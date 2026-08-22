<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Server;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PlanController extends Controller
{
    public function index()
    {
        return view('admin.plans.index', [
            'plans' => Plan::withCount('subscriptions')->orderBy('sort')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.plans.form', [
            'plan' => new Plan(['duration_days' => 30, 'device_limit' => 2, 'is_active' => true]),
            'servers' => Server::orderBy('sort')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $plan = Plan::create($data);
        $plan->servers()->sync($request->input('servers', []));

        return redirect()->route('admin.plans.index')->with('status', 'پلن ساخته شد.');
    }

    public function edit(Plan $plan)
    {
        return view('admin.plans.form', [
            'plan' => $plan->load('servers'),
            'servers' => Server::orderBy('sort')->get(),
        ]);
    }

    public function update(Request $request, Plan $plan)
    {
        $plan->update($this->validated($request, $plan));
        $plan->servers()->sync($request->input('servers', []));

        return redirect()->route('admin.plans.index')->with('status', 'پلن به‌روزرسانی شد.');
    }

    public function destroy(Plan $plan)
    {
        $plan->delete();

        return back()->with('status', 'پلن حذف شد.');
    }

    private function validated(Request $request, ?Plan $plan = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:100', Rule::unique('plans', 'slug')->ignore($plan?->id)],
            'description' => ['nullable', 'string', 'max:1000'],
            'duration_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'traffic_gb' => ['required', 'integer', 'min:0', 'max:100000'],
            'device_limit' => ['required', 'integer', 'min:1', 'max:100'],
            'price' => ['required', 'integer', 'min:0'],
            'sort' => ['required', 'integer', 'min:0'],
            'servers' => ['array'],
            'servers.*' => ['exists:servers,id'],
        ]);

        $data['slug'] = $data['slug'] ?: Str::slug($data['name']).'-'.Str::lower(Str::random(4));
        $data['is_active'] = $request->boolean('is_active');
        $data['is_featured'] = $request->boolean('is_featured');

        unset($data['servers']);

        return $data;
    }
}
