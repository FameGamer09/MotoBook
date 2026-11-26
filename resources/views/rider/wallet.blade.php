<x-rider.page-layout title="Wallet" active="wallet" :rider="$rider" :can-go-online="$canGoOnline" :has-active-delivery="$hasActiveDelivery">
    <section class="rider-earnings-card rider-wallet-balance">
        <div><span>Security Deposit</span><strong>₱{{ number_format($securityDeposit?->balance ?? 0, 2) }}</strong></div>
        <svg class="rider-wallet-icon" viewBox="0 0 48 48" fill="none" aria-hidden="true"><path d="M8 12.5A4.5 4.5 0 0 1 12.5 8H38a3 3 0 0 1 3 3v26a3 3 0 0 1-3 3H12.5A4.5 4.5 0 0 1 8 35.5v-23Z" fill="currentColor" opacity=".45"/><path d="M8 15h31.5a3.5 3.5 0 0 1 3.5 3.5v14a3.5 3.5 0 0 1-3.5 3.5H12a4 4 0 0 1-4-4V15Z" fill="currentColor" opacity=".55"/><path d="M32 22h12v10H32a5 5 0 0 1 0-10Z" fill="currentColor" opacity=".75"/><circle cx="34" cy="27" r="1.5" fill="#08A6D3"/></svg>
    </section>

    <div class="rider-wallet-actions" aria-label="Wallet actions">
        <details class="rider-wallet-action-detail">
            <summary class="rider-wallet-action rider-wallet-action-secondary">Cash Out</summary>
            <div class="rider-wallet-action-note" role="status">
                <strong>Cash-out unavailable</strong>
                <p>Cash-out is not set up for rider wallets yet.</p>
            </div>
        </details>
        <details class="rider-wallet-action-detail" @if ($errors->any()) open @endif>
            <summary class="rider-wallet-action rider-wallet-action-primary">Top Up</summary>
            <div class="rider-wallet-action-note rider-topup-form">
                <strong>Security deposit top-up</strong>
                <p>Submit your payment details. An admin will verify payment before your deposit is credited.</p>
                <form method="POST" action="{{ route('rider.wallet.security-top-ups.store') }}">
                    @csrf
                    <label for="security-top-up-amount">Amount (₱)</label>
                    <input id="security-top-up-amount" name="amount" type="number" min="1" max="100000" step="0.01" value="{{ old('amount') }}" required>
                    <label for="security-top-up-method">Payment method</label>
                    <select id="security-top-up-method" name="payment_method" required>
                        <option value="cash" @selected(old('payment_method') === 'cash')>Cash paid to admin</option>
                        <option value="gcash" @selected(old('payment_method') === 'gcash')>GCash</option>
                    </select>
                    <label for="security-top-up-reference">GCash reference number</label>
                    <input id="security-top-up-reference" name="payment_reference" value="{{ old('payment_reference') }}" maxlength="100" placeholder="Required for GCash">
                    @error('amount')<small class="rider-topup-error">{{ $message }}</small>@enderror
                    @error('payment_method')<small class="rider-topup-error">{{ $message }}</small>@enderror
                    @error('payment_reference')<small class="rider-topup-error">{{ $message }}</small>@enderror
                    <button class="rider-wallet-action rider-wallet-action-primary" type="submit">Send Top-up Request</button>
                </form>
            </div>
        </details>
    </div>

    <section class="rider-section rider-wallet-transactions">
        <div class="rider-section-heading"><h2>Recent Transactions</h2></div>
        <div class="rider-record-list">
            @forelse ($topUps as $topUp)
                <article class="rider-record-row"><span class="rider-record-icon">{{ $topUp->status === 'approved' ? '↓' : '₱' }}</span><div><strong>Security top-up · {{ ucfirst($topUp->payment_method) }}</strong><small>{{ $topUp->created_at->format('M j, Y · g:i A') }}@if($topUp->payment_reference) · Ref {{ $topUp->payment_reference }}@endif</small></div><strong class="rider-record-amount {{ $topUp->status === 'approved' ? 'positive' : '' }}">{{ $topUp->status === 'approved' ? '+' : '' }}₱{{ number_format($topUp->amount, 2) }}<small class="rider-transaction-status {{ $topUp->status }}">{{ ucfirst($topUp->status) }}</small></strong></article>
            @empty
                <div class="rider-empty"><strong>No top-ups yet</strong><p>Your security deposit requests will appear here.</p></div>
            @endforelse
        </div>
        {{ $topUps->links() }}
    </section>
</x-rider.page-layout>
