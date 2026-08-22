@extends('layouts.app')
@section('title', 'سرویس‌های من')

@section('content')
    <div class="flex items-center justify-between mb-5">
        <h1 class="text-xl font-bold">سرویس‌های من</h1>
        <a href="{{ route('plans.index') }}" class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm hover:bg-indigo-500">
            خرید سرویس
        </a>
    </div>

    @if ($subscriptions->isEmpty())
        <div class="bg-white rounded-2xl border border-dashed border-slate-300 p-10 text-center text-slate-500">
            سرویسی ثبت نشده است.
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($subscriptions as $subscription)
                <x-sub-card :subscription="$subscription" />
            @endforeach
        </div>

        <div class="mt-6">{{ $subscriptions->links() }}</div>
    @endif
@endsection
