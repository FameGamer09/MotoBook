<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRiderCashOutRequest as StoreCashOutRequest;
use App\Services\RiderCashOutService;
use Illuminate\Http\RedirectResponse;

class RiderCashOutController extends Controller
{
    public function store(StoreCashOutRequest $request, RiderCashOutService $service): RedirectResponse
    {
        $service->request($request->user(), $request->validated());

        return to_route('rider.wallet')->with('status', 'Cash-out requested. The admin will send it to your GCash account after review.');
    }
}
