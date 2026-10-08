<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;

class CustomerController extends Controller
{
    public function index()
    {
        $customers = User::where('role', 'customer')->latest()->get();

        return view('admin.customers.index', ['customers' => $customers]);
    }

    public function ban(User $customer)
    {
        abort_unless($customer->role === 'customer', 404);
        $customer->update(['status' => 'banned']);

        return back()->with('status', "{$customer->name}'s account has been banned.");
    }

    public function activate(User $customer)
    {
        abort_unless($customer->role === 'customer', 404);
        $customer->update(['status' => 'active']);

        return back()->with('status', "{$customer->name}'s account has been reactivated.");
    }
}
