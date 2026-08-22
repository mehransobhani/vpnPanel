<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $subscriptions = $request->user()->subscriptions()
            ->with('plan')
            ->latest()
            ->get();

        return view('dashboard', [
            'subscriptions' => $subscriptions,
            'orders' => $request->user()->orders()->with('plan')->latest()->limit(5)->get(),
            'featuredPlans' => Plan::active()->where('is_featured', true)->orderBy('sort')->get(),
        ]);
    }
}
