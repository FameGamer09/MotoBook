<x-rider.page-layout title="Wallet" active="wallet" :rider="$rider" :can-go-online="$canGoOnline" :has-active-delivery="$hasActiveDelivery">
    <section class="rider-earnings-card rider-wallet-balance">
        <div><span>Wallet balance</span><strong>₱{{ number_format($wallet?->balance ?? 0, 2) }}</strong></div>
        <span class="rider-wallet-icon" aria-hidden="true">▣</span>
    </section>
    <div class="rider-wallet-actions">
        <a href="{{ route('rider.earnings') }}">Earnings</a>
        <span>Cash-out and top-up are unavailable for rider wallets.</span>
    </div>
    <section class="rider-section">
        <div class="rider-section-heading"><h2>Recent transactions</h2></div>
        <div class="rider-record-list">
            @forelse ($transactions as $transaction)
                <article class="rider-record-row"><span class="rider-record-icon">{{ $transaction->type === 'credit' ? '↓' : '↑' }}</span><div><strong>{{ $transaction->description ?: ucfirst($transaction->type) }}</strong><small>{{ $transaction->created_at->format('M j, Y · g:i A') }}</small></div><strong class="rider-record-amount {{ $transaction->type === 'credit' ? 'positive' : '' }}">{{ $transaction->type === 'credit' ? '+' : '−' }}₱{{ number_format($transaction->amount, 2) }}</strong></article>
            @empty
                <div class="rider-empty"><strong>No transactions yet</strong><p>Delivery payments will appear here.</p></div>
            @endforelse
        </div>
        {{ $transactions->links() }}
    </section>
</x-rider.page-layout>
