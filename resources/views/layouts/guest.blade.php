<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') — {{ config('panel.brand') }}</title>
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>body { font-family: Vazirmatn, system-ui, sans-serif; }</style>
</head>
<body class="bg-gradient-to-br from-slate-900 to-indigo-950 min-h-screen flex items-center justify-center px-4 py-10">
    <div class="w-full max-w-md">
        <h1 class="text-center text-2xl font-bold text-white mb-6">{{ config('panel.brand') }}</h1>
        <div class="bg-white rounded-2xl shadow-2xl p-6">
            @include('partials.alerts')
            @yield('content')
        </div>
        <p class="text-center text-xs text-slate-400 mt-4">
            <a href="{{ route('home') }}" class="hover:text-white">بازگشت به صفحهٔ اصلی</a>
        </p>
    </div>
</body>
</html>
