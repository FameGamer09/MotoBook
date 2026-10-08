<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user->isCustomer()) {
            return redirect()->route('customer.restaurants');
        }

        if ($user->isMerchant()) {
            return redirect()->route($user->store ? 'merchant.dashboard' : 'merchant.onboarding');
        }

        if ($user->isRider()) {
            return redirect()->route($user->riderProfile ? 'rider.dashboard' : 'rider.onboarding');
        }

        if ($user->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        $period = $request->get('period', 'today');

        $query = Transaction::query();

        // Filter by period
        switch ($period) {
            case 'today':
                $query->whereDate('created_at', today());
                break;
            case 'week':
                $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                break;
            case 'month':
                $query->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year);
                break;
            case 'year':
                $query->whereYear('created_at', now()->year);
                break;
        }

        // Sales stats
        $salesData = (clone $query)->where('status', 'completed')
            ->selectRaw('COUNT(*) as total_transactions, SUM(total) as total_sales, AVG(total) as avg_transaction')
            ->first();

        $totalRevenue = $salesData->total_sales ?? 0;
        $totalTransactions = $salesData->total_transactions ?? 0;
        $avgTransaction = $salesData->avg_transaction ?? 0;

        // Low stock products
        $lowStockProducts = Product::with('category')
            ->whereRaw('quantity <= low_stock_threshold')
            ->where('is_active', true)
            ->limit(5)
            ->get();

        // Top selling products (from transactions)
        $topProducts = DB::table('transaction_items')
            ->join('products', 'transaction_items.product_id', '=', 'products.id')
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->where('transactions.status', 'completed')
            ->where('transactions.created_at', '>=', now()->subDays(30))
            ->selectRaw('products.id, products.name, SUM(transaction_items.quantity) as total_sold, SUM(transaction_items.subtotal) as revenue')
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get();

        // Recent transactions
        $recentTransactions = Transaction::with('user')
            ->where('status', 'completed')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        // Daily sales for chart (last 7 days)
        $dailySales = Transaction::where('status', 'completed')
            ->where('created_at', '>=', now()->subDays(7))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as transactions, SUM(total) as sales')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Category distribution
        $categoryDistribution = Category::withCount('products')
            ->withSum('products', 'quantity')
            ->get();

        return view('dashboard', compact(
            'totalRevenue',
            'totalTransactions',
            'avgTransaction',
            'lowStockProducts',
            'topProducts',
            'recentTransactions',
            'dailySales',
            'categoryDistribution',
            'period'
        ));
    }
}
