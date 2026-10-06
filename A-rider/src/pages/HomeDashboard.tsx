import { useMemo, useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useRiderStore } from '@/store/useRiderStore';
import { useOrderStore } from '@/store/useOrderStore';
import { useLocationTelemetry } from '@/hooks/useLocationTelemetry';
import { Button } from '@/components/ui/Button';
import { StatusDot } from '@/components/ui/StatusDot';
import { LucideIcon, cn, currencyPHP } from '@/components/ui/Primitive';

const RIDER_AVATAR = 'data:image/svg+xml;utf8,' + encodeURIComponent(
  `<svg xmlns='http://www.w3.org/2000/svg' width='128' height='128' viewBox='0 0 128 128'>
    <defs>
      <linearGradient id='hg' x1='0' x2='1' y1='0' y2='1'>
        <stop offset='0%' stop-color='#06B6D4'/>
        <stop offset='100%' stop-color='#0E7490'/>
      </linearGradient>
    </defs>
    <rect width='128' height='128' rx='64' fill='url(#hg)'/>
    <circle cx='64' cy='52' r='22' fill='white' opacity='0.95'/>
    <path d='M20 116 C26 88 94 88 108 116 Z' fill='white' opacity='0.95'/>
  </svg>`,
);

function formatHours(totalHours: number): string {
  const h = Math.floor(totalHours);
  const m = Math.round((totalHours - h) * 60);
  return `${h}h ${m.toString().padStart(2, '0')}m`;
}

interface QuickAction {
  key: 'wallet' | 'heatmap' | 'scan' | 'support';
  label: string;
  icon: Parameters<typeof LucideIcon>[0]['name'];
  to?: string;
  onClick?: () => void;
}

export function HomeDashboardPage() {
  const nav = useNavigate();
  const rider = useRiderStore((s) => s.profile);
  const toggleDuty = useRiderStore((s) => s.setDuty);
  const orders = useOrderStore((s) => s.list);
  const selectActive = useOrderStore((s) => s.selectActive);
  const loadOrders = useOrderStore((s) => s.loadList);

  const isOnline = rider?.status === 'ON_SHIFT';
  const completedToday = rider?.completed_today ?? 0;
  const shiftHoursOnline = useMemo(() => {
    if (!rider?.current_shift_started_at) return rider?.active_hours_today ?? 0;
    const start = new Date(rider.current_shift_started_at).getTime();
    if (!isFinite(start)) return rider?.active_hours_today ?? 0;
    return (Date.now() - start) / (1000 * 60 * 60);
  }, [rider?.current_shift_started_at, rider?.active_hours_today]);
  const canEndShift = completedToday > 0 || shiftHoursOnline >= 2;

  const activeAccepted = useMemo(
    () => orders.find((o) =>
      ['ACCEPTED','NAVIGATING_TO_PICKUP','ARRIVED_AT_PICKUP','ORDER_VERIFIED','NAVIGATING_TO_DROP_OFF','ARRIVED_AT_DROP_OFF','PROOF_SUBMITTED'].includes(o.state)
      && o.rider_id === rider?.id,
    ),
    [orders, rider?.id],
  );

  useEffect(() => { void loadOrders(); const t = setInterval(() => void loadOrders(), 15_000); return () => clearInterval(t); }, [loadOrders]);

  useEffect(() => {
    if (!activeAccepted) return;
    selectActive(activeAccepted.id);
    nav('/delivery');
  }, [activeAccepted?.id, nav, selectActive]);

  const tel = useLocationTelemetry(
    rider?.id ?? null,
    activeAccepted ?? null,
    (which) => { void which; /* geofence banner slot */ },
  );

  const greeting = (() => {
    const h = new Date().getHours();
    if (h < 12) return 'Good morning';
    if (h < 18) return 'Good afternoon';
    return 'Good evening';
  })();
  const riderName = rider?.name?.trim() || 'Juan Dela Cruz';
  const riderCode = rider?.rider_code || 'Rider ID: MB-10234';
  const dutyPayout = rider?.duty_today_payout ?? 1240;
  const rating = 4.9;

  const goalTarget = 12;
  const goalPct = Math.min(100, Math.round((completedToday / goalTarget) * 100));
  const bonusTarget = 900;
  const bonusMore = Math.max(0, bonusTarget - dutyPayout);

  const quickActions: QuickAction[] = [
    { key: 'wallet',  label: 'Wallet',   icon: 'Wallet',       to: '/earnings' },
    { key: 'heatmap', label: 'Heat Map', icon: 'MapPin',       onClick: () => alert('Heat Map: shows nearby hot orders zone.') },
    { key: 'scan',    label: 'Scan QR',  icon: 'ScanLine',     onClick: () => alert('Scan QR: scan merchant or customer QR for quick checkout.') },
    { key: 'support', label: 'Support',  icon: 'Headphones',   onClick: () => alert('Support: call dispatch or open chat with rider operations.') },
  ];

  return (
    <div className="min-h-full w-full bg-slate-950 text-white">
      <header className="phone-top-sticky z-30 bg-slate-950/85 backdrop-blur-xl lg:hidden">
        <div className="phone-col px-4 pt-[44px] sm:pt-[52px] pb-3 grid grid-cols-[auto_1fr_auto] items-center gap-2">
          <button
            type="button"
            className="min-h-[48px] min-w-[48px] h-11 w-11 rounded-2xl border border-surface-border bg-surface-card inline-flex items-center justify-center text-slate-200 hover:bg-slate-800"
            aria-label="Menu"
            onClick={() => nav('/profile')}
          >
            <LucideIcon name="Menu" size={22} strokeWidth={1.75} />
          </button>
          <span className="sr-only">Home Dashboard</span>
          <button
            type="button"
            className="ml-auto min-h-[48px] min-w-[48px] h-11 w-11 rounded-2xl border border-surface-border bg-surface-card inline-flex items-center justify-center text-slate-200 hover:bg-slate-800 relative"
            aria-label="Notifications"
            onClick={() => alert('Notifications: no new alerts.')}
          >
            <LucideIcon name="Bell" size={20} strokeWidth={1.75} />
          </button>
        </div>
      </header>

      <main className="phone-col space-y-5 px-4 pb-28 pt-3 lg:space-y-7 lg:px-8 lg:pb-10 lg:pt-8">
        {/* Greeting + Rider + Online badge */}
        <section className="px-1 grid grid-cols-[auto_1fr_auto] items-center gap-3">
          <span
            className="h-16 w-16 rounded-[24px] inline-flex items-center justify-center overflow-hidden border-2 border-cyan-400/40 bg-slate-800 shadow-[0_10px_24px_-12px_rgba(6,182,212,0.55)]"
            aria-label="Rider avatar"
          >
            <img src={RIDER_AVATAR} alt="" className="h-full w-full object-cover" />
          </span>
          <div className="min-w-0">
            <div className="text-[12px] font-semibold text-cyan-200 tracking-wide">
              {greeting},
            </div>
            <div className="flex items-center gap-1.5 flex-wrap">
              <h1 className="text-[18px] font-extrabold tracking-tight text-white truncate">
                {riderName}
                <span className="inline-flex ml-1.5 h-4 w-4 items-center justify-center rounded-full border border-cyan-500/40 bg-cyan-500/15 text-cyan-300 align-middle">
                  <LucideIcon name="Check" size={12} strokeWidth={2.5} />
                </span>
              </h1>
            </div>
            <div className="mt-0.5 text-[12px] font-mono font-semibold text-slate-400 truncate">
              {riderCode}
            </div>
          </div>
          <span className={cn(
            'inline-flex items-center gap-1.5 rounded-full h-8 px-3 text-[12px] font-extrabold uppercase tracking-[0.06em] border shrink-0',
            isOnline
              ? 'bg-cyan-500 text-slate-950 border-cyan-300 shadow-[0_0_20px_rgba(6,182,212,0.35)]'
              : 'bg-slate-800 text-slate-300 border-slate-700',
          )}>
            <span className={cn(
              'h-2 w-2 rounded-full',
              isOnline ? 'bg-slate-900 animate-pulse' : 'bg-slate-500',
            )} />
            {isOnline ? 'Online' : 'Offline'}
          </span>
        </section>

        {/* Today's Summary 4 cards */}
        <section>
          <div className="flex items-center justify-between mb-2 px-0.5">
            <h2 className="text-[12px] font-bold uppercase tracking-[0.1em] text-cyan-300">Today&apos;s Summary</h2>
            <button
              type="button"
              onClick={() => nav('/orders')}
              className="text-[12px] font-semibold text-slate-400 hover:text-cyan-200 inline-flex items-center gap-1"
            >
              View all <LucideIcon name="ChevronRight" size={14} strokeWidth={2} />
            </button>
          </div>
          <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <SummaryCard
              icon="Banknote"
              label="Earnings"
              value={currencyPHP(dutyPayout)}
              sub="Shift"
              primary
            />
            <SummaryCard
              icon="ClipboardCheck"
              label="Deliveries"
              value={`${completedToday}`}
              sub={`Goal ${goalTarget}`}
            />
            <SummaryCard
              icon="Star"
              label="Rating"
              value={rating.toFixed(1)}
            />
            <SummaryCard
              icon="Clock"
              label="Online"
              value={formatHours(shiftHoursOnline)}
              sub={isOnline ? 'Live' : 'Paused'}
              pulse={isOnline}
            />
          </div>
        </section>

        {/* Goal progress + Weather 2-column */}
        <section className="grid grid-cols-[minmax(0,1fr)_124px] gap-3 lg:grid-cols-[minmax(0,1fr)_220px]">
          <div className="relative rounded-2xl border border-cyan-700/35 bg-gradient-to-br from-slate-900 to-[#0c1a22] p-3.5 overflow-hidden">
            <div aria-hidden="true" className="absolute -right-10 -top-10 h-32 w-32 rounded-full bg-cyan-500/10 blur-2xl" />
            <div className="flex items-center justify-between relative">
              <h3 className="text-[12px] font-bold uppercase tracking-[0.1em] text-cyan-300">Today&apos;s Goal</h3>
              <span className="text-[11px] font-extrabold font-mono text-white">
                {completedToday}/{goalTarget} Deliveries
              </span>
            </div>
            <div className="mt-2.5 h-2.5 rounded-full bg-slate-800/80 overflow-hidden border border-slate-700/60 relative">
              <div
                className="h-full rounded-full bg-gradient-to-r from-cyan-400 to-cyan-700 shadow-[0_0_12px_rgba(6,182,212,0.55)] transition-[width] duration-500"
                style={{ width: `${goalPct}%` }}
              />
            </div>
            <div className="mt-3 grid grid-cols-[1fr_auto] items-center gap-2">
              <div className="inline-flex items-center gap-1.5 text-[12px] font-semibold text-cyan-100">
                <span className="h-5 w-5 rounded-md bg-cyan-500/15 border border-cyan-500/30 inline-flex items-center justify-center text-cyan-300">
                  <LucideIcon name="Gift" size={13} strokeWidth={1.75} />
                </span>
                Earn {bonusMore > 0 ? <span className="font-mono font-extrabold text-cyan-300">{currencyPHP(bonusMore)} more</span> : <span className="font-mono font-extrabold text-cyan-300">target hit</span>} to unlock bonus
              </div>
              <button
                type="button"
                onClick={() => nav('/earnings')}
                className="h-7 rounded-xl bg-cyan-500 text-slate-950 px-2.5 text-[11px] font-extrabold uppercase tracking-[0.06em] shadow-[0_0_14px_rgba(6,182,212,0.45)] hover:bg-cyan-400"
              >
                See Details
              </button>
            </div>
          </div>

          <div className="shrink-0 rounded-2xl border border-surface-border bg-surface-card p-3 flex flex-col items-center justify-center text-center lg:p-5">
            <div className="h-10 w-10 rounded-2xl bg-cyan-500/12 border border-cyan-500/25 inline-flex items-center justify-center text-cyan-300">
              <LucideIcon name="Sun" size={22} strokeWidth={1.75} className="drop-shadow-[0_0_8px_rgba(6,182,212,0.45)]" />
            </div>
            <div className="mt-1.5 text-[20px] font-extrabold font-mono leading-tight text-white">31°C</div>
            <div className="text-[11px] font-bold uppercase tracking-[0.06em] text-slate-400">Sunny</div>
            <div className="mt-1 text-[11px] font-semibold text-cyan-200/90">Ride safely today!</div>
          </div>
        </section>

        {/* Quick Actions */}
        <section>
          <h3 className="px-0.5 mb-2 text-[12px] font-bold uppercase tracking-[0.1em] text-cyan-300">Quick Actions</h3>
          <div className="grid grid-cols-2 gap-2 sm:grid-cols-4 lg:gap-3">
            {quickActions.map((a) => {
              const content = (
                <button type="button" className="group w-full rounded-2xl border border-surface-border bg-surface-card/80 hover:border-cyan-500/40 hover:bg-surface-card transition-colors py-3.5 flex flex-col items-center gap-1.5 text-center">
                  <span className="h-10 w-10 rounded-2xl bg-cyan-500/12 border border-cyan-500/25 inline-flex items-center justify-center text-cyan-300 group-hover:bg-cyan-500/20 transition-colors">
                    <LucideIcon name={a.icon} size={20} strokeWidth={1.75} />
                  </span>
                  <span className="text-[11px] font-bold uppercase tracking-[0.04em] text-slate-300 group-hover:text-cyan-200">{a.label}</span>
                </button>
              );
              if (a.to) return <Link key={a.key} to={a.to}>{content}</Link>;
              return <div key={a.key} onClick={a.onClick} role="presentation">{content}</div>;
            })}
          </div>
        </section>

        {/* Assigned Delivery */}
        <section>
          <div className="flex items-center justify-between px-0.5 mb-2">
            <h3 className="text-[12px] font-bold uppercase tracking-[0.1em] text-cyan-300">Assigned Delivery</h3>
            <span className="inline-flex items-center gap-1 rounded-full h-6 px-2.5 text-[11px] font-extrabold uppercase tracking-[0.06em] bg-cyan-500/12 border border-cyan-500/25 text-cyan-300">
              <span className="h-1.5 w-1.5 rounded-full bg-cyan-400 animate-pulse" />
              {isOnline ? 'Auto-assignment on' : 'Offline'}
            </span>
          </div>

          {activeAccepted ? (
            <Link
              to="/delivery"
              className="block rounded-2xl border border-cyan-600/40 bg-gradient-to-br from-cyan-500/15 to-slate-900 shadow-[0_10px_30px_-14px_rgba(6,182,212,0.55)] overflow-hidden"
            >
              <div className="p-3.5 grid grid-cols-[auto_1fr_auto] items-center gap-3">
                <span className="h-11 w-11 rounded-2xl bg-cyan-500/25 border border-cyan-500/40 inline-flex items-center justify-center text-cyan-200">
                  <LucideIcon name="Navigation" size={20} strokeWidth={2.25} className="-rotate-45" />
                </span>
                <div className="min-w-0">
                  <div className="text-[11px] font-extrabold uppercase tracking-[0.08em] text-cyan-300">Active Delivery</div>
                  <div className="text-[14px] font-bold text-white truncate">
                    {activeAccepted.merchant_name.slice(0, 24)} → {(activeAccepted.dropoff_name || 'Customer').slice(0, 16)}
                  </div>
                  <div className="mt-0.5 text-[12px] font-semibold text-slate-400">
                    {activeAccepted.order_code} · {activeAccepted.total_distance_km.toFixed(1)} km
                  </div>
                </div>
                <div className="flex flex-col items-end gap-1 shrink-0">
                  <div className="font-mono font-extrabold text-[16px] text-cyan-300">
                    {currencyPHP(activeAccepted.payout_amount + activeAccepted.tip_amount)}
                  </div>
                  <span className="inline-flex items-center gap-1 rounded-full bg-cyan-500/15 border border-cyan-500/30 text-cyan-300 px-2 h-5 text-[10px] font-extrabold uppercase tracking-[0.06em] animate-pulse">
                    Tap to view
                  </span>
                </div>
              </div>
            </Link>
          ) : (
            <div className="rounded-2xl border border-dashed border-surface-border bg-surface-card/40 p-4 text-center">
                <div className="inline-flex items-center justify-center h-10 w-10 rounded-2xl bg-cyan-500/10 border border-cyan-500/20 text-cyan-300 mb-1.5">
                  <LucideIcon name="PackageSearch" size={18} strokeWidth={1.75} />
                </div>
                <div className="text-[13px] font-semibold text-slate-300">No assigned delivery</div>
                <div className="text-[12px] text-slate-500 mt-0.5">
                  {isOnline ? 'You are available. The next order will be assigned automatically.' : 'Go online to receive automatic delivery assignments.'}
                </div>
            </div>
          )}
        </section>

        {/* Duty + Deposit CTA */}
        <section className="space-y-2.5 pt-1">
          <div className="rounded-2xl border border-surface-border bg-surface-card/70 p-3 grid grid-cols-2 gap-2.5 items-center">
            <div className="inline-flex items-center gap-2">
              <span className={cn(
                'inline-flex h-8 w-8 items-center justify-center rounded-xl',
                tel.watchActive && !tel.error
                  ? 'bg-cyan-500/15 border border-cyan-500/30 text-cyan-300'
                  : 'bg-slate-800 border border-slate-700 text-slate-400',
              )}>
                <LucideIcon name="Satellite" size={16} strokeWidth={1.75} />
              </span>
              <div>
                <div className="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-400">GPS Telemetry</div>
                <div className="text-[13px] font-semibold text-white inline-flex items-center gap-1.5">
                  <StatusDot state={tel.watchActive && !tel.error ? 'success' : 'danger'} size={8} />
                  {tel.error ? 'Error' : tel.watchActive ? tel.lastPoint ? `${tel.lastPoint.speed_kmh?.toFixed(0) ?? '0'} km/h · Queue ${tel.queueDepth}` : 'Acquiring…' : 'Suspended'}
                </div>
              </div>
            </div>
            <div className="inline-flex items-center justify-end gap-2">
              <Button
                variant={isOnline ? 'slate' : 'primary'}
                size="md"
                className="h-11 rounded-xl px-3"
                onClick={async () => {
                  await toggleDuty(!isOnline);
                  await loadOrders();
                }}
                iconLeft={<LucideIcon name={isOnline ? 'PauseCircle' : 'Power'} size={16} strokeWidth={1.75} />}
              >
                {isOnline ? 'Go Offline' : 'Go Online'}
              </Button>
            </div>
          </div>

          {canEndShift ? (
            <Button
              variant="warning"
              size="block"
              onClick={() => nav('/deposit')}
              className="h-12 rounded-2xl text-[15px] font-bold tracking-wide"
              iconLeft={<LucideIcon name="Landmark" size={18} strokeWidth={1.75} />}
            >
              End Shift · Deposit
            </Button>
          ) : (
            <div className="relative group">
              <Button
                variant="slate"
                size="block"
                disabled
                className="h-12 rounded-2xl text-[15px] font-bold tracking-wide"
                iconLeft={<LucideIcon name="Landmark" size={18} strokeWidth={1.75} />}
              >
                End Shift · Deposit
              </Button>
              <div className="absolute left-1/2 -translate-x-1/2 bottom-full mb-2 px-3 py-2 rounded-xl bg-surface-dark border border-surface-border text-[11px] font-semibold text-surface-muted text-center whitespace-nowrap opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity">
                Complete at least 1 delivery or 2h online
              </div>
            </div>
          )}
        </section>
      </main>

    </div>
  );
}

function SummaryCard({
  icon, label, value, sub, primary, pulse,
}: {
  icon: Parameters<typeof LucideIcon>[0]['name'];
  label: string;
  value: string;
  sub?: string;
  primary?: boolean;
  pulse?: boolean;
}) {
  return (
    <div className={cn(
      'relative min-h-[112px] rounded-2xl border p-3 overflow-hidden lg:min-h-[144px] lg:p-4',
      primary
        ? 'border-cyan-500/40 bg-gradient-to-br from-cyan-500/20 to-cyan-900/20 shadow-[0_0_28px_-12px_rgba(6,182,212,0.6)]'
        : 'border-surface-border bg-surface-card',
    )}>
      {primary ? <div aria-hidden="true" className="absolute -right-6 -top-6 h-20 w-20 rounded-full bg-cyan-400/20 blur-2xl" /> : null}
      <div className="relative flex items-center gap-1">
        <span className={cn(
          'h-7 w-7 rounded-xl inline-flex items-center justify-center',
          primary
            ? 'bg-white/15 border border-white/25 text-cyan-100'
            : 'bg-cyan-500/12 border border-cyan-500/25 text-cyan-300',
        )}>
          <LucideIcon name={icon} size={14} strokeWidth={1.75} />
        </span>
        <span className={cn(
          'text-[10px] font-extrabold uppercase tracking-[0.06em] truncate',
          primary ? 'text-cyan-100' : 'text-slate-400',
        )}>{label}</span>
      </div>
      <div className={cn(
        'relative mt-1.5 font-mono font-extrabold leading-tight tracking-tight whitespace-nowrap',
        primary ? 'text-white text-[18px] lg:text-[22px]' : 'text-white text-[16px] lg:text-[20px]',
      )}>
        {value}
      </div>
      {sub ? (
        <div className={cn(
          'text-[10px] font-bold uppercase tracking-[0.06em] mt-0.5 inline-flex items-center gap-1',
          primary ? 'text-cyan-200' : 'text-slate-400',
        )}>
          {pulse ? <span className="h-1.5 w-1.5 rounded-full bg-cyan-400 animate-pulse" /> : null}
          {sub}
        </div>
      ) : null}
    </div>
  );
}
