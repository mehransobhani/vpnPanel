<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::withCount('subscriptions')
            ->when($request->query('q'), fn ($q, $n) => $q->where('name', 'like', "%$n%")
                ->orWhere('email', 'like', "%$n%")
                ->orWhere('phone', 'like', "%$n%"))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.users.index', ['users' => $users]);
    }

    public function show(User $user)
    {
        return view('admin.users.show', [
            'user' => $user->load(['subscriptions.plan', 'orders.plan']),
            'plans' => \App\Models\Plan::active()->orderBy('sort')->get(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email,'.$user->id],
            'phone' => ['nullable', 'string', 'max:32'],
            'telegram_id' => ['nullable', 'string', 'max:64'],
            'balance' => ['required', 'integer', 'min:0'],
            'password' => ['nullable', 'confirmed', Password::min(8)],
        ]);

        if (blank($data['password'])) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
        }

        // مدیر نباید دسترسی خودش را از خودش بگیرد
        if ($user->id !== $request->user()->id) {
            $data['is_admin'] = $request->boolean('is_admin');
            $data['is_active'] = $request->boolean('is_active');
        }

        $user->update($data);

        return back()->with('status', 'کاربر به‌روزرسانی شد.');
    }
}
