@extends('layouts.app')

@section('title', 'Profile')

@section('content')
<div class="max-w-3xl mx-auto space-y-5">
    <div>
        <h2 class="text-lg font-semibold text-ink tracking-tight">Account Profile</h2>
        <p class="text-sm text-ink-subtle mt-1">Update your personal information, password, and account preferences.</p>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="flex items-center gap-2">
                <i data-lucide="user-circle-2" class="w-4 h-4 text-brand-700"></i>
                <h3 class="text-sm font-semibold text-ink tracking-tight">Profile Information</h3>
            </div>
        </div>
        <div class="card-body max-w-xl">
            @include('profile.partials.update-profile-information-form')
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="flex items-center gap-2">
                <i data-lucide="lock-keyhole" class="w-4 h-4 text-brand-700"></i>
                <h3 class="text-sm font-semibold text-ink tracking-tight">Update Password</h3>
            </div>
        </div>
        <div class="card-body max-w-xl">
            @include('profile.partials.update-password-form')
        </div>
    </div>

    <div class="card border-status-danger/20">
        <div class="card-header border-status-danger/20">
            <div class="flex items-center gap-2">
                <i data-lucide="trash-2" class="w-4 h-4 text-status-danger"></i>
                <h3 class="text-sm font-semibold text-status-danger tracking-tight">Delete Account</h3>
            </div>
        </div>
        <div class="card-body max-w-xl">
            @include('profile.partials.delete-user-form')
        </div>
    </div>
</div>
@endsection
