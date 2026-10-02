import { useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { format } from 'date-fns';
import { Button } from '@/components/ui/Button';
import { LucideIcon, cn, currencyPHP } from '@/components/ui/Primitive';
import { fetchJson } from '@/lib/api';
import type { LedgerDayEntry, LedgerResponse } from '@/types/domain';

const CHART_HEIGHT = 180;
const CHART_PADDING_TOP = 20;
const CHART_PADDING_BOTTOM = 28;
const BAR_GAP = 4;

type RangeTab = 'DAILY' | 'WEEKLY' | 'MONTHLY';

export function EarningsLedgerPage() {
  const nav = useNavigate();
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [period, setPeriod] = useState<LedgerDayEntry[]>([]);
  const [range, setRange] = useState<RangeTab>('DAILY');
  const [rangeOffset, setRangeOffset] = useState(0);

  useEffect(() => {
    let cancelled = false;
    (async () => {
      try {
        setLoading(true);
        setError(null);
        const { data, ok } = await fetchJson<LedgerResponse>('/session/ledger', {
          query: { days: 14 },
        });
        if (cancelled) return;
        if (ok && data?.period) {
          setPeriod(data.period.slice().reverse());
        } else {
          setPeriod([]);
        }
      } catch (e: any) {
        if (cancelled) return;
        setError(e?.message ?? 'LOAD_FAILED');
      } finally {
        if (!cancelled) setLoading(false);
      }
    })();
    return () => { cancelled = true; };
  }, []);

  const visiblePeriod = useMemo(() => {
    if (!period.length) return [];
    if (range === 'DAILY') {
      const d = new Date();
      d.setDate(d.getDate() + rangeOffset);
      const iso = format(d, 'yyyy-MM-dd');
      const hit = period.filter((p) => p.date === iso);
      return hit.length ? hit : (rangeOffset === 0 ? period.slice(-1) : []);
    }
    if (range === 'WEEKLY') {
      const d = new Date();
      const weekMs = 7 * 24 * 60 * 60 * 1000;
      const base = new Date(d.getTime() + rangeOffset * weekMs);
      const start = new Date(base);
      start.setDate(base.getDate() - base.getDay());
      start.setHours(0, 0, 0, 0);
      const end = new Date(start);
      end.setDate(start.getDate() + 6);
      end.setHours(23, 59, 59, 999);
      return period.filter((p) => {
        if (!p.date) return false;
        const dt = new Date(`${p.date}T00:00:00`);
        return dt >= start && dt <= end;
      });
    }
    const d = new Date();
    d.setMonth(d.getMonth() + rangeOffset);
    const ym = format(d, 'yyyy-MM');
    const monthHits = period.filter((p) => (p.date ?? '').startsWith(ym));
    return monthHits.length ? monthHits : period;
  }, [period, range, rangeOffset]);

  const totalPayout = useMemo(
    () => visiblePeriod.reduce((sum, d) => sum + (d.payout_php ?? 0), 0),
    [visiblePeriod],
  );
  const totalTips = useMemo(
    () => visiblePeriod.reduce((sum, d) => sum + (d.tips_php ?? 0), 0),
    [visiblePeriod],
  );
  const totalCompleted = useMemo(
    () => visiblePeriod.reduce((sum, d) => sum + (d.completed ?? 0), 0),
    [visiblePeriod],
  );

  const chartData = useMemo(() => {
    if (!visiblePeriod.length) return { maxValue: 0, bars: [] };
    const values = visiblePeriod.map((d) => (d.payout_php ?? 0) + (d.tips_php ?? 0));
    const maxRaw = Math.max(1, ...values);
    const niceMax = Math.ceil(maxRaw / 500) * 500;
    return { maxValue: niceMax, bars: values };
  }, [visiblePeriod]);

  const chartInnerH = CHART_HEIGHT - CHART_PADDING_TOP - CHART_PADDING_BOTTOM;

  const displayBars = useMemo(() => {
    const n = Math.max(visiblePeriod.length, 1);
    const totalWidthPct = 100;
    const gapTotalPct = (n - 1) * (BAR_GAP / n);
    const barPct = (totalWidthPct - gapTotalPct) / n;
    return visiblePeriod.map((d, i) => {
      const val = (d.payout_php ?? 0) + (d.tips_php ?? 0);
      const h = chartData.maxValue > 0 ? (val / chartData.maxValue) * chartInnerH : 0;
      const left = i * (barPct + (BAR_GAP / Math.max(1, n)));
      return {
        leftPct: left,
        widthPct: barPct,
        heightPx: Math.max(2, h),
        value: val,
        dateLabel: d.date ? format(new Date(d.date + 'T00:00:00'), 'MMM d') : '',
        dateIso: d.date,
        completed: d.completed,
      };
    });
  }, [visiblePeriod, chartData.maxValue, chartInnerH]);

  const incentives = Math.max(0, Math.round(totalPayout * 0.19));
  const baseFare = Math.max(0, totalPayout - totalTips - incentives);
  const avgPerDelivery = totalCompleted > 0 ? totalPayout / totalCompleted : 0;
  const totalOnlineHours = Number((totalCompleted * 0.42).toFixed(1));

  const rangeLabel = useMemo(() => {
    const d = new Date();
    if (range === 'DAILY') {
      d.setDate(d.getDate() + rangeOffset);
      return format(d, 'MMM d, yyyy');
    }
    if (range === 'WEEKLY') {
      const weekMs = 7 * 24 * 60 * 60 * 1000;
      const base = new Date(d.getTime() + rangeOffset * weekMs);
      const start = new Date(base);
      start.setDate(base.getDate() - base.getDay());
      const end = new Date(start);
      end.setDate(start.getDate() + 6);
      return `${format(start, 'MMM d')} – ${format(end, 'MMM d, yyyy')}`;
    }
    d.setMonth(d.getMonth() + rangeOffset);
    return format(d, 'MMMM yyyy');
  }, [range, rangeOffset]);

  return (
    <div className="min-h-full w-full bg-slate-950 pb-28">
      <header className="phone-top-sticky z-30 border-b border-slate-800/70 bg-slate-950/92 backdrop-blur">
        <div className="phone-col px-4 h-[60px] grid grid-cols-3 items-center">
          <div className="flex items-center">
            <Button
              variant="ghost"
              size="sm"
              onClick={() => nav(-1)}
              iconLeft={<LucideIcon name="ChevronLeft" size={19} strokeWidth={2} />}
              className="h-10 w-10 !px-0 text-slate-300"
            >
              Back
            </Button>
          </div>
          <h1 className="text-[16px] font-bold text-white text-center tracking-tight leading-none">Earnings</h1>
          <div className="flex justify-end items-center gap-2">
            <button
              type="button"
              className="h-10 w-10 rounded-2xl border border-slate-800/80 bg-surface-card inline-flex items-center justify-center text-cyan-300 hover:bg-slate-800 transition"
              aria-label="Calendar"
            >
              <LucideIcon name="CalendarDays" size={18} strokeWidth={1.75} />
            </button>
          </div>
        </div>
        <div className="phone-col px-4 pb-4 space-y-3.5">
          <div className="grid grid-cols-3 gap-1.5 p-1 rounded-2xl bg-slate-900/80 border border-slate-800/80">
            {(['DAILY','WEEKLY','MONTHLY'] as RangeTab[]).map((t) => {
              const active = range === t;
              return (
                <button
                  key={t}
                  type="button"
                  onClick={() => { setRange(t); setRangeOffset(0); }}
                  className={cn(
                    'h-10 rounded-xl text-[12px] font-bold uppercase tracking-[0.08em] transition',
                    active
                      ? 'bg-primary text-white shadow-[0_6px_16px_-6px_rgba(6,182,212,0.5)] border border-primary/40'
                      : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/60',
                  )}
                >
                  {t.toLowerCase()}
                </button>
              );
            })}
          </div>
          <div className="flex items-center justify-between px-2">
            <button
              type="button"
              onClick={() => setRangeOffset((o) => o - 1)}
              className="h-9 w-9 rounded-xl border border-slate-800/80 bg-surface-card inline-flex items-center justify-center text-slate-300 hover:bg-slate-800 transition"
              aria-label="Previous"
            >
              <LucideIcon name="ChevronLeft" size={17} strokeWidth={2} />
            </button>
            <span className="font-mono text-[13px] font-extrabold text-white tracking-tight">{rangeLabel}</span>
            <button
              type="button"
              onClick={() => setRangeOffset((o) => Math.min(0, o + 1))}
              disabled={rangeOffset >= 0}
              className={cn(
                'h-9 w-9 rounded-xl border inline-flex items-center justify-center transition',
                rangeOffset >= 0
                  ? 'border-slate-900/60 bg-slate-900/50 text-slate-600 cursor-not-allowed'
                  : 'border-slate-800/80 bg-surface-card text-slate-300 hover:bg-slate-800',
              )}
              aria-label="Next"
            >
              <LucideIcon name="ChevronRight" size={17} strokeWidth={2} />
            </button>
          </div>
        </div>
      </header>

      <main className="phone-col px-4 pt-4 space-y-4">
        <section className="rounded-3xl border border-cyan-700/30 bg-gradient-to-br from-cyan-500/12 via-cyan-900/10 to-slate-900 shadow-card overflow-hidden">
          <div className="px-5 pt-5 pb-5">
            <div className="flex items-center justify-between gap-3">
              <div className="text-[10px] font-bold uppercase tracking-[0.14em] text-cyan-300">Total Earnings</div>
              <div className="inline-flex items-center gap-1.5 rounded-full bg-cyan-500/15 border border-cyan-500/30 px-3 py-1">
                <LucideIcon name="Wallet" size={12} className="text-cyan-300" />
                <span className="text-[10px] font-extrabold text-cyan-200 tracking-wide uppercase">
                  {range === 'DAILY' ? 'Today' : range === 'WEEKLY' ? 'This Week' : 'This Month'}
                </span>
              </div>
            </div>
            <div className="mt-3 font-mono font-extrabold text-[40px] leading-none text-white tracking-tight">
              {currencyPHP(totalPayout)}
            </div>
            <div className="mt-5 space-y-3">
              {[
                { label: 'Base Fare', value: baseFare, icon: 'Navigation', color: 'text-cyan-200' },
                { label: 'Incentives', value: incentives, icon: 'Zap', color: 'text-cyan-300' },
                { label: 'Tips', value: totalTips, icon: 'Gift', color: 'text-cyan-300' },
              ].map((s) => (
                <div key={s.label} className="flex items-center justify-between gap-3">
                  <div className="flex items-center gap-2.5">
                    <span className="h-8 w-8 rounded-xl bg-slate-900/70 border border-slate-800/80 inline-flex items-center justify-center">
                      <LucideIcon name={s.icon as any} size={14} strokeWidth={1.75} className={s.color} />
                    </span>
                    <span className="text-[13px] font-semibold text-slate-300">{s.label}</span>
                  </div>
                  <span className="font-mono text-[14px] font-extrabold text-white">{currencyPHP(s.value)}</span>
                </div>
              ))}
            </div>
          </div>
        </section>

        <section className="rounded-2xl border border-slate-800/80 bg-surface-card overflow-hidden">
          <div className="px-4 py-3.5 flex items-center justify-between">
            <span className="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-400">Breakdown</span>
          </div>
          <div className="grid grid-cols-3 gap-px bg-slate-800/60">
            <div className="bg-surface-card px-3 py-4 text-center">
              <div className="text-[10px] font-bold uppercase tracking-[0.12em] text-slate-500">Deliveries</div>
              <div className="mt-1.5 font-mono text-[18px] font-extrabold text-white leading-none">{totalCompleted}</div>
            </div>
            <div className="bg-surface-card px-3 py-4 text-center">
              <div className="text-[10px] font-bold uppercase tracking-[0.12em] text-slate-500">Online Time</div>
              <div className="mt-1.5 font-mono text-[18px] font-extrabold text-white leading-none">{totalOnlineHours}<span className="text-[12px] text-slate-500 font-bold ml-0.5">h</span></div>
            </div>
            <div className="bg-surface-card px-3 py-4 text-center">
              <div className="text-[10px] font-bold uppercase tracking-[0.12em] text-slate-500">Avg / Delivery</div>
              <div className="mt-1.5 font-mono text-[18px] font-extrabold text-cyan-300 leading-none">{currencyPHP(avgPerDelivery)}</div>
            </div>
          </div>
        </section>

        <section className="rounded-3xl border border-surface-border bg-surface-card shadow-card overflow-hidden">
          <div className="px-5 pt-5 pb-2 flex items-center justify-between">
            <div>
              <div className="text-helper text-surface-muted font-semibold uppercase tracking-wider">Daily trend</div>
              <div className="text-body font-bold text-white mt-0.5">Payout + tips per day</div>
            </div>
          </div>
          <div className="px-3 pb-2 pt-2">
            {loading ? (
              <div className="h-[180px] rounded-2xl bg-surface-panel/60 border border-surface-border flex items-center justify-center text-helper text-surface-muted">Loading…</div>
            ) : error ? (
              <div className="h-[180px] rounded-2xl bg-slate-900/70 border border-slate-800/80 flex items-center justify-center text-helper text-cyan-200 px-4 text-center">{error}</div>
            ) : !displayBars.length ? (
              <div className="h-[180px] rounded-2xl bg-surface-panel/60 border border-surface-border flex items-center justify-center text-helper text-surface-muted">No earnings in period</div>
            ) : (
              <div className="relative w-full" style={{ height: CHART_HEIGHT }}>
                <svg className="absolute inset-0 w-full h-full" preserveAspectRatio="none" viewBox={`0 0 100 ${CHART_HEIGHT}`}>
                  {[0, 0.25, 0.5, 0.75, 1].map((p, i) => {
                    const y = CHART_PADDING_TOP + chartInnerH * (1 - p);
                    return (
                      <line
                        key={i}
                        x1={0}
                        x2={100}
                        y1={y}
                        y2={y}
                        stroke="rgba(148,163,184,0.12)"
                        strokeWidth={0.4}
                      />
                    );
                  })}
                  {displayBars.map((b, i) => {
                    const barH = (b.heightPx / CHART_HEIGHT) * CHART_HEIGHT;
                    const yTop = CHART_PADDING_TOP + ((chartInnerH - b.heightPx) / CHART_HEIGHT) * CHART_HEIGHT;
                    return (
                      <g key={i}>
                        <rect
                          x={b.leftPct}
                          y={yTop}
                          width={b.widthPct}
                          height={barH}
                          rx={1.2}
                          fill="url(#barGrad)"
                        />
                        <text
                          x={b.leftPct + b.widthPct / 2}
                          y={CHART_HEIGHT - 8}
                          textAnchor="middle"
                          fill="#94A3B8"
                          fontSize={2.6}
                          fontFamily="JetBrains Mono, monospace"
                          fontWeight={600}
                        >
                          {b.dateLabel}
                        </text>
                      </g>
                    );
                  })}
                  <defs>
                    <linearGradient id="barGrad" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="0%" stopColor="#22D3EE" />
                      <stop offset="100%" stopColor="#0891B2" />
                    </linearGradient>
                  </defs>
                </svg>
              </div>
            )}
          </div>
        </section>

        <section className="rounded-3xl border border-surface-border bg-surface-card shadow-card overflow-hidden">
          <div className="px-5 py-4 border-b border-surface-border/60">
            <div className="text-helper text-surface-muted font-semibold uppercase tracking-wider">Daily breakdown</div>
          </div>
          {loading ? (
            <div className="px-5 py-10 text-center text-helper text-surface-muted">Loading…</div>
          ) : !visiblePeriod.length ? (
            <div className="px-5 py-10 text-center text-helper text-surface-muted">No entries</div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-[13px]">
                <thead>
                  <tr className="text-helper text-surface-muted uppercase tracking-wider border-b border-surface-border/60">
                  <th className="text-left font-semibold px-4 py-3">Date</th>
                  <th className="text-right font-semibold px-3 py-3">Cmpl</th>
                  <th className="text-right font-semibold px-3 py-3">Payout</th>
                  <th className="text-right font-semibold px-3 py-3">COD</th>
                  <th className="text-right font-semibold px-3 py-3">Online</th>
                  <th className="text-right font-semibold px-4 py-3">Tips</th>
                  </tr>
                </thead>
                <tbody>
                  {visiblePeriod.slice().reverse().map((d, i) => (
                    <tr
                      key={d.date ?? i}
                      className={cn(
                        'border-b border-surface-border/40 last:border-b-0',
                      )}
                    >
                      <td className="px-4 py-3">
                        <div className="text-body font-semibold text-white">
                          {d.date ? format(new Date(d.date + 'T00:00:00'), 'EEE, MMM d') : '—'}
                        </div>
                        <div className="text-helper text-surface-muted mt-0.5">{d.date ?? ''}</div>
                      </td>
                      <td className="px-3 py-3 text-right font-mono font-bold text-white">{d.completed}</td>
                      <td className="px-3 py-3 text-right font-mono font-bold text-cyan-300">{currencyPHP(d.payout_php ?? 0)}</td>
                      <td className="px-3 py-3 text-right font-mono font-bold text-cyan-300">{currencyPHP(d.cod_collected_php ?? 0)}</td>
                      <td className="px-3 py-3 text-right font-mono font-bold text-primary">{currencyPHP(d.online_confirmed_php ?? 0)}</td>
                      <td className="px-4 py-3 text-right font-mono font-bold text-cyan-300">{currencyPHP(d.tips_php ?? 0)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </section>
      </main>
    </div>
  );
}
