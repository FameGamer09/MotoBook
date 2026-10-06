import { useEffect, useMemo } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useRiderStore } from '@/store/useRiderStore';
import { useOrderStore } from '@/store/useOrderStore';
import { useLocationTelemetry } from '@/hooks/useLocationTelemetry';
import { ActiveDeliveryBottomSheet, ActiveDeliveryMapPlaceholder } from '@/components/ActiveDelivery';
import { Button } from '@/components/ui/Button';
import { LucideIcon } from '@/components/ui/Primitive';
import { describeDeliveryProgress } from '@/lib/orderStateMachine';
import type { OrderState } from '@/types/domain';

function openMapsApp(target: { lat: number | null; lng: number | null; label: string }, kind: 'google' | 'waze' | 'apple' = 'google') {
  const ll = target.lat != null && target.lng != null ? `${target.lat},${target.lng}` : '';
  const q = encodeURIComponent(target.label || 'Destination');
  if (kind === 'google') {
    const url = ll ? `https://www.google.com/maps/dir/?api=1&destination=${ll}&query=${q}&travelmode=driving` : `https://www.google.com/maps/search/?api=1&query=${q}`;
    window.open(url, '_blank', 'noopener');
    return;
  }
  if (kind === 'waze') {
    const url = ll ? `https://www.waze.com/ul?ll=${ll}&navigate=yes` : `https://www.waze.com/livemap?search_query=${q}`;
    window.open(url, '_blank', 'noopener');
    return;
  }
  const url = ll
    ? `https://maps.apple.com/?daddr=${ll}&dirflg=d`
    : `https://maps.apple.com/?q=${q}&dirflg=d`;
  window.open(url, '_blank', 'noopener');
}

const DROPOFF_STATES: OrderState[] = ['ORDER_VERIFIED','NAVIGATING_TO_DROP_OFF','ARRIVED_AT_DROP_OFF','PROOF_SUBMITTED'];

export function ActiveDeliveryPage() {
  const rider = useRiderStore((s) => s.profile);
  const orders = useOrderStore((s) => s.list);
  const activeId = useOrderStore((s) => s.activeOrderId);
  const transition = useOrderStore((s) => s.transition);
  const loadOrders = useOrderStore((s) => s.loadList);
  const clientLocationMap = useOrderStore((s) => s.clientLocation);
  const subscribeClientLoc = useOrderStore((s) => s.subscribeClientLocation);
  const unsubscribeClientLoc = useOrderStore((s) => s.unsubscribeClientLocation);
  const nav = useNavigate();

  const order = useMemo(() => orders.find((o) => o.id === activeId) ?? orders.find((o) =>
    ['ACCEPTED','NAVIGATING_TO_PICKUP','ARRIVED_AT_PICKUP','ORDER_VERIFIED','NAVIGATING_TO_DROP_OFF','ARRIVED_AT_DROP_OFF'].includes(o.state)
    && o.rider_id === rider?.id,
  ), [orders, activeId, rider?.id]);

  const clientLocation = order ? (clientLocationMap[order.id] ?? null) : null;

  const tel = useLocationTelemetry(
    rider?.id ?? null,
    order ?? null,
    async (which) => {
      if (!order) return;
      if (which === 'pickup' && order.state === 'NAVIGATING_TO_PICKUP') await transition(order.id, 'ARRIVED_AT_PICKUP');
      if (which === 'dropoff' && order.state === 'NAVIGATING_TO_DROP_OFF') await transition(order.id, 'ARRIVED_AT_DROP_OFF');
    },
  );

  useEffect(() => {
    void loadOrders();
    const t = setInterval(() => void loadOrders(), 8_000);
    return () => clearInterval(t);
  }, [loadOrders]);

  useEffect(() => {
    if (!order) return;
    const inDropoff = DROPOFF_STATES.includes(order.state);
    if (inDropoff) subscribeClientLoc(order.id);
    return () => unsubscribeClientLoc(order.id);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [order?.id, order?.state]);

  const progress = describeDeliveryProgress(order?.state ?? 'ACCEPTED');

  const headerTitle = useMemo(() => {
    if (!order) return 'Active Delivery';
    if (order.state === 'ACCEPTED') return 'Order Details';
    if (order.state === 'NAVIGATING_TO_PICKUP' || order.state === 'ARRIVED_AT_PICKUP' || order.state === 'ORDER_VERIFIED') return 'Navigate to Pickup';
    if (order.state === 'NAVIGATING_TO_DROP_OFF' || order.state === 'ARRIVED_AT_DROP_OFF') return 'Navigate to Customer';
    return progress.label;
  }, [order, progress.label]);

  const swipeAction = async () => {
    if (!order) return;
    const next: OrderState | null =
      order.state === 'NAVIGATING_TO_PICKUP' ? 'ARRIVED_AT_PICKUP' :
      order.state === 'ARRIVED_AT_PICKUP' ? 'ORDER_VERIFIED' :
      order.state === 'ORDER_VERIFIED' ? 'NAVIGATING_TO_DROP_OFF' :
      order.state === 'NAVIGATING_TO_DROP_OFF' ? 'ARRIVED_AT_DROP_OFF' :
      order.state === 'ARRIVED_AT_DROP_OFF' ? nav('/pod', { replace: false }) as any :
      null;
    if (!next) return;
    if (typeof next === 'string' && next !== 'PROOF_SUBMITTED') {
      await transition(order.id, next as OrderState);
    }
    if (order.state === 'ARRIVED_AT_PICKUP') nav('/checklist', { replace: false });
    if (order.state === 'ARRIVED_AT_DROP_OFF') nav('/pod', { replace: false });
  };

  const onOpen = async (tgt: 'pickup' | 'dropoff') => {
    if (!order) return;
    if (tgt === 'pickup' && order.state === 'ACCEPTED') {
      await transition(order.id, 'NAVIGATING_TO_PICKUP');
    }
    if (tgt === 'dropoff' && order.state === 'ORDER_VERIFIED') {
      await transition(order.id, 'NAVIGATING_TO_DROP_OFF');
    }
    const p = tgt === 'pickup'
      ? { lat: order.merchant_lat, lng: order.merchant_lng, label: order.merchant_name }
      : { lat: order.dropoff_lat, lng: order.dropoff_lng, label: order.dropoff_address };
    openMapsApp(p, 'google');
  };

  if (!order) {
    return (
      <div className="min-h-full w-full bg-slate-950">
        <div className="phone-col px-4 py-6 text-white space-y-3">
          <div className="flex items-center gap-2 text-helper text-slate-300 font-semibold">
            <Link to="/" className="inline-flex items-center gap-1.5 min-h-[48px] min-w-[48px] h-10 px-3 rounded-xl border border-surface-border bg-surface-card">
              <LucideIcon name="ChevronLeft" size={18} /> Dashboard
            </Link>
          </div>
          <h2 className="text-screen-title font-semibold">No active delivery</h2>
          <p className="text-body text-slate-300">Deliveries assigned while you are on duty will appear here.</p>
          <Link to="/"><Button variant="primary" size="lg" className="mt-3">Return home</Button></Link>
        </div>
      </div>
    );
  }

  const isAccepted = order.state === 'ACCEPTED';

  return (
    <div className="min-h-full w-full bg-slate-950 pb-6">
      <header className="phone-top-sticky z-30 bg-slate-950/92 backdrop-blur border-b border-slate-800/70">
        <div className="phone-col px-4 h-[60px] grid grid-cols-[auto_1fr_auto] items-center gap-3">
          <Link to="/" className="min-h-[48px] min-w-[48px] h-10 w-10 rounded-2xl border border-slate-800/80 bg-surface-card inline-flex items-center justify-center text-slate-300 hover:bg-slate-800">
            <LucideIcon name="ChevronLeft" size={19} strokeWidth={2} />
          </Link>
          <div className="text-center">
            <h1 className="text-[16px] font-bold text-white tracking-tight leading-none">{headerTitle}</h1>
          </div>
          {isAccepted ? (
            <a
              href="tel:"
              className="min-h-[40px] min-w-[48px] h-10 w-10 rounded-2xl inline-flex items-center justify-center text-cyan-300 hover:text-cyan-200 hover:bg-cyan-500/10 transition"
              aria-label="Support"
            >
              <LucideIcon name="Headphones" size={18} strokeWidth={1.75} />
            </a>
          ) : (
            <button
              type="button"
              onClick={() => window.alert('Report incident: Contact dispatch via call/support.')}
              className="min-h-[40px] min-w-[48px] h-10 px-3 rounded-2xl inline-flex items-center gap-1.5 text-[13px] font-bold text-cyan-300 hover:text-cyan-200 hover:bg-cyan-500/10 transition"
              aria-label="Report"
            >
              <LucideIcon name="Flag" size={15} strokeWidth={1.75} />
              Report
            </button>
          )}
        </div>
      </header>

      <main className="phone-col space-y-3 pb-4 pt-3 lg:grid lg:grid-cols-[minmax(0,1.35fr)_minmax(360px,0.9fr)] lg:items-start lg:gap-6 lg:px-8 lg:pb-8 lg:pt-6">
        <div className="min-h-[360px] px-4 lg:min-h-[calc(100dvh-8rem)] lg:px-0">
          <ActiveDeliveryMapPlaceholder order={order} telemetry={tel} clientLocation={clientLocation} />
        </div>

        <div className="px-4 lg:sticky lg:top-6 lg:px-0">
          <ActiveDeliveryBottomSheet
            order={order}
            telemetry={tel}
            onCallMerchant={() => { if (order.merchant_phone) window.location.href = `tel:${order.merchant_phone}`; }}
            onChatCustomer={() => { alert('Chat: Not integrated — customer contact via phone below if available.'); }}
            onOpenNavigation={onOpen}
            onOpenChecklist={() => nav('/checklist')}
            onSwipeAction={swipeAction}
            openBottomSheet
          />
        </div>
      </main>
    </div>
  );
}
