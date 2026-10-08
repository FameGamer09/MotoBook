<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TransactionController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:completed,cancelled'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);
        $search = $validated['search'] ?? null;
        $status = $validated['status'] ?? null;
        $dateFrom = $validated['date_from'] ?? null;
        $dateTo = $validated['date_to'] ?? null;

        $transactions = Transaction::with(['user', 'items.product'])
            ->when($search, fn ($q) => $q->where('invoice_number', 'like', "%{$search}%"))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('created_at', '<=', $dateTo))
            ->orderByDesc('id')
            ->paginate(15);

        return view('transactions.index', compact('transactions', 'search', 'status', 'dateFrom', 'dateTo'));
    }

    public function show(Transaction $transaction): View
    {
        $transaction->load(['user', 'items.product']);

        return view('transactions.show', compact('transaction'));
    }

    public function destroy(Transaction $transaction): RedirectResponse
    {
        DB::transaction(function () use ($transaction): void {
            $lockedTransaction = Transaction::query()
                ->lockForUpdate()
                ->with('items')
                ->findOrFail($transaction->id);

            if ($lockedTransaction->status !== 'completed') {
                abort(409, 'Only completed transactions can be cancelled.');
            }

            foreach ($lockedTransaction->items->sortBy('product_id') as $item) {
                $product = Product::query()->lockForUpdate()->find($item->product_id);

                if (! $product) {
                    abort(409, 'A product in this transaction no longer exists; inventory could not be restored.');
                }

                $previousQuantity = $product->quantity;
                $product->increment('quantity', $item->quantity);
                $product->refresh();

                $product->inventoryMovements()->create([
                    'user_id' => auth()->id(),
                    'type' => 'in',
                    'quantity' => $item->quantity,
                    'previous_quantity' => $previousQuantity,
                    'new_quantity' => $product->quantity,
                    'reference' => $lockedTransaction->invoice_number,
                    'notes' => 'Transaction cancelled',
                ]);
            }

            $lockedTransaction->update(['status' => 'cancelled']);
        });

        return redirect()->route('transactions.index')->with('success', 'Transaction cancelled and inventory restored.');
    }
}
