<x-rider.page-layout title="Order History" active="orders" :rider="$rider" :can-go-online="$canGoOnline" :has-active-delivery="$hasActiveDelivery">
    <section class="rider-section">
        <div class="rider-section-heading"><h2>Restaurant deliveries</h2></div>
        <div class="rider-record-list">
            @forelse ($deliveries as $delivery)
                <a class="rider-record-row rider-record-link" href="{{ route('rider.orders.show', $delivery) }}">
                    <span class="rider-record-icon">▣</span>
                    <span><strong>{{ $delivery->order->store->name }}</strong><small>{{ $delivery->order->order_number }} · {{ $delivery->delivered_at?->format('M j, Y · g:i A') ?? ucfirst($delivery->status) }}</small></span>
                    <strong class="rider-record-amount">₱{{ number_format($delivery->total_earning, 2) }}</strong>
                </a>
            @empty
                <div class="rider-empty"><strong>No completed deliveries yet</strong><p>Finished orders will show in your history.</p></div>
            @endforelse
        </div>
        {{ $deliveries->links() }}
    </section>
    <section class="rider-section">
        <div class="rider-section-heading"><h2>Custom deliveries</h2></div>
        <div class="rider-record-list">
            @forelse ($customDeliveries as $job)
                <article class="rider-record-row"><span class="rider-record-icon">⌖</span><div><strong>Custom delivery #{{ $job->id }}</strong><small>{{ $job->delivered_at?->format('M j, Y · g:i A') ?? ucfirst($job->status) }} · {{ $job->dropoff_address }}</small></div><strong class="rider-record-amount">₱{{ number_format($job->delivery_fee, 2) }}</strong></article>
            @empty
                <div class="rider-empty"><strong>No custom deliveries yet</strong></div>
            @endforelse
        </div>
        {{ $customDeliveries->links() }}
    </section>
</x-rider.page-layout>
