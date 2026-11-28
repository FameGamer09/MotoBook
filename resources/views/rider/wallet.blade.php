<x-rider.page-layout title="Wallet" active="wallet" :rider="$rider" :can-go-online="$canGoOnline" :has-active-delivery="$hasActiveDelivery">
    <section class="rider-earnings-card rider-wallet-balance">
        <div><span>Available earnings</span><strong>₱{{ number_format($earningsWallet?->balance ?? 0, 2) }}</strong></div>
        <svg class="rider-wallet-icon" viewBox="0 0 48 48" fill="none" aria-hidden="true"><path d="M8 12.5A4.5 4.5 0 0 1 12.5 8H38a3 3 0 0 1 3 3v26a3 3 0 0 1-3 3H12.5A4.5 4.5 0 0 1 8 35.5v-23Z" fill="currentColor" opacity=".45"/><path d="M8 15h31.5a3.5 3.5 0 0 1 3.5 3.5v14a3.5 3.5 0 0 1-3.5 3.5H12a4 4 0 0 1-4-4V15Z" fill="currentColor" opacity=".55"/><path d="M32 22h12v10H32a5 5 0 0 1 0-10Z" fill="currentColor" opacity=".75"/><circle cx="34" cy="27" r="1.5" fill="#08A6D3"/></svg>
    </section>
    <section class="rider-section"><div class="rider-section-heading"><h2>Security Deposit</h2></div><p>₱{{ number_format($securityDeposit?->balance ?? 0, 2) }} · Deposit funds cannot be cashed out.</p></section>

    <div class="rider-wallet-actions" aria-label="Wallet actions">
        <details class="rider-wallet-action-detail">
            <summary class="rider-wallet-action rider-wallet-action-secondary">Cash Out</summary>
            <div class="rider-wallet-action-note rider-topup-form">
                <strong>Cash out earnings to GCash</strong>
                <p>Your requested amount is reserved immediately. Admin will send the payout manually. Rejected requests are refunded.</p>
                <form method="POST" action="{{ route('rider.wallet.cash-outs.store') }}">
                    @csrf
                    <label for="cash-out-amount">Amount (₱)</label>
                    <input id="cash-out-amount" name="amount" type="number" min="1" max="{{ $earningsWallet?->balance ?? 0 }}" step="0.01" value="{{ old('amount') }}" required>
                    <label for="cash-out-account-name">GCash account name</label>
                    <input id="cash-out-account-name" name="account_name" value="{{ old('account_name', $rider->user->name) }}" maxlength="120" required>
                    <label for="cash-out-account-number">GCash mobile number</label>
                    <input id="cash-out-account-number" name="account_number" type="tel" inputmode="tel" autocomplete="tel" placeholder="09XXXXXXXXX" value="{{ old('account_number') }}" required>
                    @foreach (['amount', 'account_name', 'account_number'] as $field)
                        @error($field)<small class="rider-topup-error">{{ $message }}</small>@enderror
                    @endforeach
                    <button class="rider-wallet-action rider-wallet-action-primary" type="submit">Request Cash Out</button>
                </form>
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
        <div class="rider-section-heading"><h2>Cash-out requests</h2></div>
        <div class="rider-record-list">
            @forelse ($cashOutRequests as $cashOut)
                <article class="rider-record-row"><span class="rider-record-icon">₱</span><div><strong>GCash · {{ $cashOut->account_number }}</strong><small>{{ $cashOut->created_at->format('M j, Y · g:i A') }}</small></div><strong class="rider-record-amount">−₱{{ number_format($cashOut->amount, 2) }}<small class="rider-transaction-status {{ $cashOut->status }}">{{ ucfirst($cashOut->status) }}</small></strong></article>
            @empty
                <div class="rider-empty"><strong>No cash-out requests yet</strong><p>Your requests will appear here.</p></div>
            @endforelse
        </div>
        {{ $cashOutRequests->links() }}
    </section>

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
