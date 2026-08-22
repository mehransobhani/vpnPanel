@extends('layouts.app')
@section('title', 'کاربران')

@section('content')
    <h1 class="text-xl font-bold mb-5">کاربران</h1>

    <form class="flex gap-2 mb-4">
        <input name="q" value="{{ request('q') }}" placeholder="نام، ایمیل یا شماره تماس"
               class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <button class="px-4 py-2 rounded-lg bg-slate-800 text-white text-sm">جستجو</button>
    </form>

    <div class="bg-white rounded-2xl border border-slate-200 overflow-x-auto">
        <table class="w-full text-sm min-w-[700px]">
            <thead class="bg-slate-50 text-slate-500 text-xs">
                <tr>
                    <th class="text-right px-4 py-3">نام</th>
                    <th class="text-right px-4 py-3">ایمیل</th>
                    <th class="text-right px-4 py-3">تماس</th>
                    <th class="text-right px-4 py-3">سرویس</th>
                    <th class="text-right px-4 py-3">کیف پول</th>
                    <th class="text-right px-4 py-3">عضویت</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($users as $user)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.users.show', $user) }}" class="font-medium text-indigo-600 hover:underline">
                                {{ $user->name }}
                            </a>
                            @if ($user->is_admin)
                                <span class="text-[11px] px-1.5 py-0.5 rounded bg-violet-100 text-violet-700">مدیر</span>
                            @endif
                            @unless ($user->is_active)
                                <span class="text-[11px] px-1.5 py-0.5 rounded bg-rose-100 text-rose-700">مسدود</span>
                            @endunless
                        </td>
                        <td class="px-4 py-3 ltr text-xs">{{ $user->email }}</td>
                        <td class="px-4 py-3 ltr text-xs">{{ $user->phone ?: '—' }}</td>
                        <td class="px-4 py-3">{{ $user->subscriptions_count }}</td>
                        <td class="px-4 py-3 text-xs">{{ \App\Support\Format::money($user->balance) }}</td>
                        <td class="px-4 py-3 text-xs text-slate-500">{{ \App\Support\Format::jalali($user->created_at) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-slate-500">کاربری یافت نشد.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $users->links() }}</div>
@endsection
