<?php

namespace App\Http\Controllers;

class PlanController extends Controller
{
    public function index()
    {
        return view('plans.index', [
            'plans' => \App\Models\Plan::active()->orderBy('sort')->orderBy('price')->get(),
        ]);
    }
}
