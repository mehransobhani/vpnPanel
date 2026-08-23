<?php

namespace App\Services\Xray;

use App\Models\Inbound;
use App\Models\Server;
use App\Models\Subscription;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * ارتباط با سرویس Xray از طریق دستور `xray api`.
 *
 * پنل تک‌نودی است: Xray در همان compose اجرا می‌شود و آدرس API آن روی
 * شبکهٔ داخلی داکر در دسترس است (پیش‌فرض `xray:10085`). باینری Xray در
 * ایمیج `app` فقط نقش کلاینت API را دارد.
 *
 * config.json نود باید بلوک api + stats + policy داشته باشد؛ دستور
 * `panel:setup-local-node` آن را می‌سازد.
 */
class NodeClient
{
    public function __construct(private readonly LinkBuilder $links) {}

    /**
     * افزودن کاربر به تمام اینباندهای فعال نود.
     */
    public function addUser(Server $server, Subscription $subscription): void
    {
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
     * حذف کاربر از تمام اینباندهای نود.
     */
    public function removeUser(Server $server, Subscription $subscription): void
    {
        foreach ($server->inbounds()->get() as $inbound) {
            $out = trim($this->exec($server, sprintf(
                '%s api rmu --server=%s -tag=%s %s 2>&1',
                escapeshellarg($server->xray_bin),
                escapeshellarg($server->xray_api),
                escapeshellarg($inbound->tag),
                escapeshellarg($subscription->email_tag),
            )));

            $this->assertRemoved($inbound->tag, $out);
        }
    }

    /**
     * خواندن آمار مصرف همهٔ کاربران و صفر کردن شمارنده‌ها.
     *
     * @return array<string, array{up: int, down: int}>
     */
    public function fetchUsage(Server $server, bool $reset = true): array
    {
        $raw = $this->exec($server, sprintf(
            '%s api statsquery --server=%s %s -pattern "user>>>" 2>&1',
            escapeshellarg($server->xray_bin),
            escapeshellarg($server->xray_api),
            $reset ? '-reset' : '',
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
     * تست اینکه سرویس Xray بالا است و API پاسخ می‌دهد.
     * نسخهٔ Xray را برمی‌گرداند.
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
                'سرویس Xray پاسخ نداد ('.$server->xray_api.'). آیا با '
                .'`docker compose --profile vpn up -d xray` بالا آمده است؟ — '
                .mb_substr(trim($probe), 0, 160)
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
     * فهرست اینباندهای موجود در config.json نود — برای تطبیق با تنظیمات پنل.
     *
     * @return array<int, array{tag: string, protocol: string, port: int|null}>
     */
    public function discoverInbounds(Server $server): array
    {
        $raw = $this->exec($server, 'cat '.escapeshellarg($server->xray_config_path).' 2>&1');
        $config = json_decode($raw, true);

        if (! is_array($config)) {
            throw new RuntimeException('خواندن config.json نود ناموفق بود: '.$server->xray_config_path);
        }

        return array_values(array_map(fn ($in) => [
            'tag' => $in['tag'] ?? '',
            'protocol' => $in['protocol'] ?? '',
            'port' => $in['port'] ?? null,
        ], array_filter(
            $config['inbounds'] ?? [],
            fn ($in) => in_array($in['protocol'] ?? '', Inbound::PROTOCOLS, true)
        )));
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
     * بررسی خروجی `xray api rmu`.
     *
     * خروجی موفق «Removed N user(s) in total.» است و هیچ کلمهٔ OK ندارد.
     * نبودنِ خودِ کاربر خطا نیست، ولی نبودنِ اینباند (tag اشتباه) هست.
     */
    private function assertRemoved(string $tag, string $out): void
    {
        if (preg_match('/Removed\s+[1-9]\d*\s+user/i', $out)) {
            return;
        }

        if (preg_match('/User\s+\S+\s+not found/i', $out)) {
            return; // از قبل حذف شده بود
        }

        if (preg_match('/handler not found/i', $out)) {
            throw new RuntimeException(
                "اینباند «$tag» روی نود وجود ندارد. tag پنل را با config.json نود یکی کنید."
            );
        }

        throw new RuntimeException('xray api rmu: '.mb_substr(trim($out) ?: 'پاسخی دریافت نشد', 0, 300));
    }

    /**
     * اجرای `xray api <cmd>` با ورودی JSON.
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

        // خروجی موفق «Added N user(s) in total.» با N ≥ ۱ است.
        // «Added 0» یعنی Xray ورودی را نپذیرفته — معمولاً به‌خاطر tag یا port اشتباه.
        if (! preg_match('/(?:Added|Removed)\s+[1-9]\d*\s+user/i', $out)) {
            throw new RuntimeException("xray api $command: ".mb_substr(trim($out) ?: 'پاسخی دریافت نشد', 0, 300));
        }

        return $out;
    }

    private function exec(Server $server, string $command): string
    {
        Log::debug('node.exec', ['cmd' => mb_substr($command, 0, 120)]);

        $process = Process::fromShellCommandline($command, base_path());
        $process->setTimeout(30);
        $process->run();

        // خروجی خطا هم لازم است؛ خود دستورها با 2>&1 آن را ادغام می‌کنند.
        return $process->getOutput().$process->getErrorOutput();
    }
}
