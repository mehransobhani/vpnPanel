<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        if ($user = $request->user()) {
            return redirect()->route($user->isAdmin() ? 'admin.dashboard' : 'dashboard');
        }

        return view('welcome', [
            'plans' => Plan::active()->orderBy('sort')->orderBy('price')->limit(6)->get(),
        ]);
    }
}
