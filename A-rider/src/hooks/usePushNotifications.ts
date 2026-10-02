import { fetchJson } from '@/lib/api';

export function playOfferAlert(): void {
  if (typeof navigator !== 'undefined' && 'vibrate' in navigator) {
    try {
      navigator.vibrate([200, 100, 200, 100, 200]);
    } catch {
      // noop
    }
  }
  if (typeof window === 'undefined' || !('AudioContext' in window || 'webkitAudioContext' in window)) return;
  try {
    const Ctor: typeof AudioContext =
      (window as unknown as { AudioContext?: typeof AudioContext; webkitAudioContext?: typeof AudioContext })
        .AudioContext ||
      (window as unknown as { webkitAudioContext?: typeof AudioContext }).webkitAudioContext!;
    const ctx = new Ctor();
    const now = ctx.currentTime;
    const o1 = ctx.createOscillator();
    const g1 = ctx.createGain();
    o1.type = 'sine';
    o1.frequency.value = 880;
    g1.gain.setValueAtTime(0.0001, now);
    g1.gain.exponentialRampToValueAtTime(0.25, now + 0.01);
    g1.gain.exponentialRampToValueAtTime(0.0001, now + 0.1);
    o1.connect(g1).connect(ctx.destination);
    o1.start(now);
    o1.stop(now + 0.11);
    const o2 = ctx.createOscillator();
    const g2 = ctx.createGain();
    o2.type = 'sine';
    o2.frequency.value = 660;
    const t2 = now + 0.11;
    g2.gain.setValueAtTime(0.0001, t2);
    g2.gain.exponentialRampToValueAtTime(0.22, t2 + 0.01);
    g2.gain.exponentialRampToValueAtTime(0.0001, t2 + 0.15);
    o2.connect(g2).connect(ctx.destination);
    o2.start(t2);
    o2.stop(t2 + 0.17);
    setTimeout(() => {
      try { ctx.close(); } catch { /* noop */ }
    }, 400);
  } catch {
    // noop
  }
}

export async function registerPushToken(token: string, subscriptionJson?: unknown): Promise<{ ok: boolean; token: string | null }> {
  try {
    const { data, ok } = await fetchJson<{ ok: boolean; fcm_push_token: string | null }>('/session/push-token', {
      method: 'POST',
      body: JSON.stringify({
        token,
        pushToken: token,
        subscription_json: subscriptionJson,
        subscriptionJson,
      }),
    });
    return { ok: ok && !!data?.ok, token: data?.fcm_push_token ?? null };
  } catch {
    return { ok: false, token: null };
  }
}

export async function requestNotificationPermission(): Promise<boolean> {
  if (typeof Notification === 'undefined') return false;
  if (Notification.permission === 'granted') return true;
  if (Notification.permission === 'denied') return false;
  try {
    const result = await Notification.requestPermission();
    return result === 'granted';
  } catch {
    return false;
  }
}
