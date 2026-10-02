import { LucideIcon, cn, currencyPHP } from './ui/Primitive';
import { StatusDot } from './ui/StatusDot';
import { Button } from './ui/Button';
import type { ClientLocationPoint, OrderEntity } from '@/types/domain';
import { CLIENT_LOCATION_STALE_MS } from '@/types/domain';
import { describeDeliveryProgress } from '@/lib/orderStateMachine';
import type { TelemetryHandle } from '@/hooks/useLocationTelemetry';

export interface ActiveDeliveryBottomSheetProps {
  order: OrderEntity;
  telemetry: TelemetryHandle;
  onCallMerchant?: () => void;
  onChatCustomer?: () => void;
  onOpenNavigation?: (target: 'pickup' | 'dropoff') => void;
  onOpenChecklist?: () => void;
  onSwipeAction?: () => void | Promise<void>;
  openBottomSheet?: boolean;
}

export function ActiveDeliveryMapPlaceholder({
  order, telemetry, clientLocation,
}: { order: OrderEntity; telemetry: TelemetryHandle; clientLocation?: ClientLocationPoint | null }) {
  const progress = describeDeliveryProgress(order.state);
  const focus = progress.step === 1
    ? { label: order.merchant_name, lat: order.merchant_lat, lng: order.merchant_lng }
    : progress.step === 2
      ? { label: order.dropoff_name || 'Customer', lat: order.dropoff_lat, lng: order.dropoff_lng }
      : null;
  const distMeters = progress.step === 1
    ? telemetry.distanceToPickupMeters
    : progress.step === 2 ? telemetry.distanceToDropoffMeters : null;

  const showClientPin = progress.step === 2 && !!clientLocation
    && clientLocation.lat != null && clientLocation.lng != null
    && Date.now() - clientLocation.t_ms <= CLIENT_LOCATION_STALE_MS
    && clientLocation.sharing_active;
  const clientAgeMs = showClientPin ? Date.now() - (clientLocation!.t_ms || 0) : 0;
  const mapLat = showClientPin ? clientLocation!.lat : focus?.lat;
  const mapLng = showClientPin ? clientLocation!.lng : focus?.lng;
  const mapQuery = mapLat != null && mapLng != null
    ? `${mapLat},${mapLng}`
    : focus?.label || order.merchant_address;
  const mapUrl = `https://maps.google.com/maps?q=${encodeURIComponent(mapQuery)}&z=17&output=embed`;

  return (
    <div className="relative w-full h-full min-h-[320px] overflow-hidden bg-gradient-to-br from-slate-900 via-[#0c1f2a] to-[#0b1226] border border-surface-border rounded-3xl shadow-card">
      <iframe
        className="absolute inset-0 h-full w-full border-0"
        src={mapUrl}
        title={`Google Maps route to ${mapQuery}`}
        loading="lazy"
        referrerPolicy="no-referrer-when-downgrade"
        allowFullScreen
      />

      <div className="absolute top-3 left-3 right-3 flex items-center justify-between gap-2 pointer-events-none">
        <div className="min-h-[48px] min-w-[48px] inline-flex items-center gap-2 px-3.5 rounded-2xl bg-surface-dark/85 backdrop-blur-md border border-surface-border/80 shadow-card">
          <StatusDot state="in-transit" size={10} pulse />
          <div>
            <div className="text-[11px] font-semibold uppercase tracking-[0.08em] text-surface-muted">
              {progress.step ? `Step ${progress.step} of ${progress.total}` : 'Status'}
            </div>
            <div className="text-body font-semibold leading-tight text-white">{progress.label}</div>
          </div>
        </div>
        <div className="min-h-[48px] min-w-[48px] inline-flex items-center gap-3 px-3.5 rounded-2xl bg-surface-dark/85 backdrop-blur-md border border-surface-border/80 shadow-card">
          <div>
            <div className="text-[11px] font-semibold uppercase tracking-[0.08em] text-surface-muted">Distance</div>
            <div className="font-mono text-[18px] font-extrabold leading-tight text-white">
              {distMeters == null
                ? (progress.step === 1 ? `${order.total_distance_km} km` : '–')
                : `${(distMeters < 1000 ? `${distMeters.toFixed(0)} m` : `${(distMeters / 1000).toFixed(1)} km`)}`}
            </div>
          </div>
          <div className="h-10 w-px bg-slate-700/80" />
          <div>
            <div className="text-[11px] font-semibold uppercase tracking-[0.08em] text-surface-muted">Queue</div>
            <div className="font-mono text-[18px] font-extrabold leading-tight text-white">{telemetry.queueDepth}</div>
          </div>
        </div>
      </div>

      <div className="absolute bottom-3 left-3 right-3 flex items-center justify-between gap-2 pointer-events-none">
        <div className="inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-surface-dark/85 backdrop-blur border border-surface-border/80">
          <LucideIcon name="Gauge" size={16} className={cn(telemetry.motionState === 'MOVING' ? 'text-cyan-300' : 'text-surface-muted')} />
          <div className="text-[13px] font-semibold text-white">
            {telemetry.lastPoint?.speed_kmh != null ? `${telemetry.lastPoint.speed_kmh.toFixed(0)} km/h` : '—'}
          </div>
          <span className="mx-1.5 h-4 w-px bg-slate-700/80" />
          <StatusDot state={telemetry.motionState === 'MOVING' ? 'in-transit' : telemetry.motionState === 'STOPPED' ? 'warning' : 'offline'} size={9} />
          <span className="text-[13px] font-semibold capitalize text-white">{telemetry.motionState.toLowerCase()}</span>
        </div>
        <div className="inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-surface-dark/85 backdrop-blur border border-surface-border/80">
          <StatusDot state={telemetry.error ? 'offline' : 'online'} size={9} />
          <span className="text-[13px] font-semibold text-white">
            {focus?.label ? `Bound for ${focus.label.slice(0, 22)}` : 'Idle'}
          </span>
        </div>
      </div>
    </div>
  );
}

function formatDistanceMeters(meters: number | null, fallbackKm?: number): string {
  if (meters == null) {
    return fallbackKm != null && fallbackKm > 0 ? `${fallbackKm.toFixed(1)} km` : '—';
  }
  return meters < 1000 ? `${Math.round(meters)} m` : `${(meters / 1000).toFixed(1)} km`;
}

function etaFromMeters(meters: number | null, estimatedMinutes: number): number {
  if (meters == null) return Math.max(1, estimatedMinutes);
  const minutes = Math.round((meters / 1000 / 22) * 60);
  return Math.max(1, minutes || estimatedMinutes);
}

export function ActiveDeliveryBottomSheet({
  order, telemetry, onCallMerchant, onChatCustomer, onOpenNavigation, onOpenChecklist, onSwipeAction,
}: ActiveDeliveryBottomSheetProps) {
  const progress = describeDeliveryProgress(order.state);
  const target = progress.step === 1
    ? { type: 'pickup' as const, title: order.merchant_name, addr: order.merchant_address, phone: null }
    : progress.step === 2
      ? { type: 'dropoff' as const, title: order.dropoff_name || 'Customer', addr: order.dropoff_address, phone: order.dropoff_phone }
      : null;
  const isAcceptedState = order.state === 'ACCEPTED';
  const totalAmount = order.cod_amount > 0 ? order.cod_amount : order.payout_amount + order.tip_amount;
  const distMeters = progress.step === 1 ? telemetry.distanceToPickupMeters : telemetry.distanceToDropoffMeters;
  const distanceLabel = formatDistanceMeters(distMeters, progress.step === 1 ? order.total_distance_km * 0.4 : order.total_distance_km * 0.6);
  const etaMins = etaFromMeters(distMeters, Math.max(1, Math.round(order.estimated_minutes * (progress.step === 1 ? 0.45 : 0.55))));
  const arrivedCta =
    order.state === 'ARRIVED_AT_PICKUP' ? 'Verify Pickup' :
    order.state === 'ORDER_VERIFIED' ? 'Navigate to Customer' :
    order.state === 'ARRIVED_AT_DROP_OFF' ? 'Confirm Delivery' :
    "I've Arrived";

  return (
    <div className="rounded-t-[28px] border-t border-x border-slate-800/80 bg-surface-card shadow-[0_-20px_60px_-24px_rgba(0,0,0,0.85)]">
      <div className="w-14 h-1.5 bg-slate-700/80 rounded-full mx-auto mt-3" role="presentation" />
      <div className="px-5 pt-4 pb-5 space-y-4">
        {isAcceptedState ? (
          <>
            <div className="flex items-center justify-between gap-3">
              <div className="inline-flex items-center gap-2 rounded-xl border border-slate-700/80 bg-slate-800/70 px-3 py-1.5">
                <span className="text-[10px] font-bold uppercase tracking-[0.1em] text-cyan-300">Order ID</span>
                <span className="font-mono text-[13px] font-extrabold text-white">{order.order_code}</span>
              </div>
              <div className={cn(
                'inline-flex items-center gap-1.5 rounded-xl border px-3 py-1.5',
                order.payment_method === 'COD'
                  ? 'border-cyan-700/40 bg-cyan-500/5 text-cyan-300'
                  : 'border-slate-700/80 bg-slate-800/70 text-slate-200',
              )}>
                <LucideIcon name={order.payment_method === 'COD' ? 'Banknote' : 'Smartphone'} size={13} />
                <span className="text-[11px] font-bold uppercase tracking-wide">{order.payment_method}</span>
              </div>
            </div>

            <div className="rounded-2xl border border-slate-800/80 bg-surface-panel/70 divide-y divide-slate-800/70">
              <div className="px-4 py-3.5 grid grid-cols-[auto_1fr_auto] items-center gap-3">
                <div className="h-9 w-9 rounded-xl bg-primary/15 border border-primary/30 text-primary inline-flex items-center justify-center flex-shrink-0">
                  <LucideIcon name="MapPin" size={17} />
                </div>
                <div className="min-w-0">
                  <div className="text-[10px] font-bold uppercase tracking-[0.12em] text-cyan-300">Pick Up From</div>
                  <div className="text-[14px] font-bold text-white mt-0.5 truncate">{order.merchant_name}</div>
                  <div className="text-[12px] font-medium text-slate-400 truncate">{order.merchant_address}</div>
                </div>
                <Button
                  variant="slate"
                  size="sm"
                  onClick={onCallMerchant}
                  iconLeft={<LucideIcon name="Phone" size={14} />}
                  className="h-9 px-2.5 rounded-xl border-slate-700/80 bg-surface-card text-cyan-300 hover:text-cyan-200"
                >
                  Call Store
                </Button>
              </div>
              <div className="px-4 py-3.5 grid grid-cols-[auto_1fr_auto] items-center gap-3">
                <div className="h-9 w-9 rounded-xl bg-slate-800 border border-slate-700/80 text-slate-200 inline-flex items-center justify-center flex-shrink-0">
                  <LucideIcon name="Navigation" size={17} />
                </div>
                <div className="min-w-0">
                  <div className="text-[10px] font-bold uppercase tracking-[0.12em] text-slate-400">Drop Off To</div>
                  <div className="text-[14px] font-bold text-white mt-0.5 truncate">{order.dropoff_name || 'Customer'}</div>
                  <div className="text-[12px] font-medium text-slate-400 truncate">{order.dropoff_address}</div>
                </div>
                <button
                  type="button"
                  onClick={() => onOpenNavigation?.('dropoff')}
                  className="h-9 w-9 min-h-[36px] min-w-[36px] rounded-xl border border-slate-700/80 bg-surface-card inline-flex items-center justify-center text-slate-300 hover:text-white hover:bg-slate-700/80 transition"
                  aria-label="Navigate"
                >
                  <LucideIcon name="Navigation2" size={16} />
                </button>
              </div>
            </div>

            {order.items && order.items.length ? (
              <div className="rounded-2xl border border-slate-800/80 bg-surface-panel/70 overflow-hidden">
                <div className="px-4 pt-3 pb-1.5 flex items-center justify-between">
                  <span className="text-[10px] font-bold uppercase tracking-[0.12em] text-slate-400">Order Items</span>
                </div>
                <div className="px-2 pb-2 space-y-0.5">
                  {order.items.slice(0, 5).map((it, idx) => (
                    <div key={idx} className="grid grid-cols-[auto_1fr_auto] gap-3 items-center px-2.5 py-2 rounded-xl hover:bg-slate-800/50">
                      <div className="font-mono text-[12px] font-extrabold text-cyan-300 w-6">{it.qty ?? 1}×</div>
                      <div className="text-[13px] font-semibold text-white truncate">{it.name}</div>
                      <div className="font-mono text-[12px] font-bold text-slate-300">
                        {it.unit_price ? currencyPHP(it.unit_price) : '—'}
                      </div>
                    </div>
                  ))}
                </div>
                <div className="mx-3 mb-3 mt-1 rounded-xl bg-slate-900/70 border border-slate-800/80 px-3.5 py-2.5 flex items-center justify-between">
                  <span className="text-[11px] font-bold uppercase tracking-[0.1em] text-slate-400">Total Amount</span>
                  <span className="font-mono text-[16px] font-extrabold text-white">{currencyPHP(totalAmount)}</span>
                </div>
              </div>
            ) : null}

            {order.special_notes ? (
              <div className="rounded-2xl border border-cyan-700/30 bg-slate-900/70 px-4 py-3">
                <div className="flex items-center gap-2 mb-1">
                  <LucideIcon name="MessageSquareText" size={14} className="text-cyan-300" />
                  <span className="text-[10px] font-bold uppercase tracking-[0.12em] text-cyan-300">Customer Note</span>
                </div>
                <div className="text-[13px] font-semibold text-cyan-100 leading-snug">“{order.special_notes}”</div>
              </div>
            ) : null}

            <Button
              variant="primary"
              size="block"
              onClick={() => onOpenNavigation?.('pickup')}
              className="h-14 rounded-2xl text-[15px] font-bold bg-primary hover:bg-primary-pressed shadow-[0_12px_30px_-10px_rgba(6,182,212,0.5)]"
              iconLeft={<LucideIcon name="Navigation" size={19} />}
            >
              Navigate to Pickup
            </Button>
          </>
        ) : (
          <>
            {progress.step === 2 ? (
              <div className="grid grid-cols-2 gap-2">
                <Button
                  variant="slate"
                  size="md"
                  onClick={() => { if (target?.phone) window.location.href = `tel:${target.phone}`; else onChatCustomer?.(); }}
                  iconLeft={<LucideIcon name="Phone" size={16} />}
                  className="h-[52px] rounded-2xl border-slate-700/80 bg-surface-card"
                >
                  Call
                </Button>
                <Button
                  variant="slate"
                  size="md"
                  onClick={onChatCustomer}
                  iconLeft={<LucideIcon name="MessageSquare" size={16} />}
                  className="h-[52px] rounded-2xl border-slate-700/80 bg-surface-card"
                >
                  Chat
                </Button>
              </div>
            ) : (
              <div className="grid grid-cols-2 gap-2">
                <Button
                  variant="slate"
                  size="md"
                  onClick={onCallMerchant}
                  iconLeft={<LucideIcon name="Phone" size={16} />}
                  className="h-[52px] rounded-2xl border-slate-700/80 bg-surface-card"
                >
                  Call Store
                </Button>
                <Button
                  variant="primary"
                  size="md"
                  onClick={() => onOpenNavigation?.(target?.type === 'dropoff' ? 'dropoff' : 'pickup')}
                  iconLeft={<LucideIcon name="Navigation" size={16} />}
                  className="h-[52px] rounded-2xl"
                >
                  Open Map
                </Button>
              </div>
            )}

            <div className="rounded-2xl border border-slate-800/80 bg-surface-panel/70 px-4 py-3.5">
              <div className="grid grid-cols-[auto_1fr] gap-3">
                <div className="mt-0.5 h-9 w-9 rounded-xl bg-primary/15 border border-primary/30 text-primary inline-flex items-center justify-center">
                  <LucideIcon name={progress.step === 1 ? 'Store' : 'Package'} size={17} />
                </div>
                <div className="min-w-0">
                  <div className="text-[10px] font-bold uppercase tracking-[0.12em] text-cyan-300">
                    {progress.step === 1 ? 'Pick Up From' : 'Deliver To'}
                  </div>
                  <div className="text-[14px] font-bold text-white mt-0.5 truncate">
                    {progress.step === 1 ? order.merchant_name : (order.dropoff_name || 'Customer')}
                  </div>
                  <div className="text-[12px] font-medium text-slate-400 mt-0.5 truncate">
                    {progress.step === 1 ? order.merchant_address : order.dropoff_address}
                  </div>
                </div>
              </div>
              <div className="mt-3 grid grid-cols-2 gap-2">
                <div className="rounded-xl border border-slate-800/80 bg-slate-900/70 px-3 py-2.5">
                  <div className="text-[10px] font-bold uppercase tracking-[0.1em] text-slate-500">Distance</div>
                  <div className="mt-1 font-mono text-[16px] font-extrabold text-white leading-none">{distanceLabel}</div>
                  <div className="text-[11px] font-semibold text-slate-500 mt-1">away</div>
                </div>
                <div className="rounded-xl border border-slate-800/80 bg-slate-900/70 px-3 py-2.5">
                  <div className="text-[10px] font-bold uppercase tracking-[0.1em] text-slate-500">ETA</div>
                  <div className="mt-1 font-mono text-[16px] font-extrabold text-white leading-none">{etaMins}</div>
                  <div className="text-[11px] font-semibold text-slate-500 mt-1">mins</div>
                </div>
              </div>
            </div>

            {progress.step === 1 && order.items.length ? (
              <div className="grid grid-cols-[1fr_auto] gap-3 items-center rounded-2xl border border-slate-800/80 bg-surface-panel/70 px-4 py-3">
                <div>
                  <div className="text-[10px] font-bold uppercase tracking-[0.1em] text-slate-400">Items to verify</div>
                  <div className="text-[13px] font-bold text-white mt-0.5">
                    {order.items.reduce((a, b) => a + (b.qty || 0), 0)} items · {order.items.length} line
                    {order.merchant_order_id ? (
                      <span className="ml-2 inline-flex items-center rounded-md bg-white text-slate-950 px-1.5 py-0.5 font-mono text-[11px] font-extrabold">
                        {order.merchant_order_id}
                      </span>
                    ) : null}
                  </div>
                </div>
                <Button
                  variant="primary"
                  size="md"
                  onClick={onOpenChecklist}
                  iconLeft={<LucideIcon name="ClipboardCheck" size={15} />}
                  className="h-11 rounded-xl px-3 text-[13px] font-bold"
                >
                  Verify
                </Button>
              </div>
            ) : null}

            {onSwipeAction ? (
              <Button
                variant="primary"
                size="block"
                onClick={() => {
                  if (order.state === 'ORDER_VERIFIED') {
                    onOpenNavigation?.('dropoff');
                    return;
                  }
                  if (order.state === 'ARRIVED_AT_PICKUP') {
                    onOpenChecklist?.();
                    return;
                  }
                  void onSwipeAction();
                }}
                className="h-14 rounded-2xl text-[15px] font-bold bg-primary hover:bg-primary-pressed shadow-[0_12px_30px_-10px_rgba(6,182,212,0.5)]"
                iconLeft={<LucideIcon name={order.state === 'ORDER_VERIFIED' ? 'Navigation' : 'MapPin'} size={18} />}
              >
                {arrivedCta}
              </Button>
            ) : null}
          </>
        )}
      </div>
    </div>
  );
}
