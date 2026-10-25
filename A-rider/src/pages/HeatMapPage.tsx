import { useMemo } from 'react';
import { Link } from 'react-router-dom';
import { ArrowUpRight, ChevronLeft, MapPin, RefreshCw, Store } from 'lucide-react';
import { useOrderStore } from '@/store/useOrderStore';
import { useRiderStore } from '@/store/useRiderStore';
import { currencyPHP } from '@/components/ui/Primitive';

const VISIBLE_STATES = new Set(['OFFER_RECEIVED', 'ACCEPTED', 'NAVIGATING_TO_PICKUP', 'ARRIVED_AT_PICKUP', 'ORDER_VERIFIED']);

export function HeatMapPage() {
  const orders = useOrderStore((s) => s.list);
  const loading = useOrderStore((s) => s.loading);
  const loadOrders = useOrderStore((s) => s.loadList);
  const riderId = useRiderStore((s) => s.profile?.id);
  const hotspots = useMemo(() => {
    const groups = new Map<string, { name: string; address: string; lat: number; lng: number; count: number; payout: number }>();
    orders.filter((order) => order.rider_id === riderId && VISIBLE_STATES.has(order.state)
      && order.merchant_lat != null && order.merchant_lng != null
      && Number.isFinite(order.merchant_lat) && Number.isFinite(order.merchant_lng))
      .forEach((order) => {
        const lat = Number(order.merchant_lat);
        const lng = Number(order.merchant_lng);
        const key = `${lat.toFixed(3)},${lng.toFixed(3)}`;
        const existing = groups.get(key);
        if (existing) {
          existing.count += 1;
          existing.payout += Number(order.payout_amount || 0);
        } else {
          groups.set(key, { name: order.merchant_name, address: order.merchant_address, lat, lng, count: 1, payout: Number(order.payout_amount || 0) });
        }
      });
    return [...groups.values()].sort((a, b) => b.count - a.count || a.name.localeCompare(b.name));
  }, [orders, riderId]);

  return (
    <main className="phone-col min-h-[100dvh] space-y-4 bg-slate-950 px-4 pb-28 pt-5 text-white lg:px-8 lg:pt-8">
      <header className="flex items-center gap-3">
        <Link to="/" aria-label="Back to dashboard" className="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-surface-border bg-surface-card text-slate-200">
          <ChevronLeft size={20} />
        </Link>
        <div className="min-w-0 flex-1">
          <h1 className="text-lg font-extrabold tracking-tight">Heat Map</h1>
          <p className="text-xs text-slate-400">Current pickup activity for your rider account</p>
        </div>
        <button type="button" onClick={() => void loadOrders()} disabled={loading} aria-label="Refresh pickup activity" className="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-cyan-500/30 bg-cyan-500/10 text-cyan-200 disabled:opacity-50">
          <RefreshCw size={18} className={loading ? 'animate-spin' : ''} />
        </button>
      </header>

      <section className="rounded-3xl border border-cyan-500/20 bg-gradient-to-br from-cyan-500/10 via-surface-card to-surface-card p-4">
        <div className="flex items-start gap-3">
          <span className="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border border-cyan-400/25 bg-cyan-500/15 text-cyan-200"><MapPin size={21} /></span>
          <div>
            <h2 className="text-sm font-bold text-white">Pickup activity near your deliveries</h2>
            <p className="mt-1 text-xs leading-5 text-slate-300">Hotspots are grouped from current pickup offers and assigned orders. The list refreshes from the rider service; it does not estimate unassigned demand.</p>
          </div>
        </div>
        <div className="mt-4 flex items-center justify-between rounded-2xl border border-surface-border bg-slate-950/50 px-3.5 py-3">
          <span className="text-xs font-semibold text-slate-400">Active pickup locations</span>
          <strong className="font-mono text-lg text-cyan-200">{hotspots.length}</strong>
        </div>
      </section>

      <section className="space-y-2" aria-live="polite">
        <div className="flex items-center justify-between px-1">
          <h2 className="text-xs font-extrabold uppercase tracking-[0.1em] text-cyan-200">Pickup hotspots</h2>
          <span className="text-[11px] text-slate-500">{hotspots.reduce((sum, spot) => sum + spot.count, 0)} current orders</span>
        </div>
        {hotspots.length ? hotspots.map((spot) => (
          <article key={`${spot.lat}-${spot.lng}`} className="rounded-2xl border border-surface-border bg-surface-card p-4">
            <div className="flex items-start gap-3">
              <span className="relative inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-cyan-500/30 bg-cyan-500/10 text-cyan-200">
                <Store size={18} />
                {spot.count > 1 ? <span className="absolute -right-1.5 -top-1.5 inline-flex h-5 min-w-5 items-center justify-center rounded-full border border-slate-950 bg-cyan-400 px-1 text-[10px] font-extrabold text-slate-950">{spot.count}</span> : null}
              </span>
              <div className="min-w-0 flex-1">
                <h3 className="truncate text-sm font-bold text-white">{spot.name}</h3>
                <p className="mt-1 line-clamp-2 text-xs leading-5 text-slate-400">{spot.address || 'Pickup location'}</p>
                <div className="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-[11px] font-semibold text-slate-300">
                  <span className="inline-flex items-center gap-1 text-cyan-200"><MapPin size={13} />{spot.lat.toFixed(4)}, {spot.lng.toFixed(4)}</span>
                  <span>{spot.count} pickup {spot.count === 1 ? 'order' : 'orders'}</span>
                  <span>{currencyPHP(spot.payout)} payout</span>
                </div>
              </div>
              <a href={`https://www.google.com/maps/search/?api=1&query=${spot.lat},${spot.lng}`} target="_blank" rel="noreferrer" aria-label={`Open ${spot.name} in Maps`} className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-surface-border text-slate-300 hover:border-cyan-500/40 hover:text-cyan-200">
                <ArrowUpRight size={17} />
              </a>
            </div>
            <div className="mt-3 h-1.5 overflow-hidden rounded-full bg-slate-800" aria-label={`${spot.count} active orders at this pickup`}>
              <div className="h-full rounded-full bg-gradient-to-r from-cyan-500 to-cyan-300" style={{ width: `${Math.max(22, Math.min(100, spot.count * 24))}%` }} />
            </div>
          </article>
        )) : (
          <div className="rounded-2xl border border-dashed border-slate-700 bg-surface-card/60 px-5 py-8 text-center">
            <MapPin size={25} className="mx-auto text-slate-500" />
            <h3 className="mt-3 text-sm font-bold text-slate-200">No pickup activity available</h3>
            <p className="mx-auto mt-1 max-w-xs text-xs leading-5 text-slate-400">When offers or assigned deliveries include pickup coordinates, they will appear here. Pull to refresh or use the refresh button.</p>
          </div>
        )}
      </section>
    </main>
  );
}
