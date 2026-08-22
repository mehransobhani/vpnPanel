<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Services\Xray\SubscriptionFormatter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * اندپوینت عمومی لینک اشتراک — همان آدرسی که در کلاینت وارد می‌شود.
 * بدون احراز هویت است؛ امنیت آن به یکتا و غیرقابل‌حدس‌بودن token وابسته است.
 */
class SubscriptionLinkController extends Controller
{
    public function __invoke(Request $request, string $token, SubscriptionFormatter $formatter): Response
    {
        $subscription = Subscription::where('token', $token)
            ->with(['plan', 'user', 'servers.inbounds'])
            ->firstOrFail();

        $subscription->forceFill(['last_online_at' => now()])->saveQuietly();

        $format = $request->query('format', $this->guessFormat($request));

        [$body, $contentType] = match ($format) {
            'clash' => [$formatter->clash($subscription), 'text/yaml; charset=utf-8'],
            'raw' => [$formatter->raw($subscription), 'text/plain; charset=utf-8'],
            default => [$formatter->base64($subscription), 'text/plain; charset=utf-8'],
        };

        // سرویس غیرقابل‌استفاده: پاسخ خالی می‌دهیم تا کلاینت کانفیگ قدیمی را پاک کند.
        if (! $subscription->isUsable()) {
            $body = $format === 'base64' ? base64_encode('') : '';
        }


        return response($body, 200)
            ->header('Content-Type', $contentType)
            ->header('Profile-Title', 'base64:'.base64_encode(
                config('panel.brand').' — '.$subscription->remark
            ))
            ->header('Profile-Update-Interval', '12')
            ->header('Subscription-Userinfo', sprintf(
                'upload=%d; download=%d; total=%d; expire=%d',
                $subscription->upload,
                $subscription->download,
                $subscription->traffic_limit,
                $subscription->expires_at?->timestamp ?? 0,
            ))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate')
            ->header('Content-Disposition', 'inline; filename="'.$subscription->token.'"');
    }

    /** بعضی کلاینت‌ها فرمت را از روی User-Agent تشخیص می‌دهند. */
    private function guessFormat(Request $request): string
    {
        $ua = strtolower($request->userAgent() ?? '');

        return str_contains($ua, 'clash') || str_contains($ua, 'mihomo') || str_contains($ua, 'stash')
            ? 'clash'
            : 'base64';
    }
}
