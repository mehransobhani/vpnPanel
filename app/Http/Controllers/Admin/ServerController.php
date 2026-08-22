<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Server;
use App\Services\Xray\NodeClient;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

class ServerController extends Controller
{
    public function index()
    {
        return view('admin.servers.index', [
            'servers' => Server::withCount(['inbounds', 'subscriptions'])->orderBy('sort')->paginate(20),
        ]);
    }

    public function create()
    {
        return view('admin.servers.form', ['server' => new Server([
            'sync_driver' => config('panel.sync_driver'),
            'xray_bin' => config('panel.xray.bin'),
            'xray_api' => config('panel.xray.api'),
            'ssh_port' => 22,
            'ssh_user' => 'root',
            'xray_config_path' => '/usr/local/etc/xray/config.json',
            'is_active' => true,
        ])]);
    }

    public function store(Request $request)
    {
        $server = Server::create($this->validated($request));

        return redirect()->route('admin.servers.edit', $server)
            ->with('status', 'سرور ثبت شد. حالا اینباندها را اضافه کنید.');
    }

    public function edit(Server $server)
    {
        return view('admin.servers.form', ['server' => $server->load('inbounds')]);
    }

    public function update(Request $request, Server $server)
    {
        $data = $this->validated($request);

        // اگر فیلد رمز/کلید خالی فرستاده شد، مقدار قبلی حفظ شود
        foreach (['ssh_password', 'ssh_private_key'] as $secret) {
            if (blank($data[$secret] ?? null)) {
                unset($data[$secret]);
            }
        }

        $server->update($data);

        return back()->with('status', 'سرور به‌روزرسانی شد.');
    }

    public function destroy(Server $server)
    {
        $server->delete();

        return redirect()->route('admin.servers.index')->with('status', 'سرور حذف شد.');
    }

    /** تست اتصال SSH و نمایش اینباندهای موجود روی نود */
    public function test(Server $server, NodeClient $client)
    {
        try {
            $version = $client->ping($server);
            $inbounds = $client->discoverInbounds($server);

            $server->forceFill(['last_seen_at' => now(), 'last_error' => null])->saveQuietly();

            $tags = collect($inbounds)->map(fn ($i) => "{$i['tag']} ({$i['protocol']}:{$i['port']})")->implode('، ');

            return back()->with('status', "اتصال موفق — Xray v$version. اینباندهای نود: ".($tags ?: 'هیچ'));
        } catch (Throwable $e) {
            $server->forceFill(['last_error' => mb_substr($e->getMessage(), 0, 250)])->saveQuietly();

            return back()->withErrors(['connection' => $e->getMessage()]);
        }
    }

    /** بازنویسی همهٔ کاربران فعال روی نود */
    public function resync(Server $server)
    {
        $count = 0;

        foreach ($server->subscriptions()->active()->pluck('subscriptions.id') as $id) {
            \App\Jobs\SyncSubscriptionToNode::dispatch((int) $id, $server->id, 'add');
            $count++;
        }

        return back()->with('status', "$count سرویس برای همگام‌سازی در صف قرار گرفت.");
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'size:2'],
            'address' => ['required', 'string', 'max:190'],
            'sync_driver' => ['required', Rule::in(['local', 'ssh', 'manual'])],
            'ssh_host' => ['nullable', 'string', 'max:190'],
            'ssh_port' => ['required', 'integer', 'between:1,65535'],
            'ssh_user' => ['required', 'string', 'max:64'],
            'ssh_password' => ['nullable', 'string', 'max:255'],
            'ssh_private_key' => ['nullable', 'string'],
            'xray_bin' => ['required', 'string', 'max:190'],
            'xray_api' => ['required', 'string', 'max:190'],
            'xray_config_path' => ['required', 'string', 'max:190'],
            'capacity' => ['required', 'integer', 'min:0'],
            'sort' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]) + ['is_active' => $request->boolean('is_active')];
    }
}
