<?php

namespace App\Services\Xray;

use App\Models\Inbound;
use App\Models\Server;
use App\Models\Subscription;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SSH2;
use RuntimeException;

/**
 * ارتباط با نودهای Xray از طریق دستور `xray api`.
 *
 * سه حالت پشتیبانی می‌شود (فیلد `sync_driver` روی هر سرور):
 *
 *  - `local`  نود روی همین ماشین است (سرویس xray در همین compose).
 *             دستور مستقیماً در کانتینر app اجرا و به `xray:10085` فرستاده می‌شود.
 *  - `ssh`    نود یک سرور جداست؛ دستور از طریق SSH روی خودش اجرا می‌شود.
 *  - `manual` پنل فقط کانفیگ می‌سازد و به نود دست نمی‌زند.
 *
 * در هر دو حالت خودکار، config.json نود باید بلوک api + stats + policy
 * داشته باشد. نمونهٔ کامل در docs/03-xray-node.md آمده است.
 */
class NodeClient
{
    private array $connections = [];

    public function __construct(private readonly LinkBuilder $links) {}

    /**
     * افزودن کاربر به تمام اینباندهای فعال یک سرور.
     *
     * ورودی `xray api adu` یک قطعهٔ config است که کاربران در
     * inbounds[].settings.clients قرار می‌گیرند.
     */
    public function addUser(Server $server, Subscription $subscription): void
    {
        if ($server->sync_driver === 'manual') {
            throw new RuntimeException('این سرور روی حالت دستی تنظیم شده و همگام‌سازی خودکار ندارد.');
        }

        foreach ($server->inbounds()->active()->get() as $inbound) {
            $settings = ['clients' => [$this->clientJson($inbound, $subscription)]];

            // vless بدون decryption ساخته نمی‌شود.
            if ($inbound->protocol === 'vless') {
                $settings['decryption'] = 'none';
            }

            // `port` اجباری است: Xray پیش از افزودن کاربر کل اینباند را می‌سازد
            // و بدون پورت با خطای «Listen on AnyIP but no Port(s) set» رد می‌کند.
            $payload = json_encode([
                'inbounds' => [[
                    'tag' => $inbound->tag,
                    'port' => (int) $inbound->port,
                    'protocol' => $inbound->protocol,
                    'settings' => $settings,
                ]],
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $this->runApi($server, 'adu', $payload);
        }
    }

    /**
     * حذف کاربر از تمام اینباندهای سرور.
     *
     * `xray api rmu` با فلگ ‎-tag و ایمیل کاربر کار می‌کند.
     */
    public function removeUser(Server $server, Subscription $subscription): void
    {
        if ($server->sync_driver === 'manual') {
            return;
        }

        foreach ($server->inbounds()->get() as $inbound) {
            $command = sprintf(
                '%s api rmu --server=%s -tag=%s %s 2>&1',
                escapeshellarg($server->xray_bin),
                escapeshellarg($server->xray_api),
                escapeshellarg($inbound->tag),
                escapeshellarg($subscription->email_tag),
            );

            $out = trim($this->exec($server, $command));

            // نبودنِ کاربر خطا نیست — یعنی از قبل حذف شده.
            if ($out !== '' && ! preg_match('/OK|success|not found|not exist/i', $out)) {
                throw new RuntimeException('xray api rmu: '.mb_substr($out, 0, 300));
            }
        }
    }

    /**
     * خواندن آمار مصرف همهٔ کاربران سرور و صفر کردن شمارنده‌ها.
     *
     * خروجی: ['email_tag' => ['up' => int, 'down' => int], ...]
     *
     * @return array<string, array{up: int, down: int}>
     */
    public function fetchUsage(Server $server, bool $reset = true): array
    {
        if ($server->sync_driver === 'manual') {
            return [];
        }

        $flags = $reset ? '-reset' : '';
        $raw = $this->exec($server, sprintf(
            '%s api statsquery --server=%s %s -pattern "user>>>" 2>&1',
            escapeshellarg($server->xray_bin),
            escapeshellarg($server->xray_api),
            $flags,
        ));

        $data = json_decode($raw, true);

        if (! is_array($data)) {
            throw new RuntimeException('پاسخ نامعتبر از xray api statsquery: '.mb_substr($raw, 0, 300));
        }

        $usage = [];

        foreach ($data['stat'] ?? [] as $stat) {
            // نمونه: user>>>ali@panel>>>traffic>>>uplink
            if (! preg_match('/^user>>>(.+)>>>traffic>>>(uplink|downlink)$/', $stat['name'] ?? '', $m)) {
                continue;
            }

            [$email, $direction] = [$m[1], $m[2]];
            $usage[$email] ??= ['up' => 0, 'down' => 0];
            $usage[$email][$direction === 'uplink' ? 'up' : 'down'] += (int) ($stat['value'] ?? 0);
        }

        return $usage;
    }

    /**
     * تست اتصال و برگرداندن نسخهٔ Xray روی نود.
     */
    public function ping(Server $server): string
    {
        $out = $this->exec($server, escapeshellarg($server->xray_bin).' version 2>&1');

        if (! preg_match('/Xray\s+([\d.]+)/i', $out, $m)) {
            throw new RuntimeException('باینری Xray پیدا نشد: '.mb_substr(trim($out), 0, 200));
        }

        // نسخه فقط می‌گوید باینری هست؛ باید مطمئن شویم سرویس هم بالا و API در دسترس است.
        $probe = $this->exec($server, sprintf(
            '%s api statsquery --server=%s -pattern "___probe___" 2>&1',
            escapeshellarg($server->xray_bin),
            escapeshellarg($server->xray_api),
        ));

        if (json_decode($probe, true) === null) {
            throw new RuntimeException(
                'API نود پاسخ نداد ('.$server->xray_api.'): '.mb_substr(trim($probe), 0, 200)
            );
        }

        return $m[1];
    }

    /**
     * شمار کاربرانی که هم‌اکنون در حافظهٔ Xray برای یک اینباند ثبت‌اند.
     * برای تشخیص اینکه نود ری‌استارت شده و کاربران پاک شده‌اند.
     */
    public function countUsers(Server $server, Inbound $inbound): int
    {
        $out = $this->exec($server, sprintf(
            '%s api inboundusercount --server=%s -tag=%s 2>&1',
            escapeshellarg($server->xray_bin),
            escapeshellarg($server->xray_api),
            escapeshellarg($inbound->tag),
        ));

        $data = json_decode($out, true);

        if (! is_array($data)) {
            throw new RuntimeException('پاسخ نامعتبر از inboundusercount: '.mb_substr(trim($out), 0, 200));
        }

        // وقتی هیچ کاربری نیست، Xray فقط `{}` برمی‌گرداند.
        return (int) ($data['count'] ?? 0);
    }

    /**
     * فهرست tag اینباندهای موجود در config.json نود — برای کمک به تنظیم پنل.
     *
     * @return array<int, array{tag: string, protocol: string, port: int|null}>
     */
    public function discoverInbounds(Server $server): array
    {
        $raw = $this->exec($server, 'cat '.escapeshellarg($server->xray_config_path).' 2>&1');
        $config = json_decode($raw, true);

        if (! is_array($config)) {
            throw new RuntimeException('خواندن config.json نود ناموفق بود.');
        }

        return array_map(fn ($in) => [
            'tag' => $in['tag'] ?? '',
            'protocol' => $in['protocol'] ?? '',
            'port' => $in['port'] ?? null,
        ], array_filter(
            $config['inbounds'] ?? [],
            fn ($in) => in_array($in['protocol'] ?? '', ['vless', 'vmess', 'trojan'], true)
        ));
    }

    /**
     * ساخت آبجکت client مطابق پروتکل اینباند.
     * کلید `email` در Xray شناسهٔ یکتای شمارش مصرف است.
     */
    private function clientJson(Inbound $inbound, Subscription $subscription): array
    {
        $client = ['email' => $subscription->email_tag, 'level' => 0];

        return match ($inbound->protocol) {
            'vless' => $client + array_filter([
                'id' => $subscription->uuid,
                'flow' => ($inbound->flow && $inbound->network === 'tcp') ? $inbound->flow : null,
            ]),
            'vmess' => $client + ['id' => $subscription->uuid, 'alterId' => 0],
            'trojan' => $client + ['password' => $subscription->password],
            default => throw new RuntimeException("پروتکل پشتیبانی‌نشده: {$inbound->protocol}"),
        };
    }

    /**
     * اجرای `xray api <cmd>` با ورودی JSON روی نود.
     *
     * نکتهٔ مهم: بدون آرگومان صریح «stdin:» دستور ورودی لوله‌شده را نادیده
     * می‌گیرد و بی‌صدا «Added 0 user(s)» برمی‌گرداند.
     */
    private function runApi(Server $server, string $command, string $json): string
    {
        // JSON را با base64 منتقل می‌کنیم تا escape شدن کوتیشن‌ها مشکل نسازد.
        $cmd = sprintf(
            'echo %s | base64 -d | %s api %s --server=%s stdin: 2>&1',
            escapeshellarg(base64_encode($json)),
            escapeshellarg($server->xray_bin),
            escapeshellarg($command),
            escapeshellarg($server->xray_api),
        );

        $out = trim($this->exec($server, $cmd));

        // «Added 0 user(s)» یعنی Xray ورودی را نپذیرفته است.
        if (preg_match('/Added 0 user|Removed 0 user/i', $out) || ! preg_match('/result:\s*ok|OK|success/i', $out)) {
            throw new RuntimeException("xray api $command: ".mb_substr($out ?: 'پاسخی دریافت نشد', 0, 300));
        }

        return $out;
    }

    private function exec(Server $server, string $command): string
    {
        Log::debug('node.exec', [
            'server' => $server->name,
            'driver' => $server->sync_driver,
            'cmd' => mb_substr($command, 0, 120),
        ]);

        return $server->sync_driver === 'local'
            ? $this->execLocal($command)
            : $this->execSsh($server, $command);
    }

    /** نود محلی: دستور در همین کانتینر اجرا می‌شود. */
    private function execLocal(string $command): string
    {
        $process = Process::fromShellCommandline($command, base_path());
        $process->setTimeout(30);
        $process->run();

        // خروجی خطا هم لازم است؛ خود دستورها با 2>&1 آن را ادغام می‌کنند.
        return $process->getOutput().$process->getErrorOutput();
    }

    private function execSsh(Server $server, string $command): string
    {
        $output = $this->connect($server)->exec($command);

        if ($output === false) {
            throw new RuntimeException('اجرای دستور روی نود ناموفق بود.');
        }

        return (string) $output;
    }

    private function connect(Server $server): SSH2
    {
        if (isset($this->connections[$server->id])) {
            return $this->connections[$server->id];
        }

        $host = $server->ssh_host ?: $server->address;
        $ssh = new SSH2($host, $server->ssh_port ?: 22, 15);
        $ssh->setTimeout(60);

        $credential = $server->ssh_private_key
            ? PublicKeyLoader::load($server->ssh_private_key, $server->ssh_password ?: false)
            : $server->ssh_password;

        if (! $credential) {
            throw new RuntimeException("برای سرور «{$server->name}» رمز یا کلید SSH ثبت نشده است.");
        }

        if (! $ssh->login($server->ssh_user ?: 'root', $credential)) {
            throw new RuntimeException("ورود SSH به «{$server->name}» ناموفق بود (کاربر/رمز/کلید را بررسی کنید).");
        }

        return $this->connections[$server->id] = $ssh;
    }
}
