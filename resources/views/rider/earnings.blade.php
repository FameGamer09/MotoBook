<x-rider.page-layout title="Earnings" active="earnings" :rider="$rider" :can-go-online="$canGoOnline" :has-active-delivery="$hasActiveDelivery">
    <section class="rider-earnings-card rider-wallet-balance">
        <div><span>{{ ucfirst($period) }} earnings</span><strong>₱{{ number_format($earnings, 2) }}</strong></div>
        <span class="rider-wallet-icon" aria-hidden="true">▤</span>
    </section>
    <nav class="rider-period-tabs" aria-label="Earnings period">
        @foreach (['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'] as $key => $label)
            <a class="{{ $period === $key ? 'active' : '' }}" href="{{ route('rider.earnings', ['period' => $key]) }}">{{ $label }}</a>
        @endforeach
    </nav>
    <section class="rider-section">
        <div class="rider-section-heading"><h2>Transaction history</h2><a href="{{ route('rider.wallet') }}">Wallet</a></div>
        <div class="rider-record-list">
            @forelse ($transactions as $transaction)
                <article class="rider-record-row"><span class="rider-record-icon">₱</span><div><strong>{{ $transaction->description ?: 'Delivery earning' }}</strong><small>{{ $transaction->created_at->format('M j, Y · g:i A') }}</small></div><strong class="rider-record-amount positive">+₱{{ number_format($transaction->amount, 2) }}</strong></article>
            @empty
                <div class="rider-empty"><strong>No earnings for this period</strong><p>Completed delivery earnings will appear here.</p></div>
            @endforelse
        </div>
        {{ $transactions->links() }}
    </section>
</x-rider.page-layout>
