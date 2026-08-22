@extends('layouts.app')
@section('title', 'پلن‌ها')

@section('content')
    <div class="flex items-center justify-between mb-5">
        <h1 class="text-xl font-bold">پلن‌های فروش</h1>
        <a href="{{ route('admin.plans.create') }}"
           class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm hover:bg-indigo-500">+ پلن جدید</a>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 overflow-x-auto">
        <table class="w-full text-sm min-w-[750px]">
            <thead class="bg-slate-50 text-slate-500 text-xs">
                <tr>
                    <th class="text-right px-4 py-3">نام</th>
                    <th class="text-right px-4 py-3">مدت</th>
                    <th class="text-right px-4 py-3">حجم</th>
                    <th class="text-right px-4 py-3">دستگاه</th>
                    <th class="text-right px-4 py-3">قیمت</th>
                    <th class="text-right px-4 py-3">فروش</th>
                    <th class="text-right px-4 py-3">وضعیت</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($plans as $plan)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.plans.edit', $plan) }}" class="font-medium text-indigo-600 hover:underline">
                                {{ $plan->name }}
                            </a>
                            @if ($plan->is_featured)
                                <span class="text-[11px] px-1.5 py-0.5 rounded bg-amber-100 text-amber-700">ویژه</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $plan->duration_days }} روز</td>
                        <td class="px-4 py-3">{{ $plan->traffic_gb ? $plan->traffic_gb.' GB' : 'نامحدود' }}</td>
                        <td class="px-4 py-3">{{ $plan->device_limit }}</td>
                        <td class="px-4 py-3">{{ \App\Support\Format::money($plan->price) }}</td>
                        <td class="px-4 py-3">{{ $plan->subscriptions_count }}</td>
                        <td class="px-4 py-3">
                            <span class="text-xs px-2 py-0.5 rounded-full {{ $plan->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' }}">
                                {{ $plan->is_active ? 'فعال' : 'غیرفعال' }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-10 text-center text-slate-500">پلنی تعریف نشده است.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
