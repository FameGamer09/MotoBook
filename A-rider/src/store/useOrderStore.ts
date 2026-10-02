import { create } from 'zustand';
import type {
  OrderEntity,
  OrderState,
  PoDPayload,
  IncidentCode,
  ClientLocationPoint,
  ClientLocationState,
} from '@/types/domain';
import {
  CLIENT_LOCATION_POLL_MS,
  CLIENT_LOCATION_SUSPEND_HIDDEN_MS,
  CLIENT_LOCATION_STALE_MS,
} from '@/types/domain';
import { canTransition } from '@/lib/orderStateMachine';
import { fetchJson } from '@/lib/api';

interface OrderStoreState {
  list: OrderEntity[];
  activeOrderId: number | null;
  loading: boolean;
  error: string | null;
  listRefreshedAt: number | null;
  clientLocation: ClientLocationState;
  clientPollers: Record<number, number>;
  clientLastPollAt: Record<number, number>;
  visibilityHiddenAtMs: number | null;
  loadList: () => Promise<void>;
  selectActive: (id: number | null) => void;
  transition: (id: number, state: OrderState) => Promise<void>;
  submitPOD: (id: number, payload: PoDPayload) => Promise<{ proof_path?: string | null; signature_path?: string | null }>;
  reportIncident: (id: number, code: IncidentCode, detail?: string) => Promise<void>;
  loadClientLocation: (orderId: number) => Promise<void>;
  subscribeClientLocation: (orderId: number) => void;
  unsubscribeClientLocation: (orderId: number) => void;
  clearClientLocation: (orderId: number) => void;
  setVisibilityHiddenAtMs: (t: number | null) => void;
}

const demoOrderBase = (): OrderEntity[] => {
  const now = Date.now();
  return [
    {
      id: 999_001,
      rider_id: null,
      order_code: 'MB-88491',
      merchant_order_id: 'JJ-7K2A',
      source_store_id: 1,
      source_system: 'INHOUSE',
      merchant_name: 'Jollibee - Uptown Mall Branch',
      merchant_phone: null,
      merchant_lat: 14.556477, merchant_lng: 121.055092,
      merchant_address: '3rd Level, Uptown Mall, 36th St., Taguig',
      dropoff_name: 'Ms. Santos',
      dropoff_lat: 14.574321, dropoff_lng: 121.083912,
      dropoff_address: 'Block 4, Lot 12, Santa Rosa Village',
      dropoff_phone: '639990000001',
      total_distance_km: 4.2,
      estimated_minutes: 18,
      payout_amount: 105,
      tip_amount: 20,
      cod_amount: 680,
      items: [
        { name: '1x Paa Large with Extra Rice', qty: 1, special_instructions: 'NO MAYO, LESS ICE', unit_price: null },
        { name: '1x Coke Zero', qty: 1, special_instructions: 'No Ice', unit_price: null },
      ],
      special_notes: 'Leave at gate if no answer within 2 minutes',
      payment_method: 'COD',
      payment_reference: null,
      payment_status: 'UNPAID',
      state: 'OFFER_RECEIVED',
      offer_received_at: new Date(now).toISOString(),
      offer_expires_at: new Date(now + 30_000).toISOString(),
      accepted_at: null, pickup_arrived_at: null, verified_at: null,
      dropoff_arrived_at: null, completed_at: null, incident_reported: null,
      created_at: new Date(now).toISOString(), updated_at: new Date(now).toISOString(),
    },
    {
      id: 999_002,
      rider_id: null,
      order_code: 'MB-88492',
      merchant_order_id: 'MI-X1P3',
      source_store_id: 2,
      source_system: 'INHOUSE',
      merchant_name: 'Mang Inasal - Market Market',
      merchant_phone: null,
      merchant_lat: 14.549122, merchant_lng: 121.049921,
      merchant_address: 'Market! Market!, McKinley Pkwy, Taguig',
      dropoff_name: 'Mr. Reyes',
      dropoff_lat: 14.559874, dropoff_lng: 121.051222,
      dropoff_address: 'Unit 1702, Avida Towers 34th',
      dropoff_phone: '639180000002',
      total_distance_km: 2.1,
      estimated_minutes: 10,
      payout_amount: 75,
      tip_amount: 0,
      cod_amount: 0,
      items: [
        { name: '2pc Chicken Inasal Unli Rice', qty: 1, special_instructions: 'Extra soy sauce' },
        { name: '1x Halo-Halo Special', qty: 1, special_instructions: 'Hold leche flan' },
      ],
      special_notes: null,
      payment_method: 'GCASH',
      payment_reference: 'G-884120912',
      payment_status: 'PENDING_VERIFICATION',
      state: 'OFFER_RECEIVED',
      offer_received_at: new Date(now + 6_000).toISOString(),
      offer_expires_at: new Date(now + 66_000).toISOString(),
      accepted_at: null, pickup_arrived_at: null, verified_at: null,
      dropoff_arrived_at: null, completed_at: null, incident_reported: null,
      created_at: new Date(now).toISOString(), updated_at: new Date(now).toISOString(),
    },
  ];
};

const clientLocTick = async (s: OrderStoreState, orderId: number): Promise<{ location: ClientLocationPoint | null; polledAt: number } | null> => {
  const hiddenAt = s.visibilityHiddenAtMs;
  if (hiddenAt != null && Date.now() - hiddenAt > CLIENT_LOCATION_SUSPEND_HIDDEN_MS) return null;
  try {
    const { data, ok } = await fetchJson<{ ok: boolean; location: ClientLocationPoint | null }>(
      `/orders/${orderId}/client-location`,
    );
    if (!ok) return null;
    return { location: data?.location ?? null, polledAt: Date.now() };
  } catch {
    return null;
  }
};

let clientVisibilityListenerInstalled = false;
const ensureVisibilityListener = () => {
  if (typeof document === 'undefined' || clientVisibilityListenerInstalled) return;
  clientVisibilityListenerInstalled = true;
  document.addEventListener('visibilitychange', () => {
    const state = useOrderStore.getState();
    if (document.hidden) {
      if (state.visibilityHiddenAtMs == null) state.setVisibilityHiddenAtMs(Date.now());
    } else {
      if (state.visibilityHiddenAtMs != null) {
        state.setVisibilityHiddenAtMs(null);
        const active = Object.keys(state.clientPollers).map(Number);
        active.forEach((id) => void state.loadClientLocation(id));
      }
    }
  });
};

export const useOrderStore = create<OrderStoreState>((set, get) => ({
  list: demoOrderBase(),
  activeOrderId: null,
  loading: false,
  error: null,
  listRefreshedAt: null,
  clientLocation: {},
  clientPollers: {},
  clientLastPollAt: {},
  visibilityHiddenAtMs: null,

  async loadList() {
    set({ loading: true, error: null });
    try {
      const { data, ok } = await fetchJson<{ orders: OrderEntity[] }>('/orders/list');
      if (ok && Array.isArray(data?.orders)) {
        set({ list: data.orders, loading: false, listRefreshedAt: Date.now() });
        return;
      }
    } catch (e: any) {
      set({ error: e?.message ?? 'ORDERS_LOAD_FAILED' });
    }
    set({ loading: false });
  },

  selectActive(id) {
    set({ activeOrderId: id });
  },

  async transition(id, state) {
    const cur = get().list.find((o) => o.id === id);
    if (cur && !canTransition(cur.state, state)) {
      set({ error: `INVALID_TRANSITION: ${cur.state} -> ${state}` });
      return;
    }
    try {
      const { data, ok } = await fetchJson<{ ok: boolean; order: OrderEntity }>(`/orders/${id}/transition`, {
        method: 'POST',
        body: JSON.stringify({ state }),
      });
      if (ok && data?.ok && data.order) {
        set((s) => ({ list: s.list.map((o) => (o.id === id ? data.order : o)) }));
      }
    } catch (e: any) {
      set({ error: e?.message ?? 'TRANSITION_FAILED' });
    }
  },

  async submitPOD(id, payload) {
    const { data, ok } = await fetchJson<{ ok: boolean; proof_path?: string | null; signature_path?: string | null }>(`/orders/${id}/pod`, {
      method: 'POST',
      body: JSON.stringify(payload),
    });
    if (ok && data?.ok) {
      set((s) => ({
        list: s.list.map((o) => (o.id === id ? { ...o, state: 'PROOF_SUBMITTED' as const } : o)),
      }));
    }
    return { proof_path: data?.proof_path ?? null, signature_path: data?.signature_path ?? null };
  },

  async reportIncident(id, code, detail) {
    await fetchJson(`/orders/${id}/incidents`, {
      method: 'POST',
      body: JSON.stringify({ code, detail }),
    });
    set((s) => ({
      list: s.list.map((o) =>
        o.id === id ? { ...o, incident_reported: [code, detail].filter(Boolean).join(' — ') } : o
      ),
    }));
  },

  async loadClientLocation(orderId) {
    ensureVisibilityListener();
    const result = await clientLocTick(get(), orderId);
    if (!result) return;
    set((s) => ({
      clientLocation: { ...s.clientLocation, [orderId]: result.location },
      clientLastPollAt: { ...s.clientLastPollAt, [orderId]: result.polledAt },
    }));
  },

  subscribeClientLocation(orderId) {
    ensureVisibilityListener();
    const existing = get().clientPollers[orderId];
    if (existing != null) {
      clearInterval(existing);
    }
    void get().loadClientLocation(orderId);
    const timer = window.setInterval(() => {
      void get().loadClientLocation(orderId);
    }, CLIENT_LOCATION_POLL_MS);
    set((s) => ({ clientPollers: { ...s.clientPollers, [orderId]: timer } }));
  },

  unsubscribeClientLocation(orderId) {
    const timer = get().clientPollers[orderId];
    if (timer != null) {
      clearInterval(timer);
      set((s) => {
        const next = { ...s.clientPollers };
        delete next[orderId];
        return { clientPollers: next };
      });
    }
  },

  clearClientLocation(orderId) {
    get().unsubscribeClientLocation(orderId);
    set((s) => {
      const loc = { ...s.clientLocation };
      delete loc[orderId];
      const poll = { ...s.clientPollers };
      delete poll[orderId];
      const last = { ...s.clientLastPollAt };
      delete last[orderId];
      return { clientLocation: loc, clientPollers: poll, clientLastPollAt: last };
    });
  },

  setVisibilityHiddenAtMs(t) {
    set({ visibilityHiddenAtMs: t });
  },
}));
