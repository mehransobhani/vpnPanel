<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'پنل') — {{ config('panel.brand') }}</title>

    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: { extend: { fontFamily: { sans: ['Vazirmatn', 'system-ui', 'sans-serif'] } } },
        }
    </script>
    <style>
        body { font-family: Vazirmatn, system-ui, sans-serif; }
        .ltr { direction: ltr; text-align: left; }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen">

@auth
    @include('partials.nav')
@endauth

<main class="max-w-7xl mx-auto px-4 py-6">
    @include('partials.alerts')
    @yield('content')
</main>

<script>
    function copyText(text, el) {
        navigator.clipboard.writeText(text).then(() => {
            const old = el.innerText;
            el.innerText = 'کپی شد ✓';
            el.classList.add('bg-emerald-600', 'text-white');
            setTimeout(() => {
                el.innerText = old;
                el.classList.remove('bg-emerald-600', 'text-white');
            }, 1500);
        });
    }
</script>
@stack('scripts')
</body>
</html>
