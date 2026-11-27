<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RiderSecurityTopUp;
use App\Services\RiderSecurityTopUpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RiderSecurityTopUpController extends Controller
{
    public function index(): View
    {
        return view('admin.security-top-ups.index', [
            'topUps' => RiderSecurityTopUp::query()
                ->with('user', 'reviewer')
                ->latest()
                ->paginate(20),
        ]);
    }

    public function approve(RiderSecurityTopUp $topUp, RiderSecurityTopUpService $service): RedirectResponse
    {
        $approved = $service->approve($topUp, (int) auth()->id());

        return back()->with('status', $approved ? 'Top-up approved and credited to the rider security deposit.' : 'This top-up has already been reviewed.');
    }

    public function reject(RiderSecurityTopUp $topUp, RiderSecurityTopUpService $service): RedirectResponse
    {
        $rejected = $service->reject($topUp, (int) auth()->id());

        return back()->with('status', $rejected ? 'Top-up request rejected.' : 'This top-up has already been reviewed.');
    }
}
