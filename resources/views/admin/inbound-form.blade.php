@php
    $new = $inbound === null;
    $field = 'w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm ltr focus:ring-2 focus:ring-indigo-500 outline-none';
    $label = 'block text-[11px] text-slate-600 mb-1';
    $v = fn ($key, $default = null) => old($key, $new ? $default : ($inbound->$key ?? $default));
@endphp

<details class="bg-white rounded-2xl border border-slate-200 overflow-hidden" @unless($new) open @endunless>
    <summary class="px-5 py-3.5 cursor-pointer select-none flex items-center justify-between gap-2 hover:bg-slate-50">
        @if ($new)
            <span class="text-sm font-medium text-indigo-600">+ افزودن اینباند</span>
        @else
            <span class="flex items-center gap-2 text-sm flex-wrap">
                <b>{{ $inbound->tag }}</b>
                <span class="px-1.5 py-0.5 rounded bg-indigo-50 text-indigo-700 text-[11px] uppercase">{{ $inbound->protocol }}</span>
                <span class="px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 text-[11px] ltr">:{{ $inbound->port }}</span>
                <span class="px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 text-[11px]">{{ $inbound->network }}</span>
                @if ($inbound->security !== 'none')
                    <span class="px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700 text-[11px]">{{ $inbound->security }}</span>
                @endif
                @unless ($inbound->is_active)
                    <span class="px-1.5 py-0.5 rounded bg-slate-200 text-slate-500 text-[11px]">غیرفعال</span>
                @endunless
            </span>
        @endif
    </summary>

    <form method="POST" class="p-5 pt-2 border-t border-slate-100 space-y-4"
          action="{{ $new ? route('admin.inbounds.store') : route('admin.inbounds.update', $inbound) }}">
        @csrf
        @unless ($new) @method('PUT') @endunless

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div>
                <label class="{{ $label }}">Tag * <span class="text-slate-400">(مثل config.json)</span></label>
                <input name="tag" required value="{{ $v('tag') }}" class="{{ $field }}" placeholder="vless-reality">
            </div>
            <div>
                <label class="{{ $label }}">پروتکل *</label>
                <select name="protocol" class="{{ $field }}">
                    @foreach (\App\Models\Inbound::PROTOCOLS as $p)
                        <option value="{{ $p }}" @selected($v('protocol') === $p)>{{ $p }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="{{ $label }}">پورت *</label>
                <input name="port" type="number" required min="1" max="65535" value="{{ $v('port') }}" class="{{ $field }}">
            </div>
            <div>
                <label class="{{ $label }}">ترتیب</label>
                <input name="sort" type="number" required min="0" value="{{ $v('sort', 0) }}" class="{{ $field }}">
            </div>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div>
                <label class="{{ $label }}">Network *</label>
                <select name="network" class="{{ $field }}">
                    @foreach (\App\Models\Inbound::NETWORKS as $n)
                        <option value="{{ $n }}" @selected($v('network', 'tcp') === $n)>{{ $n }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="{{ $label }}">Security *</label>
                <select name="security" class="{{ $field }}">
                    @foreach (\App\Models\Inbound::SECURITIES as $s)
                        <option value="{{ $s }}" @selected($v('security', 'none') === $s)>{{ $s }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="{{ $label }}">SNI / servername</label>
                <input name="sni" value="{{ $v('sni') }}" class="{{ $field }}" placeholder="www.microsoft.com">
            </div>
            <div>
                <label class="{{ $label }}">Fingerprint</label>
                <select name="fingerprint" class="{{ $field }}">
                    <option value="">—</option>
                    @foreach (['chrome', 'firefox', 'safari', 'edge', 'ios', 'android', 'random', 'randomized'] as $fp)
                        <option value="{{ $fp }}" @selected($v('fingerprint') === $fp)>{{ $fp }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div>
                <label class="{{ $label }}">Path <span class="text-slate-400">(ws / xhttp)</span></label>
                <input name="path" value="{{ $v('path') }}" class="{{ $field }}" placeholder="/ray">
            </div>
            <div>
                <label class="{{ $label }}">Host header</label>
                <input name="host" value="{{ $v('host') }}" class="{{ $field }}">
            </div>
            <div>
                <label class="{{ $label }}">gRPC serviceName</label>
                <input name="service_name" value="{{ $v('service_name') }}" class="{{ $field }}">
            </div>
            <div>
                <label class="{{ $label }}">Flow <span class="text-slate-400">(vless + tcp)</span></label>
                <select name="flow" class="{{ $field }}">
                    <option value="">—</option>
                    <option value="xtls-rprx-vision" @selected($v('flow') === 'xtls-rprx-vision')>xtls-rprx-vision</option>
                </select>
            </div>
        </div>

        <fieldset class="border border-slate-200 rounded-xl p-4 grid grid-cols-2 sm:grid-cols-3 gap-3">
            <legend class="text-[11px] text-slate-500 px-2">پارامترهای REALITY</legend>
            <div>
                <label class="{{ $label }}">Public key (pbk)</label>
                <input name="reality_public_key" value="{{ $v('reality_public_key') }}" class="{{ $field }} font-mono text-[11px]">
            </div>
            <div>
                <label class="{{ $label }}">Short ID (sid)</label>
                <input name="reality_short_id" value="{{ $v('reality_short_id') }}" class="{{ $field }} font-mono text-[11px]">
            </div>
            <div>
                <label class="{{ $label }}">SpiderX (spx)</label>
                <input name="reality_spider_x" value="{{ $v('reality_spider_x') }}" class="{{ $field }}" placeholder="/">
            </div>
        </fieldset>

        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
            <div>
                <label class="{{ $label }}">ALPN</label>
                <input name="alpn" value="{{ $v('alpn') }}" class="{{ $field }}" placeholder="h2,http/1.1">
            </div>
            <div>
                <label class="{{ $label }}">Header type <span class="text-slate-400">(tcp)</span></label>
                <input name="header_type" value="{{ $v('header_type', 'none') }}" class="{{ $field }}">
            </div>
            <div>
                <label class="{{ $label }}">قالب نام کانفیگ *</label>
                <input name="remark_template" required value="{{ $v('remark_template', '{brand}-{country}') }}" class="{{ $field }}">
            </div>
        </div>

        <p class="text-[11px] text-slate-400">
            متغیرهای قالب نام: <code class="ltr">{brand}</code> <code class="ltr">{server}</code>
            <code class="ltr">{country}</code> <code class="ltr">{protocol}</code>
            <code class="ltr">{plan}</code> <code class="ltr">{tag}</code> <code class="ltr">{user}</code>
        </p>

        <div class="flex items-center justify-between gap-3 flex-wrap pt-2 border-t border-slate-100">
            <div class="flex gap-4 text-sm">
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="is_active" value="1" class="rounded" @checked($v('is_active', true))>
                    فعال
                </label>
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="allow_insecure" value="1" class="rounded" @checked($v('allow_insecure', false))>
                    allowInsecure
                </label>
            </div>

            <button class="px-4 py-1.5 rounded-lg bg-indigo-600 text-white text-sm hover:bg-indigo-500">
                {{ $new ? 'افزودن' : 'ذخیره' }}
            </button>
        </div>
    </form>

    @unless ($new)
        <form method="POST" action="{{ route('admin.inbounds.destroy', $inbound) }}"
              class="px-5 pb-4 -mt-2" onsubmit="return confirm('این اینباند حذف شود؟')">
            @csrf @method('DELETE')
            <button class="text-xs text-rose-600 hover:underline">حذف اینباند</button>
        </form>
    @endunless
</details>
