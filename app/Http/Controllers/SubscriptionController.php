<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Services\Xray\LinkBuilder;
use App\Services\Xray\SubscriptionFormatter;
use App\Support\QrCode;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function index(Request $request)
    {
        return view('subscriptions.index', [
            'subscriptions' => $request->user()->subscriptions()->with('plan')->latest()->paginate(15),
        ]);
    }

    public function show(Request $request, Subscription $subscription, LinkBuilder $builder)
    {
        $this->authorizeOwner($request, $subscription);

        $subscription->load(['plan', 'servers.inbounds']);

        $links = [];
        foreach ($subscription->servers->where('is_active', true) as $server) {
            foreach ($server->inbounds->where('is_active', true)->sortBy('sort') as $inbound) {
                $links[] = [
                    'server' => $server->name,
                    'protocol' => $inbound->protocol,
                    'network' => $inbound->network,
                    'security' => $inbound->security,
                    'remark' => $builder->remark($inbound, $subscription),
                    'uri' => $builder->build($inbound, $subscription),
                ];
            }
        }

        return view('subscriptions.show', [
            'subscription' => $subscription,
            'links' => $links,
            'subUrl' => route('sub', $subscription->token),
        ]);
    }

    /** QR کد لینک اشتراک یا یک کانفیگ خاص، به صورت SVG */
    public function qr(Request $request, Subscription $subscription)
    {
        $this->authorizeOwner($request, $subscription);

        $data = $request->query('data') ?: route('sub', $subscription->token);

        return response(QrCode::svg($data), 200)
            ->header('Content-Type', 'image/svg+xml')
            ->header('Cache-Control', 'private, max-age=300');
    }

    /** دانلود فایل کانفیگ برای کلاینت‌های دسکتاپ */
    public function download(Request $request, Subscription $subscription, SubscriptionFormatter $formatter)
    {
        $this->authorizeOwner($request, $subscription);

        $format = $request->query('format', 'raw');

        [$body, $ext, $type] = match ($format) {
            'clash' => [$formatter->clash($subscription), 'yaml', 'text/yaml'],
            default => [$formatter->raw($subscription), 'txt', 'text/plain'],
        };

        return response($body, 200)
            ->header('Content-Type', "$type; charset=utf-8")
            ->header('Content-Disposition', 'attachment; filename="'.$subscription->token.".$ext\"");
    }

    private function authorizeOwner(Request $request, Subscription $subscription): void
    {
        abort_unless(
            $subscription->user_id === $request->user()->id || $request->user()->isAdmin(),
            403
        );
    }
}
