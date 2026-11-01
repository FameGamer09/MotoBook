const offerSection = document.querySelector('[data-rider-offers]');

if (offerSection) {
    const alertButton = offerSection.querySelector('[data-enable-order-notifications]');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    const offerUrl = offerSection.dataset.offersUrl;
    const seenOffers = new Map(
        [...offerSection.querySelectorAll('[data-offer-id]')].map((card) => [
            Number(card.dataset.offerId),
            `${card.dataset.offerStatus}:${card.dataset.offerCanAccept}`,
        ]),
    );

    const updateAlertButton = () => {
        if (!alertButton || !('Notification' in window)) return;

        alertButton.hidden = Notification.permission === 'granted' || Notification.permission === 'denied';
        if (Notification.permission === 'denied') alertButton.textContent = 'Alerts blocked in browser';
    };

    updateAlertButton();
    alertButton?.addEventListener('click', async () => {
        if (!('Notification' in window)) return;

        await Notification.requestPermission();
        updateAlertButton();
    });

    const notifyRider = (title, body, tag) => {
        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification(title, { body, tag, icon: '/images/825310998_1610647237377200_4391978218746131377_n.png' });
        }
    };

    let checkingOffers = false;
    const checkOffers = async () => {
        if (checkingOffers || !offerUrl) return;

        checkingOffers = true;
        try {
            const response = await fetch(offerUrl, {
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken ?? '' },
                cache: 'no-store',
            });

            if (!response.ok) return;

            const payload = await response.json();
            const offers = payload.offers ?? [];
            let hasChanged = offers.length !== seenOffers.size;

            for (const offer of offers) {
                const key = `${offer.status}:${offer.can_accept ? 1 : 0}`;
                const previousKey = seenOffers.get(offer.id);

                if (previousKey === undefined) {
                    notifyRider('New MotoBook delivery', `${offer.store} · Order ${offer.order_number} · Queue #${offer.queue_position}`, `delivery-${offer.delivery_id}`);
                    hasChanged = true;
                } else if (previousKey !== key) {
                    if (offer.can_accept) {
                        notifyRider('Your turn to accept', `${offer.store} · Order ${offer.order_number}`, `delivery-${offer.delivery_id}`);
                    }
                    hasChanged = true;
                }

                seenOffers.set(offer.id, key);
            }

            if (hasChanged) window.location.reload();
        } catch {
            // Keep the current dashboard usable; the next poll retries automatically.
        } finally {
            checkingOffers = false;
        }
    };

    const interval = window.setInterval(checkOffers, 5000);
    window.addEventListener('pagehide', () => window.clearInterval(interval), { once: true });
}
