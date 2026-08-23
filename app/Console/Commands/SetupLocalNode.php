<?php

namespace App\Console\Commands;

use App\Models\Inbound;
use App\Models\Server;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * راه‌اندازی نود VPN روی همین سروری که پنل اجرا می‌شود.
 *
 * کلید REALITY می‌سازد، config.json سرویس xray را می‌نویسد و سرور + اینباند
 * را در پنل ثبت می‌کند. پس از اجرا کافی است سرویس xray بالا بیاید.
 */
class SetupLocalNode extends Command
{
    protected $signature = 'panel:setup-local-node
        {--address= : آدرسی که مشتری به آن وصل می‌شود (دامنه یا IP عمومی)}
        {--port=443 : پورت VLESS}
        {--sni=www.microsoft.com : دامنهٔ پوششی REALITY}
        {--name=سرور اصلی : نام نمایشی نود در پنل}
        {--country= : کد دو حرفی کشور}
        {--force : بازتولید کلیدها حتی اگر قبلاً ساخته شده باشند}';

    protected $description = 'راه‌اندازی نود Xray روی همین سرور (VLESS + REALITY)';

    private const CONFIG_PATH = 'docker/xray/config.json';

    private const TAG = 'vless-reality';

    public function handle(): int
    {
        $address = $this->option('address') ?: $this->guessAddress();

        if (! $address) {
            $this->error('آدرس عمومی تشخیص داده نشد. آن را صریح بدهید: --address=1.2.3.4');

            return self::FAILURE;
        }

        if ($problem = $this->addressProblem($address)) {
            $this->error($problem);
            $this->newLine();
            $this->line('  نمونهٔ درست:');
            $this->line('    <fg=cyan>--address=203.0.113.10</>      (IP سرور)');
            $this->line('    <fg=cyan>--address=vpn.example.com</>   (دامنه)');
            $this->newLine();
            $this->line('  یا اصلاً ندهید تا خودش IP عمومی سرور را پیدا کند:');
            $this->line('    <fg=cyan>php artisan panel:setup-local-node --port=443</>');

            return self::FAILURE;
        }

        $port = (int) $this->option('port');
        $sni = $this->option('sni');
        $configPath = base_path(self::CONFIG_PATH);

        $existing = file_exists($configPath) ? json_decode(file_get_contents($configPath), true) : null;
        $reality = $this->realitySettings($existing);
        $node = Server::node();

        // کلیدها فقط وقتی حفظ می‌شوند که این نود قبلاً در همین پنل ثبت شده باشد.
        // اگر config.json هست ولی رکوردی در دیتابیس نیست، یعنی فایل از جای
        // دیگری کپی شده و کلید خصوصی‌اش دست ما نیست — باید بازتولید شود.
        $keysUsable = $existing && $node && $reality['privateKey'] !== '' && $reality['publicKey'] !== '';

        if ($keysUsable && ! $this->option('force')) {
            $this->warn('کانفیگ موجود پیدا شد — کلیدهای فعلی حفظ می‌شوند (برای بازتولید: --force).');
        } else {
            if ($existing && ! $node) {
                $this->warn('کانفیگ Xray پیدا شد ولی نودی در پنل ثبت نیست —');
                $this->warn('این فایل احتمالاً از جای دیگری کپی شده. کلیدها بازتولید می‌شوند.');
            }

            try {
                $reality = $this->generateKeys();
            } catch (RuntimeException $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }
        }

        // ۱) نوشتن config.json برای سرویس xray
        $this->writeConfig($configPath, $port, $sni, $reality);
        $this->info('✓ کانفیگ نوشته شد: '.self::CONFIG_PATH);

        // ۲) ثبت نود در پنل — همیشه همان یک ردیف به‌روزرسانی می‌شود.
        $server = Server::node() ?? new Server;
        $server->fill([
            'name' => $this->option('name'),
            'address' => $address,
            'country' => $this->option('country') ? strtolower($this->option('country')) : null,
            'xray_bin' => config('panel.xray.bin'),
            'xray_api' => config('panel.xray.api'),
            'xray_config_path' => $configPath,
            'is_active' => true,
            'note' => 'نود Xray روی همین سروری که پنل اجرا می‌شود.',
        ])->save();

        // ۳) ثبت اینباند
        Inbound::updateOrCreate(['server_id' => $server->id, 'tag' => self::TAG], [
            'protocol' => 'vless',
            'port' => $port,
            'network' => 'tcp',
            'security' => 'reality',
            'sni' => $sni,
            'flow' => 'xtls-rprx-vision',
            'fingerprint' => 'chrome',
            'reality_public_key' => $reality['publicKey'],
            'reality_short_id' => $reality['shortId'],
            'reality_spider_x' => '/',
            'remark_template' => '{brand}-{server}',
            'is_active' => true,
        ]);

        $this->info("✓ نود «{$server->name}» و اینباند «".self::TAG.'» در پنل ثبت شدند.');

        // سرویس‌های فعالی که هنوز به نود وصل نیستند را وصل کن.
        \App\Models\Subscription::active()
            ->whereDoesntHave('servers', fn ($q) => $q->whereKey($server->id))
            ->each(fn ($sub) => $sub->servers()->syncWithoutDetaching([$server->id]));

        // ۴) به‌روزرسانی .env تا پورت با compose هماهنگ بماند
        $this->syncEnv($port);

        $this->newLine();
        $this->line('  <fg=black;bg=green> گام بعدی </> سرویس Xray را بالا بیاورید:');
        $this->newLine();
        $this->line('    <fg=cyan>docker compose --profile vpn up -d xray</>');
        $this->newLine();
        $this->line('  سپس تست کنید:');
        $this->line('    <fg=cyan>docker compose exec app php artisan panel:test-node</>');
        $this->newLine();

        if ($port === 443) {
            $this->warn('پورت ۴۴۳ اکنون در اختیار Xray است؛ پنل را روی پورت دیگری سرو کنید (APP_PORT).');
        }

        return self::SUCCESS;
    }

    /**
     * آدرس باید IP یا دامنهٔ واقعی باشد.
     * جلوگیری از خطای رایج: کپی کردن عینِ متن راهنما به‌جای مقدار واقعی.
     */
    private function addressProblem(string $address): ?string
    {
        if (preg_match('/[^\x20-\x7E]/', $address)) {
            return "آدرس «{$address}» حروف غیرانگلیسی دارد — احتمالاً متن راهنما را عیناً کپی کرده‌اید.";
        }

        if (filter_var($address, FILTER_VALIDATE_IP)) {
            return null;
        }

        // دامنه: برچسب‌های حرف/عدد/خط‌تیره که با نقطه جدا شده‌اند
        if (preg_match('/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i', $address)) {
            return null;
        }

        return "آدرس «{$address}» نه IP معتبر است نه دامنهٔ معتبر.";
    }

    /** اجرای `xray x25519` و استخراج جفت‌کلید. */
    private function generateKeys(): array
    {
        $process = new Process([config('panel.xray.bin'), 'x25519']);
        $process->run();
        $out = $process->getOutput();

        if (! preg_match('/PrivateKey:\s*(\S+)/i', $out, $priv)
            || ! preg_match('/(?:Password|Public\s*key)[^:]*:\s*(\S+)/i', $out, $pub)) {
            throw new RuntimeException('تولید کلید REALITY ناموفق بود: '.mb_substr($out.$process->getErrorOutput(), 0, 200));
        }

        return [
            'privateKey' => $priv[1],
            'publicKey' => $pub[1],
            'shortId' => bin2hex(random_bytes(4)),
        ];
    }

    /** خواندن کلیدها از کانفیگ موجود تا با --force بازتولید نشوند. */
    private function realitySettings(?array $config): array
    {
        $settings = collect($config['inbounds'] ?? [])
            ->firstWhere('tag', self::TAG)['streamSettings']['realitySettings'] ?? [];

        return [
            'privateKey' => $settings['privateKey'] ?? '',
            'publicKey' => $settings['publicKey'] ?? '',
            'shortId' => $settings['shortIds'][0] ?? '',
        ];
    }

    private function writeConfig(string $path, int $port, string $sni, array $reality): void
    {
        $config = [
            'log' => ['loglevel' => 'warning'],

            // بدون این سه بلوک، پنل نه می‌تواند کاربر اضافه کند و نه مصرف بخواند.
            'api' => ['tag' => 'api', 'services' => ['HandlerService', 'StatsService']],
            'stats' => new \stdClass,
            'policy' => [
                // کلید «0» باید آبجکت بماند، وگرنه PHP آن را آرایه سریالایز می‌کند
                // و Xray کانفیگ را رد می‌کند.
                'levels' => (object) ['0' => ['statsUserUplink' => true, 'statsUserDownlink' => true]],
                'system' => ['statsInboundUplink' => true, 'statsInboundDownlink' => true],
            ],

            'inbounds' => [
                [
                    'tag' => 'api',
                    // فقط از داخل شبکهٔ داکر در دسترس است و به هاست publish نمی‌شود.
                    'listen' => '0.0.0.0',
                    'port' => 10085,
                    'protocol' => 'dokodemo-door',
                    'settings' => ['address' => '127.0.0.1'],
                ],
                [
                    'tag' => self::TAG,
                    'listen' => '0.0.0.0',
                    'port' => $port,
                    'protocol' => 'vless',
                    // کاربران در زمان اجرا توسط پنل اضافه می‌شوند.
                    'settings' => ['clients' => [], 'decryption' => 'none'],
                    'streamSettings' => [
                        'network' => 'tcp',
                        'security' => 'reality',
                        'realitySettings' => [
                            'show' => false,
                            'dest' => "$sni:443",
                            'xver' => 0,
                            'serverNames' => [$sni],
                            'privateKey' => $reality['privateKey'],
                            // publicKey در Xray استفاده نمی‌شود؛ اینجا نگه داشته می‌شود
                            // تا اجرای دوبارهٔ دستور بتواند آن را بازیابی کند.
                            'publicKey' => $reality['publicKey'],
                            'shortIds' => [$reality['shortId']],
                        ],
                    ],
                    'sniffing' => ['enabled' => true, 'destOverride' => ['http', 'tls', 'quic']],
                ],
            ],

            'outbounds' => [
                ['protocol' => 'freedom', 'tag' => 'direct'],
                ['protocol' => 'blackhole', 'tag' => 'blocked'],
            ],

            'routing' => [
                'rules' => [
                    ['type' => 'field', 'inboundTag' => ['api'], 'outboundTag' => 'api'],
                    ['type' => 'field', 'protocol' => ['bittorrent'], 'outboundTag' => 'blocked'],
                ],
            ],
        ];

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents(
            $path,
            json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n",
        );

        // کانتینر xray با کاربر دیگری اجرا می‌شود و باید بتواند فایل را بخواند.
        chmod($path, 0644);
    }

    /** پورت Xray باید در .env هم باشد تا compose همان را publish کند. */
    private function syncEnv(int $port): void
    {
        $envPath = base_path('.env');

        if (! is_writable($envPath)) {
            $this->warn("XRAY_PORT=$port را دستی به .env اضافه کنید.");

            return;
        }

        $env = file_get_contents($envPath);

        $env = preg_match('/^XRAY_PORT=.*$/m', $env)
            ? preg_replace('/^XRAY_PORT=.*$/m', "XRAY_PORT=$port", $env)
            : rtrim($env)."\n\n# پورت نود VPN محلی\nXRAY_PORT=$port\n";

        file_put_contents($envPath, $env);
        $this->info("✓ XRAY_PORT=$port در .env تنظیم شد.");
    }

    /** حدس آدرس عمومی سرور؛ اگر شکست خورد کاربر باید --address بدهد. */
    private function guessAddress(): ?string
    {
        $host = parse_url((string) config('panel.sub_domain'), PHP_URL_HOST);

        if ($host && ! in_array($host, ['localhost', '127.0.0.1'], true)) {
            return $host;
        }

        $process = Process::fromShellCommandline('curl -s --max-time 5 https://api.ipify.org');
        $process->run();
        $ip = trim($process->getOutput());

        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : null;
    }
}
