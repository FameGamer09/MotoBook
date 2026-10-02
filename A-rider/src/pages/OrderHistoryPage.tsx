import { useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useRiderStore } from '@/store/useRiderStore';
import { useOrderStore } from '@/store/useOrderStore';
import { Button } from '@/components/ui/Button';
import { LucideIcon, cn, currencyPHP } from '@/components/ui/Primitive';
import { format, startOfToday, startOfYesterday } from 'date-fns';

type HistoryFilter = 'ALL' | 'COMPLETED' | 'CANCELLED';

interface HistoryRow {
  id: number;
  order_code: string;
  merchant_name: string;
  merchant_initials: string;
  completed_at_ms: number;
  payout_amount: number;
  tip_amount: number;
  status: 'COMPLETED' | 'CANCELLED';
  isToday: boolean;
  merchant_lat?: number | null;
  merchant_lng?: number | null;
}

function groupInitials(name: string): string {
  const parts = (name || '').trim().split(/[^A-Za-z0-9]+/).filter(Boolean).slice(0, 2);
  if (parts.length === 0) return 'MB';
  return parts.map((p) => p[0]?.toUpperCase() ?? '').join('');
}

const FILTER_CHIPS: { key: HistoryFilter; label: string }[] = [
  { key: 'ALL', label: 'All' },
  { key: 'COMPLETED', label: 'Completed' },
  { key: 'CANCELLED', label: 'Cancelled' },
];

const HISTORY_SEED: HistoryRow[] = [
  {
    id: 998_010, order_code: 'MB-99210', merchant_name: 'Big Brew',
    merchant_initials: 'BB', completed_at_ms: startOfToday().getTime() + 10 * 60 * 60 * 1000 + 32 * 60 * 1000,
    payout_amount: 80, tip_amount: 70, status: 'COMPLETED', isToday: true,
    merchant_lat: 14.5565, merchant_lng: 121.0551,
  },
  {
    id: 998_009, order_code: 'MB-99208', merchant_name: "McDonald's",
    merchant_initials: 'MD', completed_at_ms: startOfToday().getTime() + 9 * 60 * 60 * 1000 + 5 * 60 * 1000,
    payout_amount: 72, tip_amount: 48, status: 'COMPLETED', isToday: true,
  },
  {
    id: 998_005, order_code: 'MB-99204', merchant_name: 'Chowking',
    merchant_initials: 'CK', completed_at_ms: startOfYesterday().getTime() + 18 * 60 * 60 * 1000 + 48 * 60 * 1000,
    payout_amount: 95, tip_amount: 15, status: 'COMPLETED', isToday: false,
  },
  {
    id: 998_003, order_code: 'MB-99202', merchant_name: 'KFC',
    merchant_initials: 'KF', completed_at_ms: startOfYesterday().getTime() + 16 * 60 * 60 * 1000 + 15 * 60 * 1000,
    payout_amount: 110, tip_amount: 0, status: 'COMPLETED', isToday: false,
  },
];

export function OrderHistoryPage() {
  const navigate = useNavigate();
  const rider = useRiderStore((s) => s.profile);
  const orders = useOrderStore((s) => s.list);
  const [filter, setFilter] = useState<HistoryFilter>('ALL');

  const rows: HistoryRow[] = useMemo(() => {
    const fromStore: HistoryRow[] = orders
      .filter((o) => o.state === 'COMPLETED' || o.state === 'CANCELLED')
      .map((o) => {
        const ts = o.completed_at ? new Date(o.completed_at).getTime() : new Date(o.updated_at || 0).getTime();
        const todayStart = startOfToday().getTime();
        return {
          id: o.id,
          order_code: o.order_code,
          merchant_name: o.merchant_name || 'Merchant',
          merchant_initials: groupInitials(o.merchant_name || ''),
          completed_at_ms: ts,
          payout_amount: o.payout_amount || 0,
          tip_amount: o.tip_amount || 0,
          status: o.state === 'COMPLETED' ? 'COMPLETED' : 'CANCELLED',
          isToday: ts >= todayStart,
          merchant_lat: o.merchant_lat ?? null,
          merchant_lng: o.merchant_lng ?? null,
        } satisfies HistoryRow;
      });

    const seen = new Set(fromStore.map((r) => r.id));
    const dedupedSeed = HISTORY_SEED.filter((s) => !seen.has(s.id));
    const all = [...fromStore, ...dedupedSeed];
    all.sort((a, b) => b.completed_at_ms - a.completed_at_ms);
    return all;
  }, [orders]);

  const filtered = useMemo(() => {
    if (filter === 'ALL') return rows;
    return rows.filter((r) => r.status === filter);
  }, [rows, filter]);

  const todayRows = filtered.filter((r) => r.isToday);
  const yesterdayRows = filtered.filter((r) => !r.isToday);

  return (
    <div className="min-h-[100dvh] w-full flex flex-col bg-gradient-to-b from-slate-950 via-slate-950 to-slate-950 text-white">
      <header className="phone-col px-4 pt-[44px] sm:pt-[52px] pb-3 sticky top-0 z-20 bg-slate-950/80 backdrop-blur-xl border-b border-surface-border/60">
        <div className="flex items-center justify-between gap-2">
          <Button variant="ghost" size="sm" onClick={() => navigate(-1)} aria-label="Back">
            <LucideIcon name="ChevronLeft" size={22} strokeWidth={2} />
          </Button>
          <h1 className="text-screen-title font-semibold text-white">Order History</h1>
          <Button variant="ghost" size="sm" aria-label="Filter">
            <LucideIcon name="Filter" size={20} strokeWidth={1.75} />
          </Button>
        </div>
        <div className="mt-4 inline-flex gap-2 rounded-2xl p-1 bg-surface-panel/70 border border-surface-border w-full">
          {FILTER_CHIPS.map((chip) => {
            const active = filter === chip.key;
            return (
              <button
                key={chip.key}
                type="button"
                onClick={() => setFilter(chip.key)}
                className={cn(
                  'flex-1 h-9 rounded-xl text-[13px] font-bold transition-all',
                  active
                    ? 'bg-primary text-white shadow-[0_0_18px_rgba(6,182,212,0.25)]'
                    : 'text-slate-400 hover:text-cyan-200',
                )}
              >
                {chip.label}
              </button>
            );
          })}
        </div>
      </header>

      <main className="phone-col px-4 pt-4 pb-4 space-y-6">
        <section>
          <div className="flex items-center justify-between mb-2">
            <h2 className="text-[12px] font-bold uppercase tracking-[0.1em] text-slate-400">Today</h2>
            <span className="text-[12px] font-semibold text-surface-muted">{format(new Date(), 'MMM d')}</span>
          </div>
          <ul className="space-y-2.5">
            {todayRows.length === 0 ? (
              <li className="rounded-2xl border border-dashed border-surface-border bg-surface-card/40 px-4 py-5 text-center">
                <div className="text-body font-semibold text-slate-300">No completed orders today</div>
                <div className="text-helper text-surface-muted mt-1">New deliveries are assigned automatically while you are on duty.</div>
              </li>
            ) : todayRows.map((r) => <HistoryRowCard key={r.id} row={r} rider={rider} />)}
          </ul>
        </section>

        <section>
          <div className="flex items-center justify-between mb-2">
            <h2 className="text-[12px] font-bold uppercase tracking-[0.1em] text-slate-400">Yesterday</h2>
            <span className="text-[12px] font-semibold text-surface-muted">{format(startOfYesterday(), 'MMM d')}</span>
          </div>
          <ul className="space-y-2.5">
            {yesterdayRows.length === 0 ? (
              <li className="rounded-2xl border border-dashed border-surface-border bg-surface-card/40 px-4 py-5 text-center">
                <div className="text-body font-semibold text-slate-300">Nothing here</div>
                <div className="text-helper text-surface-muted mt-1">Come back after deliveries.</div>
              </li>
            ) : yesterdayRows.map((r) => <HistoryRowCard key={r.id} row={r} rider={rider} />)}
          </ul>
        </section>
      </main>
    </div>
  );
}

function HistoryRowCard({ row, rider }: { row: HistoryRow; rider: ReturnType<typeof useRiderStore.getState>['profile'] }) {
  const time = format(new Date(row.completed_at_ms), 'h:mm a');
  const total = row.payout_amount + row.tip_amount;
  return (
    <li className="group rounded-2xl border border-surface-border bg-surface-card/80 hover:bg-surface-card transition-colors p-3.5 grid grid-cols-[auto_1fr_auto] items-center gap-3">
      <span className="h-11 w-11 rounded-2xl inline-flex items-center justify-center bg-gradient-to-br from-cyan-600 to-cyan-800 text-white font-extrabold text-[13px] tracking-tight border border-cyan-500/30 shadow-[0_0_18px_rgba(6,182,212,0.18)]">
        {row.merchant_initials || 'MB'}
      </span>
      <div className="min-w-0">
        <div className="flex items-center gap-1.5 flex-wrap">
          <span className="text-[13px] font-extrabold text-white truncate">{row.merchant_name}</span>
        </div>
        <div className="mt-0.5 flex items-center gap-2">
          <span className="text-helper font-mono text-slate-400">{row.order_code}</span>
          <span className="text-helper text-slate-500">·</span>
          <span className="text-helper text-slate-400">{time}</span>
        </div>
      </div>
      <div className="flex flex-col items-end gap-1.5">
        <div className="font-mono font-extrabold text-[14px] text-cyan-300 whitespace-nowrap">
          {currencyPHP(total)}
        </div>
        {row.status === 'COMPLETED' ? (
          <span className="inline-flex items-center gap-1 rounded-full px-2 h-5 text-[10px] font-extrabold uppercase tracking-[0.06em] bg-cyan-500/12 text-cyan-300 border border-cyan-500/25">
            <span className="h-1.5 w-1.5 rounded-full bg-cyan-400" /> Delivered
          </span>
        ) : (
          <span className="inline-flex items-center gap-1 rounded-full px-2 h-5 text-[10px] font-extrabold uppercase tracking-[0.06em] bg-slate-500/15 text-slate-300 border border-slate-500/25">
            <span className="h-1.5 w-1.5 rounded-full bg-slate-500" /> Cancelled
          </span>
        )}
      </div>
    </li>
  );
}
