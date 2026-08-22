<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('panel.brand') }}</title>
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>body { font-family: Vazirmatn, system-ui, sans-serif; }</style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen">
    <div class="max-w-5xl mx-auto px-4 py-16">
        <header class="flex items-center justify-between mb-16">
            <span class="text-xl font-bold text-indigo-400">{{ config('panel.brand') }}</span>
            <nav class="flex gap-3 text-sm">
                <a href="{{ route('login') }}" class="px-4 py-2 rounded-lg border border-slate-700 hover:bg-slate-800">ورود</a>
                <a href="{{ route('register') }}" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500">ثبت‌نام</a>
            </nav>
        </header>

        <section class="text-center mb-16">
            <h1 class="text-4xl sm:text-5xl font-bold mb-4 leading-tight">اتصال پرسرعت و پایدار</h1>
            <p class="text-slate-400 mb-8">پلن مورد نظرت رو انتخاب کن، کانفیگ در کمتر از یک دقیقه تحویل داده می‌شه.</p>
            <a href="{{ route('register') }}" class="inline-block px-8 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-500 font-medium">شروع کن</a>
        </section>

        @if ($plans->isNotEmpty())
            <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($plans as $plan)
                    <div class="rounded-2xl border p-6 {{ $plan->is_featured ? 'border-indigo-500 bg-indigo-950/40' : 'border-slate-800 bg-slate-900' }}">
                        <h3 class="font-bold text-lg mb-1">{{ $plan->name }}</h3>
                        <p class="text-sm text-slate-400 mb-4 min-h-[2.5rem]">{{ $plan->description }}</p>
                        <ul class="text-sm space-y-1.5 text-slate-300 mb-5">
                            <li>⏳ {{ $plan->duration_days }} روز</li>
                            <li>📊 {{ $plan->traffic_gb ? $plan->traffic_gb.' گیگابایت' : 'ترافیک نامحدود' }}</li>
                            <li>📱 {{ $plan->device_limit }} دستگاه هم‌زمان</li>
                        </ul>
                        <div class="text-2xl font-bold text-indigo-400 mb-4">{{ \App\Support\Format::money($plan->price) }}</div>
                        <a href="{{ route('register') }}" class="block text-center py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-sm">خرید</a>
                    </div>
                @endforeach
            </section>
        @endif

        @if (config('panel.support.telegram'))
            <p class="text-center text-sm text-slate-500 mt-12">
                پشتیبانی: <a class="text-indigo-400" href="https://t.me/{{ ltrim(config('panel.support.telegram'), '@') }}">{{ config('panel.support.telegram') }}</a>
            </p>
        @endif
    </div>
</body>
</html>
