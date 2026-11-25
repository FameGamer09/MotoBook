<x-rider.page-layout title="Settings" active="settings" :rider="$rider" :can-go-online="$canGoOnline" :has-active-delivery="$hasActiveDelivery">
    <section class="rider-settings-group">
        <h2>General</h2>
        <div class="rider-settings-row"><span>App language</span><strong>English <span aria-hidden="true">›</span></strong></div>
        <div class="rider-settings-row"><span>Notifications</span><button type="button" data-rider-notification-toggle>Enable alerts</button></div>
        <div class="rider-settings-row"><span>Theme</span><span class="rider-settings-theme"><span data-rider-theme-label>Light</span><x-rider.theme-toggle /></span></div>
    </section>
    <section class="rider-settings-group">
        <h2>Support</h2>
        <a class="rider-settings-row" href="{{ route('rider.profile') }}">Rider information <span aria-hidden="true">›</span></a>
        <a class="rider-settings-row" href="{{ route('rider.history') }}">Order history <span aria-hidden="true">›</span></a>
        <a class="rider-settings-row" href="{{ route('rider.wallet') }}">Wallet and payments <span aria-hidden="true">›</span></a>
        <div class="rider-settings-row"><span>Terms and Conditions</span><span aria-hidden="true">›</span></div>
        <div class="rider-settings-row"><span>Privacy Policy</span><span aria-hidden="true">›</span></div>
        <div class="rider-settings-row"><span>About MotoBook Rider</span><span aria-hidden="true">›</span></div>
    </section>
</x-rider.page-layout>
