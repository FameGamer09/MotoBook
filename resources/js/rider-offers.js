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
        if (!alertButton) return;

        if (!('Notification' in window)) {
            alertButton.hidden = false;
            alertButton.disabled = true;
            alertButton.textContent = 'Notifications unavailable';

            return;
        }

        alertButton.hidden = Notification.permission === 'granted';
        alertButton.disabled = Notification.permission === 'denied';
        if (Notification.permission === 'denied') alertButton.textContent = 'Alerts blocked in browser';
    };

    updateAlertButton();
    alertButton?.addEventListener('click', async () => {
        if (!('Notification' in window)) return;

        try {
            await Notification.requestPermission();
            updateAlertButton();
        } catch (error) {
            console.error('Unable to request notification permission.', error);
            alertButton.textContent = 'Unable to enable alerts';
        }
    });

    const notifyRider = (title, body, tag) => {
        if ('Notification' in window && Notification.permission === 'granted') {
            const notification = new Notification(title, {
                body,
                tag,
                icon: '/images/825310998_1610647237377200_4391978218746131377_n.png',
            });
            notification.addEventListener('click', () => {
                window.focus();
                window.location.href = '/rider/requests';
            }, { once: true });
        }
    };

    const updateQueueCountdowns = () => {
        offerSection.querySelectorAll('[data-rider-countdown]').forEach((countdown) => {
            const remainingSeconds = Math.max(0, Number(countdown.dataset.riderCountdown) - 1);
            countdown.dataset.riderCountdown = String(remainingSeconds);

            if (countdown.dataset.riderCountdownMode === 'accept') {
                countdown.textContent = `Accept within ${remainingSeconds} seconds.`;

                return;
            }

            const minutes = Math.floor(remainingSeconds / 60);
            const seconds = remainingSeconds % 60;
            countdown.textContent = `Your turn is expected in about ${minutes} min ${seconds} sec.`;
        });
    };

    const countdownInterval = window.setInterval(updateQueueCountdowns, 1000);
    window.addEventListener('pagehide', () => window.clearInterval(countdownInterval), { once: true });

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
