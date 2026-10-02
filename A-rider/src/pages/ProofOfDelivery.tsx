import { useMemo, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useRiderStore } from '@/store/useRiderStore';
import { useOrderStore } from '@/store/useOrderStore';
import { Button } from '@/components/ui/Button';
import { SwipeConfirmSlider } from '@/components/ui/SwipeConfirmSlider';
import { LucideIcon, cn, currencyPHP } from '@/components/ui/Primitive';
import { CameraCapture } from '@/components/CameraCapture';
import { SignaturePad } from '@/components/SignaturePad';

export function ProofOfDeliveryPage() {
  const rider = useRiderStore((s) => s.profile);
  const orders = useOrderStore((s) => s.list);
  const activeId = useOrderStore((s) => s.activeOrderId);
  const submitPOD = useOrderStore((s) => s.submitPOD);
  const transition = useOrderStore((s) => s.transition);
  const patch = useRiderStore((s) => s.patchProfile);
  const nav = useNavigate();

  const order = useMemo(() => orders.find((o) => o.id === activeId) ?? orders.find((o) =>
    ['ARRIVED_AT_DROP_OFF','PROOF_SUBMITTED','COMPLETED'].includes(o.state) && o.rider_id === rider?.id,
  ), [orders, activeId, rider?.id]);

  const [photo, setPhoto] = useState<{ dataUri: string; lat?: number | null; lng?: number | null; capturedAtMs: number } | null>(null);
  const [sig, setSig] = useState<string | null>(null);
  const [signerName, setSignerName] = useState<string>('');
  const [handoffMode, setHandoffMode] = useState<'DIRECT' | 'GATE_LEAVE' | 'NEIGHBOR' | 'LOCKER'>('DIRECT');
  const [codCollected, setCodCollected] = useState(true);
  const [cashReceived, setCashReceived] = useState<string>(order ? order.cod_amount.toFixed(2) : '');
  const [paymentProof, setPaymentProof] = useState<{ dataUri: string; lat?: number | null; lng?: number | null; capturedAtMs: number } | null>(null);
  const [paymentRef, setPaymentRef] = useState<string>(order?.payment_reference ?? '');
  const [paymentConfirmed, setPaymentConfirmed] = useState(false);
  const [photoOpen, setPhotoOpen] = useState(true);
  const [sigOpen, setSigOpen] = useState(true);

  const codAmount = order?.cod_amount ?? 0;
  const payMethod = order?.payment_method ?? 'COD';
  const isCOD = payMethod === 'COD' || codAmount > 0;
  const received = parseFloat(cashReceived.replace(/,/g, '') || '0');
  const changeDue = isCOD && codCollected ? Math.max(0, received - codAmount) : 0;
  const shortBy = isCOD && codCollected ? Math.max(0, codAmount - received) : 0;

  const payLabel = useMemo(() => (payMethod || 'COD').toString().replaceAll('_', ' '), [payMethod]);
  const payStatus = order?.payment_status ?? 'UNPAID';
  const payStatusLabel = payStatus.replaceAll('_', ' ');

  const complete = async () => {
    if (!order) return;
    const payload = {
      photoUri: photo?.dataUri ?? null,
      photoLat: photo?.lat ?? null,
      photoLng: photo?.lng ?? null,
      signatureUri: handoffMode === 'DIRECT' ? sig : null,
      signerName: handoffMode === 'DIRECT' ? signerName.trim() : null,
      handoffMode,
      codCollected: isCOD ? codCollected : false,
      cashReceived: isCOD && codCollected ? received : 0,
      changeDue,
      payment_method: payMethod,
      payment_reference: paymentRef.trim() || null,
      payment_confirmed: !isCOD && paymentConfirmed,
      paymentProofUri: paymentProof?.dataUri ?? null,
      paymentProofLat: paymentProof?.lat ?? null,
      paymentProofLng: paymentProof?.lng ?? null,
      paymentProofCapturedAtMs: paymentProof?.capturedAtMs ?? null,
    };
    await submitPOD(order.id, payload);
    await transition(order.id, 'COMPLETED');
    patch({
      duty_today_payout: (rider?.duty_today_payout ?? 0) + (order.payout_amount + order.tip_amount),
      completed_today: (rider?.completed_today ?? 0) + 1,
    });
    nav('/', { replace: true });
  };

  const directValid = handoffMode !== 'DIRECT' || (!!sig && signerName.trim().length >= 2);
  const codValid = !isCOD || !codCollected || shortBy === 0;
  const photoValid = !!photo;
  const paymentProofValid = !!paymentProof;
  const paymentValid = isCOD
    ? codValid
    : (paymentConfirmed || paymentProofValid || paymentRef.trim().length >= 6);
  const canComplete = photoValid && directValid && (isCOD ? codValid : paymentValid);

  if (!order) {
    return (
      <div className="min-h-full w-full bg-slate-950">
        <div className="phone-col px-4 py-6 text-white space-y-3">
          <Link to="/delivery" className="inline-flex items-center gap-1.5 min-h-[48px] px-3 rounded-xl border border-surface-border bg-surface-card">
            <LucideIcon name="ChevronLeft" size={18} /> Back to delivery
          </Link>
          <h2 className="text-screen-title font-semibold">No drop-off in progress</h2>
          <p className="text-body text-slate-300">Arrive at the customer first to capture proof of delivery.</p>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-full w-full bg-slate-950 pb-28">
      <header className="phone-top-sticky z-30 bg-slate-950/92 backdrop-blur border-b border-slate-800/70">
        <div className="phone-col px-4 h-[60px] grid grid-cols-[auto_1fr] items-center gap-3">
          <Link to="/delivery" className="min-h-[48px] min-w-[48px] h-10 w-10 rounded-2xl border border-slate-800/80 bg-surface-card inline-flex items-center justify-center text-slate-300 hover:bg-slate-800">
            <LucideIcon name="ChevronLeft" size={19} strokeWidth={2} />
          </Link>
          <div className="text-center pr-12">
            <h1 className="text-[16px] font-bold text-white tracking-tight leading-none">Delivery Confirmation</h1>
          </div>
        </div>
      </header>

      <main className="phone-col px-4 pt-5 space-y-4">
        <div className="flex flex-col items-center text-center py-5">
          <div className="relative">
            <div className="absolute -inset-4 rounded-full bg-cyan-500/10 animate-pulse" />
            <div className="relative h-24 w-24 rounded-[28px] bg-gradient-to-br from-cyan-500/15 to-cyan-700/10 border border-cyan-500/30 inline-flex items-center justify-center">
              <LucideIcon name="Package" size={46} strokeWidth={1.75} className="text-cyan-300" />
              <div className="absolute -right-2 -bottom-2 h-10 w-10 rounded-2xl bg-cyan-500/15 border border-cyan-500/30 inline-flex items-center justify-center">
                <LucideIcon name="CheckCircle2" size={20} strokeWidth={1.75} className="text-cyan-300" />
              </div>
            </div>
          </div>
          <p className="mt-5 text-[13px] font-bold uppercase tracking-[0.08em] text-cyan-300">Confirm delivery to</p>
          <p className="mt-1 text-[20px] font-extrabold text-white leading-tight">{order.dropoff_name || 'Customer'}</p>
        </div>

        <div className="rounded-2xl border border-slate-800/80 bg-surface-card divide-y divide-slate-800/70 overflow-hidden">
          {[
            { k: 'order_id', label: 'ORDER ID', val: <span className="font-mono text-[14px] font-extrabold text-white">{order.order_code}</span> },
            { k: 'payment', label: 'PAYMENT METHOD', val: <span className="text-[14px] font-bold text-cyan-300 inline-flex items-center gap-1.5"><LucideIcon name={order.payment_method === 'COD' ? 'Banknote' : 'Smartphone'} size={14} />{payLabel}</span> },
            { k: 'amount', label: isCOD ? 'AMOUNT COLLECTED' : 'AMOUNT TO COLLECT', val: <span className="font-mono text-[15px] font-extrabold text-white">{currencyPHP(isCOD ? codAmount : order.payout_amount + order.tip_amount)}</span> },
          ].map((r) => (
            <div key={r.k} className="px-4 py-3.5 grid grid-cols-[120px_1fr] items-center gap-3">
              <div className="text-[10px] font-bold uppercase tracking-[0.12em] text-slate-500">{r.label}</div>
              <div className="text-right">{r.val}</div>
            </div>
          ))}
        </div>

        {isCOD ? (
          <div className="rounded-2xl border border-slate-800/80 bg-surface-card p-4 space-y-3">
            <div className="flex items-center justify-between gap-3">
              <div className="inline-flex items-center gap-2">
                <LucideIcon name="BadgeDollarSign" size={15} className="text-cyan-300" />
                <h3 className="text-[12px] font-bold uppercase tracking-[0.1em] text-slate-300">Cash Reconciliation</h3>
              </div>
              <label className="inline-flex items-center gap-2 px-3 h-10 rounded-xl border border-slate-700/80 bg-slate-900/60 cursor-pointer">
                <input
                  type="checkbox"
                  checked={codCollected}
                  onChange={(e) => setCodCollected(e.target.checked)}
                  className="h-4 w-4 accent-cyan-500"
                />
                <span className="text-[13px] font-bold text-white">Collected</span>
              </label>
            </div>
            <div className="grid grid-cols-3 gap-2.5">
              <div className="rounded-xl border border-slate-800/80 bg-slate-900/60 px-3 py-3">
                <div className="text-[9px] font-bold uppercase tracking-[0.12em] text-slate-500">Billed</div>
                <div className="mt-1 font-mono text-[16px] font-extrabold text-white leading-none">{currencyPHP(codAmount)}</div>
              </div>
              <div className="rounded-xl border border-slate-800/80 bg-slate-900/60 px-3 py-3">
                <div className="text-[9px] font-bold uppercase tracking-[0.12em] text-slate-500">Received</div>
                <input
                  inputMode="decimal"
                  value={cashReceived}
                  disabled={!codCollected}
                  onChange={(e) => setCashReceived(e.target.value)}
                  className={cn(
                    'mt-1 w-full bg-transparent font-mono text-[16px] font-extrabold text-white outline-none leading-none',
                    !codCollected && 'opacity-50',
                  )}
                />
              </div>
              <div className={cn(
                'rounded-xl border px-3 py-3',
                shortBy > 0 ? 'border-cyan-700/50 bg-slate-900/80' : changeDue > 0 ? 'border-cyan-600/40 bg-slate-800/70' : 'border-cyan-500/40 bg-cyan-900/20',
              )}>
                <div className="text-[9px] font-bold uppercase tracking-[0.12em] text-slate-400">
                  {shortBy > 0 ? 'Short' : changeDue > 0 ? 'Change' : 'Exact'}
                </div>
                <div className={cn(
                  'mt-1 font-mono text-[16px] font-extrabold leading-none',
                  shortBy > 0 ? 'text-cyan-200' : changeDue > 0 ? 'text-cyan-100' : 'text-white',
                )}>
                  {shortBy > 0 ? '-' : ''}{currencyPHP(shortBy || changeDue)}
                </div>
              </div>
            </div>
            {shortBy > 0 ? (
              <div className="inline-flex items-center gap-2 rounded-xl border border-cyan-700/40 bg-slate-900/70 px-3 py-2 text-[12px] font-bold text-cyan-200">
                <LucideIcon name="AlertCircle" size={14} /> Short by {currencyPHP(shortBy)}
              </div>
            ) : null}
          </div>
        ) : (
          <div className="rounded-2xl border border-cyan-700/30 bg-cyan-500/5 p-4 space-y-3">
            <div className="flex items-center gap-2">
              <span className="h-9 w-9 rounded-xl bg-cyan-500/15 border border-cyan-500/30 inline-flex items-center justify-center">
                <LucideIcon name="Smartphone" size={17} className="text-cyan-300" />
              </span>
              <div>
                <div className="text-[10px] font-bold uppercase tracking-[0.12em] text-cyan-300">Payment · {payLabel}</div>
                <div className="text-[12px] font-bold text-cyan-100 mt-0.5">Confirm payment received below</div>
              </div>
              <div className="ml-auto">
                <span className={cn(
                  'inline-flex items-center gap-1 px-2.5 h-7 rounded-full text-[10px] font-extrabold border',
                  payStatus === 'PAID' ? 'bg-cyan-600 text-white border-cyan-700' :
                  payStatus === 'PENDING_VERIFICATION' ? 'bg-slate-500 text-white border-slate-600' :
                  'bg-slate-600 text-white border-slate-700',
                )}>
                  {payStatusLabel}
                </span>
              </div>
            </div>
            <div className="rounded-xl border border-slate-800/80 bg-surface-card px-3 py-3 space-y-2">
              <label className="text-[9px] font-bold uppercase tracking-[0.12em] text-slate-500 block">Reference #</label>
              <input
                type="text"
                value={paymentRef}
                onChange={(e) => setPaymentRef(e.target.value)}
                placeholder="G-XXXXXXXXX"
                className="w-full bg-transparent font-mono text-[15px] font-bold text-white outline-none placeholder:text-slate-500"
                autoComplete="off"
              />
            </div>
            <label className="inline-flex items-center gap-2.5 px-3.5 h-12 rounded-xl border border-slate-800/80 bg-surface-card cursor-pointer w-full">
              <input
                type="checkbox"
                checked={paymentConfirmed}
                onChange={(e) => setPaymentConfirmed(e.target.checked)}
                className="h-4 w-4 accent-cyan-500"
              />
              <span className="text-[13px] font-bold text-white">I confirm the customer has paid</span>
            </label>
          </div>
        )}

        <div>
          <h3 className="text-[12px] font-bold uppercase tracking-[0.08em] text-slate-400 px-1 mb-2.5">Proof of Delivery</h3>
          <div className="grid grid-cols-2 gap-3">
            <button
              type="button"
              onClick={() => setPhotoOpen((v) => !v)}
              className={cn(
                'rounded-2xl border p-4 flex flex-col items-center justify-center gap-2 text-center min-h-[120px] transition',
                photoValid
                  ? 'border-cyan-500/40 bg-cyan-500/5'
                  : 'border-slate-800/80 bg-surface-card hover:bg-slate-800/60',
              )}
            >
              <span className={cn(
                'h-12 w-12 rounded-2xl border inline-flex items-center justify-center',
                photoValid ? 'border-cyan-500/40 bg-cyan-500/15 text-cyan-300' : 'border-slate-700/80 bg-slate-900/60 text-slate-400',
              )}>
                <LucideIcon name="Camera" size={22} strokeWidth={1.75} />
              </span>
              <div>
                <div className={cn('text-[13px] font-bold leading-tight', photoValid ? 'text-cyan-200' : 'text-white')}>
                  Take Photo
                </div>
                <div className={cn('text-[10px] font-semibold mt-0.5', photoValid ? 'text-cyan-400' : 'text-slate-500')}>
                  {photoValid ? 'Captured · REQUIRED' : 'Required'}
                </div>
              </div>
            </button>
            <button
              type="button"
              onClick={() => setSigOpen((v) => !v)}
              className={cn(
                'rounded-2xl border p-4 flex flex-col items-center justify-center gap-2 text-center min-h-[120px] transition',
                directValid
                  ? 'border-cyan-500/40 bg-cyan-500/5'
                  : 'border-slate-800/80 bg-surface-card hover:bg-slate-800/60',
              )}
              disabled={handoffMode !== 'DIRECT'}
            >
              <span className={cn(
                'h-12 w-12 rounded-2xl border inline-flex items-center justify-center',
                directValid ? 'border-cyan-500/40 bg-cyan-500/15 text-cyan-300' : 'border-slate-700/80 bg-slate-900/60 text-slate-400',
              )}>
                <LucideIcon name="Signature" size={22} strokeWidth={1.75} />
              </span>
              <div>
                <div className={cn('text-[13px] font-bold leading-tight', directValid ? 'text-cyan-200' : 'text-white')}>
                  Customer Signature
                </div>
                <div className={cn('text-[10px] font-semibold mt-0.5', directValid ? 'text-cyan-400' : 'text-slate-500')}>
                  {handoffMode === 'DIRECT' ? (directValid ? 'Signed · REQUIRED' : 'Required for direct') : 'N/A · Photo-only'}
                </div>
              </div>
            </button>
          </div>
          {photoOpen || !photoValid ? (
            <CameraCapture
              value={photo?.dataUri ?? null}
              onChange={(p) => setPhoto(p)}
              required
              watermark
              className="mt-3"
            />
          ) : null}
          {handoffMode === 'DIRECT' && (sigOpen || !directValid) ? (
            <div className="mt-3">
              <SignaturePad
                value={sig}
                onChange={setSig}
                signerName={signerName}
                onSignerNameChange={setSignerName}
                required
                aspectRatio="5:3"
                minPoints={10}
              />
            </div>
          ) : null}
        </div>

        {order.special_notes ? (
          <div className="rounded-2xl border border-cyan-700/30 bg-cyan-500/5 px-4 py-3">
            <div className="flex items-center gap-2 mb-1">
              <LucideIcon name="MessageSquareText" size={14} className="text-cyan-300" />
              <span className="text-[10px] font-bold uppercase tracking-[0.12em] text-cyan-300">Special Notes</span>
            </div>
            <div className="text-[13px] font-semibold text-cyan-100 leading-snug">“{order.special_notes}”</div>
          </div>
        ) : null}
      </main>

      <div className="phone-bottom-bar z-20 bg-gradient-to-t from-slate-950 via-slate-950/95 to-slate-950/0 pt-8 pb-4 px-4">
        <div className="phone-col space-y-2.5">
          <div className="flex items-center justify-between text-[10px] font-bold uppercase tracking-[0.1em] text-slate-500 px-1">
            <span>Final step · complete delivery</span>
            <span className={cn(canComplete ? 'text-cyan-300' : 'text-slate-500')}>
              {canComplete ? 'Ready' : 'Complete requirements'}
            </span>
          </div>
          {canComplete ? (
            <SwipeConfirmSlider
              label="Complete Delivery"
              color="primary"
              onComplete={complete}
              icon="CheckCircle2"
            />
          ) : (
            <Button variant="slate" size="block" disabled className="h-14 rounded-2xl text-[15px] font-bold border-slate-700/80">
              {!photoValid
                ? 'Capture proof photo first'
                : !directValid
                  ? 'Signature + signatory name required'
                  : isCOD
                    ? (shortBy > 0 ? 'Resolve COD short amount' : 'Mark cash as collected')
                    : (!paymentConfirmed && !paymentProofValid && paymentRef.trim().length < 6)
                      ? 'Confirm payment or paste ref #'
                      : 'Review requirements'}
            </Button>
          )}
          <div className="px-1 text-[11px] text-slate-500 font-medium leading-snug text-center">
            By completing above you confirm hand-off &amp; correct collection per policy.
          </div>
        </div>
      </div>
    </div>
  );
}
