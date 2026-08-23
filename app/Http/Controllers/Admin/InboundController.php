<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inbound;
use App\Models\Server;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InboundController extends Controller
{
    public function store(Request $request)
    {
        $node = Server::node() ?? abort(404, 'نودی راه‌اندازی نشده است.');

        $node->inbounds()->create($this->validated($request, $node));

        return back()->with('status',
            'اینباند اضافه شد. مطمئن شوید همین tag در config.json نود هم هست، سپس «همگام‌سازی مجدد» بزنید.');
    }

    public function update(Request $request, Inbound $inbound)
    {
        $inbound->update($this->validated($request, $inbound->server, $inbound));

        return back()->with('status', 'اینباند به‌روزرسانی شد.');
    }

    public function destroy(Inbound $inbound)
    {
        $inbound->delete();

        return back()->with('status', 'اینباند حذف شد.');
    }

    private function validated(Request $request, Server $server, ?Inbound $inbound = null): array
    {
        $data = $request->validate([
            'tag' => [
                'required', 'string', 'max:64',
                Rule::unique('inbounds', 'tag')
                    ->where('server_id', $server->id)
                    ->ignore($inbound?->id),
            ],
            'protocol' => ['required', Rule::in(Inbound::PROTOCOLS)],
            'port' => ['required', 'integer', 'between:1,65535'],
            'network' => ['required', Rule::in(Inbound::NETWORKS)],
            'security' => ['required', Rule::in(Inbound::SECURITIES)],
            'sni' => ['nullable', 'string', 'max:190'],
            'host' => ['nullable', 'string', 'max:190'],
            'path' => ['nullable', 'string', 'max:190'],
            'service_name' => ['nullable', 'string', 'max:190'],
            'header_type' => ['nullable', 'string', 'max:32'],
            'flow' => ['nullable', 'string', 'max:32'],
            'fingerprint' => ['nullable', 'string', 'max:16'],
            'reality_public_key' => ['nullable', 'string', 'max:190'],
            'reality_short_id' => ['nullable', 'string', 'max:32'],
            'reality_spider_x' => ['nullable', 'string', 'max:190'],
            'alpn' => ['nullable', 'string', 'max:64'],
            'remark_template' => ['required', 'string', 'max:190'],
            'sort' => ['required', 'integer', 'min:0'],
        ]);

        // reality بدون کلید عمومی بی‌معنی است
        if ($data['security'] === 'reality' && blank($data['reality_public_key'])) {
            abort(422, 'برای امنیت reality وارد کردن public key الزامی است.');
        }

        $data['header_type'] = $data['header_type'] ?: 'none';
        $data['allow_insecure'] = $request->boolean('allow_insecure');
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
