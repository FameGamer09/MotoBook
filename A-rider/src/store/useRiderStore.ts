import { create } from 'zustand';
import type { OrderEntity, RiderProfile } from '@/types/domain';
import { fetchJson } from '@/lib/api';

interface RiderState {
  profile: RiderProfile | null;
  loading: boolean;
  error: string | null;
  load: () => Promise<void>;
  setDuty: (online: boolean) => Promise<void>;
  patchProfile: (patch: Partial<RiderProfile>) => void;
  logout: () => Promise<void>;
  bootstrapFromServer: () => Promise<void>;
}

const bootProfile: RiderProfile | null = (() => {
  const b = window.__RIDER_BOOT__;
  if (!b) return null;
  return {
    id: b.riderId,
    rider_code: b.riderCode,
    name: b.riderName,
    email: b.riderEmail,
    phone: null,
    vehicle_plate: b.vehiclePlate,
    vehicle_type: 'MOTORCYCLE',
    city: null,
    status: b.riderStatus,
    duty_today_payout: 1450,
    completed_today: 12,
    acceptance_rate: 98,
    active_hours_today: 5.2,
    current_shift_started_at: null,
  } satisfies RiderProfile;
})();

export const useRiderStore = create<RiderState>((set, get) => ({
  profile: bootProfile,
  loading: !bootProfile,
  error: null,

  async load() {
    set({ loading: true, error: null });
    try {
      const { data, ok } = await fetchJson<{ authenticated: boolean; rider: RiderProfile }>('/session/me');
      if (ok && data?.authenticated && data.rider) {
        set({ profile: data.rider, loading: false });
      } else {
        set({ profile: null, loading: false });
      }
    } catch (e: any) {
      set({ error: e?.message ?? 'LOAD_FAILED', loading: false });
    }
  },

  async setDuty(online: boolean) {
    const { profile } = get();
    if (!profile) return;
    try {
      await fetchJson('/session/toggle-duty', {
        method: 'POST',
        body: JSON.stringify({ online }),
      });
      set((s) => {
        if (!s.profile) return {};
        return {
          profile: {
            ...s.profile,
            status: online ? 'ON_SHIFT' : 'OFFLINE',
            current_shift_started_at: online
              ? (s.profile.current_shift_started_at ?? new Date().toISOString())
              : null,
          },
        };
      });
    } catch (e: any) {
      set({ error: e?.message ?? 'DUTY_FAILED' });
    }
  },

  async bootstrapFromServer() {
    await get().load();
  },

  patchProfile(patch) {
    set((s) => s.profile ? { profile: { ...s.profile, ...patch } } : {});
  },

  async logout() {
    try {
      await fetchJson('/session/logout', { method: 'POST', body: '{}' });
    } catch { /* noop */ }
    set({ profile: null });
    const url = window.__RIDER_BOOT__?.unifiedLogoutUrl ?? '/IM-101/motobook/admin/logout.php';
    window.location.href = url;
  },
}));
