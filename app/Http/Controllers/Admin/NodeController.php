<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SyncSubscriptionToNode;
use App\Models\Server;
use App\Services\Xray\NodeClient;
use Illuminate\Http\Request;
use Throwable;

/**
 * تنها نود پنل — همان سروری که اپ روی آن اجرا می‌شود.
 *
 * امکان افزودن یا حذف سرور وجود ندارد؛ نود با دستور
 * `panel:setup-local-node` ساخته می‌شود و اینجا فقط ویرایش می‌شود.
 */
class NodeController extends Controller
{
    public function show()
    {
        $node = Server::node();

        return view('admin.node', [
            'node' => $node?->load('inbounds'),
            'subscriptionCount' => $node?->subscriptions()->active()->count() ?? 0,
        ]);
    }

    public function update(Request $request)
    {
        $node = $this->nodeOrFail();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'size:2'],
            'address' => ['required', 'string', 'max:190'],
            'xray_bin' => ['required', 'string', 'max:190'],
            'xray_api' => ['required', 'string', 'max:190'],
            'xray_config_path' => ['required', 'string', 'max:190'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['country'] = $data['country'] ? strtolower($data['country']) : null;

        $node->update($data);

        return back()->with('status', 'تنظیمات نود ذخیره شد.');
    }

    /** تست اینکه سرویس Xray بالا است و پنل به آن دسترسی دارد. */
    public function test(NodeClient $client)
    {
        $node = $this->nodeOrFail();

        try {
            $version = $client->ping($node);
            $inbounds = $client->discoverInbounds($node);

            $node->forceFill(['last_seen_at' => now(), 'last_error' => null])->saveQuietly();

            $tags = collect($inbounds)
                ->map(fn ($i) => "{$i['tag']} ({$i['protocol']}:{$i['port']})")
                ->implode('، ');

            return back()->with('status', "اتصال موفق — Xray v$version. اینباندهای نود: ".($tags ?: 'هیچ'));
        } catch (Throwable $e) {
            $node->forceFill(['last_error' => mb_substr($e->getMessage(), 0, 250)])->saveQuietly();

            return back()->withErrors(['connection' => $e->getMessage()]);
        }
    }

    /** بازنویسی همهٔ کاربران فعال روی نود. */
    public function resync()
    {
        $node = $this->nodeOrFail();
        $count = 0;

        foreach ($node->subscriptions()->active()->pluck('subscriptions.id') as $id) {
            SyncSubscriptionToNode::dispatch((int) $id, $node->id, 'add');
            $count++;
        }

        return back()->with('status', "$count سرویس برای همگام‌سازی در صف قرار گرفت.");
    }

    private function nodeOrFail(): Server
    {
        return Server::node() ?? abort(404, 'نودی راه‌اندازی نشده است.');
    }
}
