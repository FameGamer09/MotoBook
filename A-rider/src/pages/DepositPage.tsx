import { useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { format } from 'date-fns';
import { Button } from '@/components/ui/Button';
import { SwipeConfirmSlider } from '@/components/ui/SwipeConfirmSlider';
import { CameraCapture } from '@/components/CameraCapture';
import { LucideIcon, cn, currencyPHP } from '@/components/ui/Primitive';
import { useRiderStore } from '@/store/useRiderStore';
import { fetchJson } from '@/lib/api';
import type { DepositSubmitPayload, LedgerDayEntry, LedgerResponse, RiderDepositEntity } from '@/types/domain';

type CameraPayload = { dataUri: string; lat?: number | null; lng?: number | null; capturedAtMs: number } | null;

export function DepositPage() {
  const nav = useNavigate();
  const rider = useRiderStore((s) => s.profile);
  const todayStr = useMemo(() => format(new Date(), 'yyyy-MM-dd'), []);
  const todayLabel = useMemo(() => format(new Date(), 'EEEE, MMMM d, yyyy'), []);

  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const [submitOk, setSubmitOk] = useState(false);
  const [submitError, setSubmitError] = useState<string | null>(null);

  const [todayEntry, setTodayEntry] = useState<LedgerDayEntry | null>(null);
  const [existing, setExisting] = useState<RiderDepositEntity | null>(null);

  const [depositedCash, setDepositedCash] = useState<string>('0');
  const [depositNote, setDepositNote] = useState('');
  const [slipPayload, setSlipPayload] = useState<CameraPayload>(null);

  const codExpected = todayEntry?.cod_collected_php ?? 0;
  const onlineExpected = todayEntry?.online_confirmed_php ?? 0;
  const ordersCompleted = todayEntry?.completed ?? 0;
  const payoutExpected = (todayEntry?.payout_php ?? 0) + (todayEntry?.tips_php ?? 0);
  const totalExpected = codExpected + onlineExpected;

  useEffect(() => {
    async function load() {
      try {
        setLoading(true);
        setError(null);
        const { data, ok } = await fetchJson<LedgerResponse>('/session/ledger', { query: { days: 7 } });
        if (ok && data?.period) {
          const t = data.period.find((d) => d.date === todayStr) ?? null;
          setTodayEntry(t);
          if (t && t.cod_collected_php > 0) {
            setDepositedCash(String(t.cod_collected_php.toFixed(2)));
          }
        }
      } catch (e: any) {
        setError(e?.message ?? 'LOAD_FAILED');
      } finally {
        setLoading(false);
      }
    }
    void load();
  }, [todayStr]);

  const depositedNum = useMemo(() => {
    const n = parseFloat(depositedCash);
    return isFinite(n) && n >= 0 ? n : 0;
  }, [depositedCash]);

  const diff = depositedNum - codExpected;
  const mismatch = Math.abs(diff) > 0.005;

  const onSubmit = async () => {
    if (submitting || submitOk) return;
    setSubmitError(null);
    setSubmitting(true);
    try {
      const payload: DepositSubmitPayload = {
        cash_deposited_php: depositedNum,
        deposit_note: depositNote.trim() || undefined,
        deposit_slip_image_data: slipPayload?.dataUri ?? null,
        lat: slipPayload?.lat ?? null,
        lng: slipPayload?.lng ?? null,
        capturedAtMs: slipPayload?.capturedAtMs ?? null,
        shift_started_at: rider?.current_shift_started_at ?? null,
        shift_ended_at: rider?.status === 'OFFLINE' ? new Date().toISOString() : null,
      };
      const { data, ok } = await fetchJson<{ deposit: RiderDepositEntity }>('/session/deposit', {
        method: 'POST',
        body: JSON.stringify(payload),
      });
      if (ok && data?.deposit) {
        setExisting(data.deposit);
        setSubmitOk(true);
      } else {
        setSubmitError((data as any)?.error ?? 'SUBMIT_FAILED');
      }
    } catch (e: any) {
      setSubmitError(e?.message ?? 'SUBMIT_FAILED');
    } finally {
      setSubmitting(false);
    }
  };

  const canSubmit = !submitting && !submitOk && depositedNum > 0;

  return (
    <div className="min-h-full w-full bg-slate-950 pb-40">
      <header className="phone-top-sticky z-30 border-b border-slate-800/80 bg-slate-950/90 backdrop-blur">
        <div className="phone-col px-4 h-[60px] grid grid-cols-3 items-center">
          <div className="flex items-center">
            <Button
              variant="ghost"
              size="sm"
              onClick={() => nav(-1)}
              iconLeft={<LucideIcon name="ChevronLeft" size={20} />}
              className="h-10 w-10 !px-0"
            >
              Back
            </Button>
          </div>
          <h1 className="text-[16px] font-bold text-white text-center tracking-tight leading-none">End Shift</h1>
          <div className="flex justify-end items-center gap-2">
            <span className="inline-flex items-center gap-1.5 rounded-full bg-cyan-500/15 border border-cyan-600/30 px-2.5 py-1 text-[11px] font-bold text-cyan-300">
              <LucideIcon name="Calendar" size={12} /> {format(new Date(), 'MMM d')}
            </span>
          </div>
        </div>
      </header>

      <main className="phone-col px-4 pt-5 space-y-4">
        <section className="rounded-3xl border border-surface-border bg-surface-card shadow-card px-5 py-4">
          <div className="flex items-center justify-between">
            <div>
              <div className="text-helper text-surface-muted font-semibold uppercase tracking-wider">Shift date</div>
              <div className="text-body font-bold text-white mt-0.5">{todayLabel}</div>
            </div>
            <div className="h-12 w-12 rounded-2xl bg-primary-soft border border-primary/25 inline-flex items-center justify-center">
              <LucideIcon name="Landmark" size={22} strokeWidth={1.75} className="text-cyan-300" />
            </div>
          </div>
        </section>

        {loading ? (
          <section className="rounded-3xl border border-surface-border bg-surface-card shadow-card p-6 text-center text-helper text-surface-muted">Loading today's totals…</section>
        ) : error ? (
          <section className="rounded-3xl border border-slate-800/80 bg-surface-card p-4 flex items-start gap-3">
            <LucideIcon name="AlertTriangle" size={18} className="text-cyan-300 mt-0.5 flex-shrink-0" />
            <div>
              <div className="text-body font-bold text-white">Failed to load shift totals</div>
              <div className="text-helper text-slate-400 mt-0.5">{error}</div>
            </div>
          </section>
        ) : (
          <section className="rounded-3xl border border-surface-border bg-surface-card shadow-card overflow-hidden">
            <div className="px-5 pt-4 pb-2">
              <div className="text-helper text-surface-muted font-semibold uppercase tracking-wider">Expected collection</div>
            </div>
            <div className="px-5 pb-4 grid grid-cols-4 gap-2.5">
              {[
                { label: 'Cash COD', value: currencyPHP(codExpected), icon: 'Banknote', color: 'text-cyan-300', ring: 'border-cyan-700/40 bg-cyan-500/5' },
                { label: 'Online', value: currencyPHP(onlineExpected), icon: 'Smartphone', color: 'text-primary', ring: 'border-primary/25 bg-primary/10' },
                { label: 'Orders', value: String(ordersCompleted), icon: 'ClipboardList', color: 'text-cyan-200', ring: 'border-slate-700/80 bg-slate-900/50' },
                { label: 'Payout', value: currencyPHP(payoutExpected), icon: 'Wallet', color: 'text-cyan-300', ring: 'border-cyan-700/30 bg-slate-900/60' },
              ].map((s) => (
                <div key={s.label} className={cn('rounded-2xl border px-2.5 py-3 text-center', s.ring)}>
                  <div className="mx-auto h-8 w-8 rounded-xl bg-surface-dark/80 inline-flex items-center justify-center">
                    <LucideIcon name={s.icon as any} size={15} strokeWidth={1.75} className={s.color} />
                  </div>
                  <div className="mt-1.5 font-mono text-[13px] font-extrabold text-white truncate">{s.value}</div>
                  <div className="text-[10px] font-semibold uppercase tracking-[0.08em] text-surface-muted mt-0.5">{s.label}</div>
                </div>
              ))}
            </div>
            <div className="mx-5 mb-4 rounded-2xl bg-gradient-to-r from-slate-900 to-surface-dark border border-surface-border px-4 py-3 flex items-center justify-between">
              <div>
                <div className="text-[11px] font-semibold uppercase tracking-[0.08em] text-surface-muted">Total expected (COD + Online)</div>
              </div>
              <div className="font-mono font-extrabold text-[20px] text-white">{currencyPHP(totalExpected)}</div>
            </div>
          </section>
        )}

        <section className="rounded-3xl border border-surface-border bg-surface-card shadow-card overflow-hidden">
          <div className="px-5 pt-4 pb-2 flex items-center justify-between">
            <div>
              <div className="text-helper text-surface-muted font-semibold uppercase tracking-wider">Deposited cash</div>
              <div className="text-helper text-slate-400 mt-0.5">Amount you are remitting for today's COD</div>
            </div>
          </div>
          <div className="px-5 pb-4">
            <label className="block">
              <div className="sr-only">Deposited cash PHP</div>
              <div className="relative">
                <span className="absolute left-4 top-1/2 -translate-y-1/2 font-mono font-extrabold text-[22px] text-surface-muted pointer-events-none">₱</span>
                <input
                  type="number"
                  inputMode="decimal"
                  min={0}
                  step={0.01}
                  value={depositedCash}
                  onChange={(e) => setDepositedCash(e.target.value)}
                  className="w-full h-16 pl-12 pr-5 rounded-2xl text-right font-mono font-extrabold text-[26px] bg-surface-dark border border-surface-border text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950"
                  placeholder="0.00"
                />
              </div>
            </label>
            {depositedNum > 0 && (
              <div className="mt-3 flex items-center gap-2 flex-wrap">
                {mismatch ? (
                  <span className={cn(
                    'inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-[12px] font-bold border',
                    diff > 0
                      ? 'bg-cyan-500/10 border-cyan-500/30 text-cyan-200'
                      : 'bg-slate-900/80 border-cyan-700/40 text-cyan-100',
                  )}>
                    <LucideIcon name={diff > 0 ? 'TrendingUp' : 'AlertTriangle'} size={14} />
                    {diff > 0 ? `Over by ${currencyPHP(diff)}` : `Short by ${currencyPHP(-diff)}`} vs COD expected
                  </span>
                ) : (
                  <span className="inline-flex items-center gap-1.5 rounded-full bg-cyan-500/10 border border-cyan-500/30 text-cyan-200 px-3 py-1.5 text-[12px] font-bold">
                    <LucideIcon name="CheckCircle2" size={14} /> Matches expected COD
                  </span>
                )}
                <button
                  type="button"
                  onClick={() => setDepositedCash(codExpected.toFixed(2))}
                  className="text-helper font-semibold text-primary hover:text-cyan-200 inline-flex items-center gap-1"
                >
                  <LucideIcon name="RefreshCcw" size={12} /> Use expected
                </button>
              </div>
            )}
          </div>
        </section>

        <section className="rounded-3xl border border-surface-border bg-surface-card shadow-card overflow-hidden">
          <div className="px-5 pt-4 pb-2">
            <div className="text-helper text-surface-muted font-semibold uppercase tracking-wider">Remittance notes</div>
            <div className="text-helper text-slate-400 mt-0.5">Optional — discrepancies, explanations, etc.</div>
          </div>
          <div className="px-5 pb-4">
            <textarea
              value={depositNote}
              onChange={(e) => setDepositNote(e.target.value.slice(0, 500))}
              rows={3}
              placeholder="e.g. ₱50 short due to change given…"
              className="w-full rounded-2xl bg-surface-dark border border-surface-border text-body text-white px-4 py-3 resize-none focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950 placeholder:text-slate-500"
            />
            <div className="mt-1 text-right text-[11px] font-semibold text-surface-muted font-mono">{depositNote.length}/500</div>
          </div>
        </section>

        <section className="rounded-3xl border border-surface-border bg-surface-card shadow-card overflow-hidden">
          <div className="px-5 pt-4 pb-2 flex items-center gap-2">
            <div className="h-8 w-8 rounded-xl bg-cyan-500/10 border border-cyan-500/25 inline-flex items-center justify-center">
              <LucideIcon name="Receipt" size={15} strokeWidth={1.75} className="text-cyan-300" />
            </div>
            <div>
              <div className="text-body font-bold text-white">Deposit slip photo</div>
              <div className="text-helper text-slate-400">Required for verification · GPS + timestamp watermark</div>
            </div>
          </div>
          <div className="px-5 pb-4">
            <CameraCapture
              watermark
              aspectRatio="4:3"
              onChange={setSlipPayload}
              value={slipPayload?.dataUri ?? null}
            />
          </div>
        </section>

        {submitError ? (
          <section className="rounded-3xl border border-slate-800/80 bg-surface-card p-4 flex items-start gap-3">
            <LucideIcon name="AlertOctagon" size={18} className="text-cyan-300 mt-0.5 flex-shrink-0" />
            <div>
              <div className="text-body font-bold text-white">Deposit failed</div>
              <div className="text-helper text-slate-400 mt-0.5">{submitError}</div>
            </div>
          </section>
        ) : null}

        {submitOk && existing ? (
          <section className="rounded-3xl border border-cyan-500/40 bg-cyan-500/10 p-4 flex items-start gap-3">
            <LucideIcon name="CheckCircle2" size={20} className="text-cyan-300 mt-0.5 flex-shrink-0" />
            <div className="flex-1">
              <div className="text-body font-bold text-cyan-100">Deposit submitted — pending verification</div>
              <div className="mt-2 grid grid-cols-2 gap-2 text-[12px]">
                <div className="rounded-xl bg-surface-dark/70 border border-surface-border px-3 py-2">
                  <div className="text-surface-muted uppercase tracking-wider text-[10px] font-semibold">Deposited</div>
                  <div className="font-mono font-extrabold text-white mt-0.5">{currencyPHP(existing.deposited_cash_php)}</div>
                </div>
                <div className="rounded-xl bg-surface-dark/70 border border-surface-border px-3 py-2">
                  <div className="text-surface-muted uppercase tracking-wider text-[10px] font-semibold">Expected</div>
                  <div className="font-mono font-extrabold text-white mt-0.5">{currencyPHP(existing.total_expected_php)}</div>
                </div>
              </div>
              <Button variant="slate" size="block" className="mt-3" onClick={() => nav('/')} iconLeft={<LucideIcon name="Home" size={18} />}>
                Back to dashboard
              </Button>
            </div>
          </section>
        ) : (
          <section className="phone-bottom-bar z-20 bg-gradient-to-t from-slate-950 via-slate-950/95 to-slate-950/0 pt-8 pb-4 px-4 -mx-4 mb-[-112px]">
            <div className="phone-col space-y-3">
              {depositedNum <= 0 && !loading ? (
                <div className="rounded-2xl bg-slate-800 border border-cyan-700/30 px-4 py-2.5 flex items-center gap-2">
                  <LucideIcon name="AlertCircle" size={16} className="text-cyan-400 flex-shrink-0" />
                  <span className="text-helper font-semibold text-cyan-200">Enter an amount to deposit first</span>
                </div>
              ) : null}
              <SwipeConfirmSlider
                label="Submit Deposit"
                color="primary"
                icon="ArrowRightToLine"
                disabled={!canSubmit}
                onComplete={onSubmit}
              />
            </div>
          </section>
        )}
      </main>
    </div>
  );
}
