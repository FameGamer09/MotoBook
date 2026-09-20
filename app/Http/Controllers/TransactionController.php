<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TransactionController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->get('search');
        $status = $request->get('status');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        $transactions = Transaction::with(['user', 'items.product'])
            ->when($search, fn($q) => $q->where('invoice_number', 'like', "%{$search}%"))
            ->when($status, fn($q) => $q->where('status', $status))
            ->when($dateFrom, fn($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('created_at', '<=', $dateTo))
            ->orderByDesc('id')
            ->paginate(15);

        return view('transactions.index', compact('transactions', 'search', 'status', 'dateFrom', 'dateTo'));
    }

    public function show(Transaction $transaction): View
    {
        $transaction->load(['user', 'items.product']);
        return view('transactions.show', compact('transaction'));
    }

    public function destroy(Transaction $transaction)
    {
        // Restore inventory
        foreach ($transaction->items as $item) {
            $product = $item->product;
            $product->quantity += $item->quantity;
            $product->save();

            $product->inventoryMovements()->create([
                'user_id' => auth()->id(),
                'type' => 'in',
                'quantity' => $item->quantity,
                'previous_quantity' => $product->quantity - $item->quantity,
                'new_quantity' => $product->quantity,
                'reference' => $transaction->invoice_number,
                'notes' => 'Transaction cancelled',
            ]);
        }

        $transaction->update(['status' => 'cancelled']);

        return redirect()->route('transactions.index')->with('success', 'Transaction cancelled and inventory restored.');
    }
}