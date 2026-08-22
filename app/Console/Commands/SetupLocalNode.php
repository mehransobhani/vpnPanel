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
        {--name=سرور اصلی : نام نمایشی سرور در پنل}
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

        $port = (int) $this->option('port');
        $sni = $this->option('sni');
        $configPath = base_path(self::CONFIG_PATH);

        $existing = file_exists($configPath) ? json_decode(file_get_contents($configPath), true) : null;
        $reality = $this->realitySettings($existing);

        if ($existing && ! $this->option('force')) {
            $this->warn('کانفیگ موجود پیدا شد — کلیدهای فعلی حفظ می‌شوند (برای بازتولید: --force).');
        } else {
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

        // ۲) ثبت سرور در پنل
        $server = Server::updateOrCreate(['name' => $this->option('name')], [
            'address' => $address,
            'country' => $this->option('country') ? strtolower($this->option('country')) : null,
            'sync_driver' => 'local',
            'xray_bin' => '/usr/local/bin/xray',
            'xray_api' => 'xray:10085',
            'xray_config_path' => $configPath,
            'is_active' => true,
            'note' => 'نود محلی — روی همین سروری که پنل اجرا می‌شود.',
        ]);

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

        $this->info("✓ سرور «{$server->name}» و اینباند «".self::TAG.'» در پنل ثبت شدند.');

        // ۴) به‌روزرسانی .env تا پورت با compose هماهنگ بماند
        $this->syncEnv($port);

        $this->newLine();
        $this->line('  <fg=black;bg=green> گام بعدی </> سرویس Xray را بالا بیاورید:');
        $this->newLine();
        $this->line('    <fg=cyan>docker compose --profile vpn up -d xray</>');
        $this->newLine();
        $this->line('  سپس تست کنید:');
        $this->line('    <fg=cyan>docker compose exec app php artisan panel:test-node "'.$server->name.'"</>');
        $this->newLine();

        if ($port === 443) {
            $this->warn('پورت ۴۴۳ اکنون در اختیار Xray است؛ پنل را روی پورت دیگری سرو کنید (APP_PORT).');
        }

        return self::SUCCESS;
    }

    /** اجرای `xray x25519` و استخراج جفت‌کلید. */
    private function generateKeys(): array
    {
        $process = new Process(['/usr/local/bin/xray', 'x25519']);
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
