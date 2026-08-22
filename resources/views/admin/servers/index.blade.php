@extends('layouts.app')
@section('title', 'سرورها')

@section('content')
    <div class="flex items-center justify-between mb-5">
        <h1 class="text-xl font-bold">سرورها (نودهای Xray)</h1>
        <a href="{{ route('admin.servers.create') }}"
           class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm hover:bg-indigo-500">+ سرور جدید</a>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 overflow-x-auto">
        <table class="w-full text-sm min-w-[800px]">
            <thead class="bg-slate-50 text-slate-500 text-xs">
                <tr>
                    <th class="text-right px-4 py-3">نام</th>
                    <th class="text-right px-4 py-3">آدرس</th>
                    <th class="text-right px-4 py-3">اینباند</th>
                    <th class="text-right px-4 py-3">سرویس</th>
                    <th class="text-right px-4 py-3">آخرین اتصال</th>
                    <th class="text-right px-4 py-3">وضعیت</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($servers as $server)
                    @php $online = $server->last_seen_at && $server->last_seen_at->gt(now()->subHour()); @endphp
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.servers.edit', $server) }}" class="font-medium text-indigo-600 hover:underline">
                                {{ $server->name }}
                            </a>
                            @if ($server->country)
                                <span class="text-xs text-slate-400 ltr">{{ strtoupper($server->country) }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 ltr text-xs font-mono text-slate-600">{{ $server->address }}</td>
                        <td class="px-4 py-3">{{ $server->inbounds_count }}</td>
                        <td class="px-4 py-3">
                            {{ $server->subscriptions_count }}{{ $server->capacity ? ' / '.$server->capacity : '' }}
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-500">
                            {{ $server->last_seen_at ? \App\Support\Format::jalali($server->last_seen_at, true) : '—' }}
                        </td>
                        <td class="px-4 py-3">
                            @if (! $server->is_active)
                                <span class="text-xs px-2 py-0.5 rounded-full bg-slate-200 text-slate-600">غیرفعال</span>
                            @elseif ($server->sync_driver === 'manual')
                                <span class="text-xs px-2 py-0.5 rounded-full bg-sky-100 text-sky-700">دستی</span>
                            @elseif ($online)
                                <span class="text-xs px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700">آنلاین</span>
                            @else
                                <span class="text-xs px-2 py-0.5 rounded-full bg-rose-100 text-rose-700" title="{{ $server->last_error }}">
                                    قطع
                                </span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-slate-500">سروری ثبت نشده است.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $servers->links() }}</div>
@endsection
