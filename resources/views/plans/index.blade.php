@extends('layouts.app')
@section('title', 'خرید سرویس')

@section('content')
    <h1 class="text-xl font-bold mb-1">انتخاب پلن</h1>
    <p class="text-sm text-slate-500 mb-6">بعد از پرداخت، کانفیگ‌ها بلافاصله در بخش «سرویس‌های من» قرار می‌گیرند.</p>

    @php $renewables = auth()->user()->subscriptions()->latest()->get(); @endphp

    @if ($plans->isEmpty())
        <div class="bg-white rounded-2xl border border-dashed p-10 text-center text-slate-500">
            هنوز پلنی تعریف نشده است.
        </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($plans as $plan)
            <div class="bg-white rounded-2xl border p-6 flex flex-col
                        {{ $plan->is_featured ? 'border-indigo-500 ring-2 ring-indigo-100' : 'border-slate-200' }}">
                @if ($plan->is_featured)
                    <span class="self-start text-xs px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700 mb-2">پرفروش</span>
                @endif

                <h3 class="font-bold text-lg">{{ $plan->name }}</h3>
                <p class="text-sm text-slate-500 mt-1 mb-4 flex-1">{{ $plan->description }}</p>

                <ul class="text-sm space-y-1.5 text-slate-600 mb-5">
                    <li>⏳ مدت: <b>{{ $plan->duration_days }}</b> روز</li>
                    <li>📊 حجم: <b>{{ $plan->traffic_gb ? $plan->traffic_gb.' GB' : 'نامحدود' }}</b></li>
                    <li>📱 دستگاه هم‌زمان: <b>{{ $plan->device_limit }}</b></li>
                    <li>🌍 لوکیشن: <b>{{ $plan->servers->isEmpty() ? 'همهٔ سرورها' : $plan->servers->pluck('name')->implode('، ') }}</b></li>
                </ul>

                <div class="text-2xl font-bold text-indigo-600 mb-4">{{ \App\Support\Format::money($plan->price) }}</div>

                <form method="POST" action="{{ route('orders.store') }}" class="space-y-2">
                    @csrf
                    <input type="hidden" name="plan" value="{{ $plan->slug }}">

                    @if ($renewables->isNotEmpty())
                        <select name="subscription" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="">سرویس جدید بساز</option>
                            @foreach ($renewables as $sub)
                                <option value="{{ $sub->token }}">تمدید: {{ $sub->remark }} (#{{ $sub->id }})</option>
                            @endforeach
                        </select>
                    @endif

                    <div class="grid grid-cols-2 gap-2">
                        <button name="payment_method" value="manual"
                                class="py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-sm">
                            پرداخت
                        </button>
                        <button name="payment_method" value="wallet"
                                class="py-2 rounded-lg border border-slate-300 hover:bg-slate-50 text-sm
                                       {{ auth()->user()->balance < $plan->price ? 'opacity-50' : '' }}">
                            کیف پول
                        </button>
                    </div>
                </form>
            </div>
        @endforeach
    </div>
@endsection
