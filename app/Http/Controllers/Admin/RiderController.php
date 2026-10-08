<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RiderController extends Controller
{
    public function index()
    {
        $riders = Rider::withoutGlobalScopes()
            ->with('user')
            ->orderBy('is_verified')
            ->orderByDesc('created_at')
            ->get();

        return view('admin.riders.index', ['riders' => $riders]);
    }

    public function show(Rider $rider)
    {
        $rider->loadMissing('user');

        return view('admin.riders.show', ['rider' => $rider]);
    }

    public function verify(Rider $rider)
    {
        DB::transaction(function () use ($rider): void {
            $rider->update(['is_verified' => true]);
            $rider->user()->where('status', 'inactive')->update(['status' => 'active']);
        });

        return back()->with('status', "{$rider->user->name} has been verified and activated.");
    }

    public function unverify(Rider $rider)
    {
        $rider->update(['is_verified' => false]);

        return back()->with('status', "{$rider->user->name} verification revoked.");
    }

    public function ban(Rider $rider)
    {
        DB::transaction(function () use ($rider): void {
            $rider->user->update(['status' => 'banned']);
            $rider->update(['status' => 'offline']);
        });

        return back()->with('status', "{$rider->user->name}'s account has been banned.");
    }

    public function activate(Rider $rider)
    {
        $rider->user->update(['status' => 'active']);

        return back()->with('status', "{$rider->user->name}'s account has been reactivated.");
    }

    public function license(Rider $rider): StreamedResponse
    {
        abort_unless($rider->license_photo && Storage::disk('local')->exists($rider->license_photo), 404);

        return Storage::disk('local')->download($rider->license_photo);
    }
}
