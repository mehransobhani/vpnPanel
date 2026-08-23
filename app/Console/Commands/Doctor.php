<?php

namespace App\Console\Commands;

use App\Models\Inbound;
use App\Models\Server;
use App\Models\Subscription;
use App\Services\Xray\NodeClient;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * بررسی سرتاسری سلامت پنل و نود — «چرا مشتری وصل نمی‌شود؟»
 */
class Doctor extends Command
{
    protected $signature = 'panel:doctor';

    protected $description = 'بررسی کامل پنل و نود و گزارش مشکلات';

    private int $problems = 0;

    public function handle(NodeClient $client): int
    {
        $this->newLine();

        $node = Server::node();

        if (! $node) {
            $this->bad('نودی راه‌اندازی نشده است.');
            $this->hint('php artisan panel:setup-local-node --address=IP_سرور --port=443');

            return self::FAILURE;
        }

        $this->section('نود');
        $this->line("  نام: {$node->name}");
        $this->line("  آدرس: {$node->address}");

        $publicIp = $this->publicIp();
        $this->checkAddress($node, $publicIp);
        $this->checkActive($node);

        $this->section('سرویس Xray');
        $version = $this->checkXray($client, $node);

        $this->section('اینباندها');
        $inbounds = $this->checkInbounds($client, $node);

        $this->section('دسترسی از بیرون');
        $this->checkPublishedPort($inbounds);
        foreach ($inbounds as $inbound) {
            $this->checkPort($node, $inbound);
        }

        $this->section('دامنهٔ پوششی REALITY');
        foreach ($inbounds->where('security', 'reality') as $inbound) {
            $this->checkRealityDest($inbound);
        }

        $this->section('کاربران');
        $this->checkUsers($client, $node, $inbounds);

        $this->newLine();

        if ($this->problems === 0) {
            $this->info('  همه‌چیز سالم است'.($version ? " — Xray $version" : ''));
        } else {
            $this->warn("  {$this->problems} مشکل پیدا شد (بالا را ببینید).");
        }

        $this->newLine();

        return $this->problems === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function checkAddress(Server $node, ?string $publicIp): void
    {
        if (preg_match('/[^\x20-\x7E]/', $node->address)) {
            $this->bad('آدرس نود حروف غیرانگلیسی دارد — متن نمونه به‌جای IP واقعی ثبت شده.');
            $this->hint('php artisan panel:setup-local-node --address='.($publicIp ?: 'IP_سرور').' --port=443');

            return;
        }

        $isIp = (bool) filter_var($node->address, FILTER_VALIDATE_IP);
        $isDomain = (bool) preg_match('/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i', $node->address);

        if (! $isIp && ! $isDomain) {
            $this->bad("آدرس «{$node->address}» نه IP معتبر است نه دامنه.");
            $this->hint('php artisan panel:setup-local-node --address='.($publicIp ?: 'IP_سرور').' --port=443');

            return;
        }

        $this->ok('آدرس معتبر است');

        if ($publicIp && $isIp && $node->address !== $publicIp) {
            $this->warnLine("آدرس ثبت‌شده ({$node->address}) با IP عمومی سرور ($publicIp) فرق دارد.");
        }
    }

    private function checkActive(Server $node): void
    {
        $node->is_active
            ? $this->ok('نود فعال است')
            : $this->bad('نود غیرفعال است — لینک اشتراک همهٔ مشتری‌ها خالی برمی‌گردد.');
    }

    private function checkXray(NodeClient $client, Server $node): ?string
    {
        try {
            $version = $client->ping($node);
            $this->ok("سرویس Xray بالا است و API پاسخ می‌دهد (v$version)");

            return $version;
        } catch (Throwable $e) {
            $this->bad($e->getMessage());
            $this->hint('docker compose --profile vpn up -d xray');

            return null;
        }
    }

    private function checkInbounds(NodeClient $client, Server $node)
    {
        $inbounds = $node->inbounds()->active()->get();

        if ($inbounds->isEmpty()) {
            $this->bad('هیچ اینباند فعالی در پنل ثبت نشده — کانفیگی ساخته نمی‌شود.');

            return $inbounds;
        }

        try {
            $onNode = collect($client->discoverInbounds($node))->keyBy('tag');
        } catch (Throwable $e) {
            $this->warnLine('config.json نود خوانده نشد: '.$e->getMessage());

            return $inbounds;
        }

        foreach ($inbounds as $inbound) {
            if (! $onNode->has($inbound->tag)) {
                $this->bad("اینباند «{$inbound->tag}» در پنل هست ولی در config.json نود نیست.");
                $this->hint('tagهای موجود روی نود: '.$onNode->keys()->implode('، '));

                continue;
            }

            $nodePort = (int) ($onNode[$inbound->tag]['port'] ?? 0);

            if ($nodePort !== (int) $inbound->port) {
                $this->bad("پورت اینباند «{$inbound->tag}» در پنل {$inbound->port} است ولی روی نود $nodePort.");

                continue;
            }

            $this->ok("اینباند «{$inbound->tag}» با نود هماهنگ است ({$inbound->protocol}:{$inbound->port})");
        }

        return $inbounds;
    }

    /**
     * compose پورت را از XRAY_PORT در .env می‌خواند. اگر با پورت اینباند
     * یکی نباشد، Xray روی پورتی گوش می‌دهد که به بیرون منتشر نشده —
     * مشتری هیچ‌وقت وصل نمی‌شود.
     */
    private function checkPublishedPort($inbounds): void
    {
        $envPath = base_path('.env');

        if (! is_readable($envPath)) {
            return;
        }

        if (! preg_match('/^XRAY_PORT=(\d+)/m', file_get_contents($envPath), $m)) {
            $this->warnLine('XRAY_PORT در .env تعریف نشده — compose پورت پیش‌فرض ۴۴۳ را منتشر می‌کند.');

            return;
        }

        $published = (int) $m[1];
        $ports = $inbounds->pluck('port')->map(fn ($p) => (int) $p);

        if ($ports->contains($published)) {
            $this->ok("XRAY_PORT در .env ($published) با اینباند هماهنگ است");

            return;
        }

        $this->bad(
            "XRAY_PORT در .env برابر $published است ولی اینباند(های) فعال روی پورت "
            .$ports->implode('، ').' هستند — این پورت به بیرون منتشر نشده.'
        );
        $this->hint('sed -i "s/^XRAY_PORT=.*/XRAY_PORT='.$ports->first().'/" .env');
        $this->hint('docker compose --profile vpn up -d xray');
    }

    private function checkPort(Server $node, Inbound $inbound): void
    {
        // از داخل شبکهٔ داکر به سرویس xray وصل می‌شویم؛ باز بودن روی
        // اینترنت را نمی‌سنجد ولی نشان می‌دهد Xray واقعاً گوش می‌دهد.
        $host = explode(':', $node->xray_api)[0];
        $socket = @fsockopen($host, (int) $inbound->port, $errno, $errstr, 5);

        if ($socket) {
            fclose($socket);
            $this->ok("پورت {$inbound->port} روی نود باز است");
            $this->line("      برای دسترسی مشتری، روی هاست هم لازم است: ufw allow {$inbound->port}/tcp");

            return;
        }

        $this->bad("Xray روی پورت {$inbound->port} گوش نمی‌دهد ($errstr).");
        $this->hint('docker compose logs xray --tail 20');
    }

    private function checkRealityDest(Inbound $inbound): void
    {
        if (! $inbound->sni) {
            $this->bad("اینباند «{$inbound->tag}» امنیت reality دارد ولی SNI ندارد.");

            return;
        }

        $start = microtime(true);
        $socket = @fsockopen('ssl://'.$inbound->sni, 443, $errno, $errstr, 10);
        $ms = (int) ((microtime(true) - $start) * 1000);

        if (! $socket) {
            $this->bad("دامنهٔ پوششی {$inbound->sni} از این سرور در دسترس نیست ($errstr).");
            $this->hint('REALITY برای هر اتصال با این دامنه هندشیک می‌کند؛ در دسترس نبودنش یعنی هیچ‌کس وصل نمی‌شود.');

            return;
        }

        fclose($socket);

        if ($ms > 3000) {
            $this->bad("دامنهٔ پوششی {$inbound->sni} خیلی کند است ({$ms}ms) — مشتری‌ها وصل نمی‌شوند.");
            $this->hint('یک دامنهٔ نزدیک‌تر انتخاب کنید: --sni=dl.google.com یا --sni=www.cloudflare.com');

            return;
        }

        $this->ok("دامنهٔ پوششی {$inbound->sni} در دسترس است ({$ms}ms)");
    }

    private function checkUsers(NodeClient $client, Server $node, $inbounds): void
    {
        $expected = $node->subscriptions()->active()->count();
        $this->line("  سرویس فعال در پنل: $expected");

        if ($expected === 0) {
            return;
        }

        foreach ($inbounds as $inbound) {
            try {
                $count = $client->countUsers($node, $inbound);
            } catch (Throwable $e) {
                $this->warnLine("شمارش کاربران «{$inbound->tag}» ناموفق: ".$e->getMessage());

                continue;
            }

            if ($count < $expected) {
                $this->bad("روی «{$inbound->tag}» فقط $count کاربر از $expected تا ثبت شده.");
                $this->hint('php artisan panel:sync-node');

                continue;
            }

            $this->ok("«{$inbound->tag}»: $count کاربر روی نود");
        }
    }

    private function publicIp(): ?string
    {
        $process = Process::fromShellCommandline('curl -s --max-time 6 https://api.ipify.org');
        $process->run();
        $ip = trim($process->getOutput());

        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : null;
    }

    private function section(string $title): void
    {
        $this->newLine();
        $this->line("  <options=bold>$title</>");
    }

    private function ok(string $message): void
    {
        $this->line("    <fg=green>✓</> $message");
    }

    private function bad(string $message): void
    {
        $this->problems++;
        $this->line("    <fg=red>✗</> $message");
    }

    private function warnLine(string $message): void
    {
        $this->line("    <fg=yellow>!</> $message");
    }

    private function hint(string $command): void
    {
        $this->line("      <fg=cyan>→ $command</>");
    }
}
