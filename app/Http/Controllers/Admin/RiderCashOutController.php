<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RiderCashOutRequest as RiderCashOut;
use App\Services\RiderCashOutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RiderCashOutController extends Controller
{
    public function index(): View
    {
        return view('admin.cash-outs.index', [
            'cashOuts' => RiderCashOut::query()->with('user', 'reviewer')->latest()->paginate(20),
        ]);
    }

    public function markPaid(RiderCashOut $cashOut, RiderCashOutService $service): RedirectResponse
    {
        $paid = $service->markPaid($cashOut, (int) auth()->id());

        return back()->with('status', $paid ? 'Cash-out marked as paid.' : 'This cash-out has already been reviewed.');
    }

    public function reject(Request $request, RiderCashOut $cashOut, RiderCashOutService $service): RedirectResponse
    {
        $validated = $request->validate(['review_note' => ['nullable', 'string', 'max:500']]);
        $rejected = $service->reject($cashOut, (int) auth()->id(), $validated['review_note'] ?? null);

        return back()->with('status', $rejected ? 'Cash-out rejected and funds returned to the rider wallet.' : 'This cash-out has already been reviewed.');
    }
}
