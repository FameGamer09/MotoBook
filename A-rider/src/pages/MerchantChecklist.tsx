import { useMemo, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useRiderStore } from '@/store/useRiderStore';
import { useOrderStore } from '@/store/useOrderStore';
import { Button } from '@/components/ui/Button';
import { LucideIcon, cn, currencyPHP } from '@/components/ui/Primitive';
import { CameraCapture } from '@/components/CameraCapture';
import type { IncidentCode, OrderItem } from '@/types/domain';

const INCIDENT_OPTIONS: { code: IncidentCode; label: string }[] = [
  { code: 'STORE_CLOSED', label: 'Store is closed' },
  { code: 'LONG_STORE_WAIT_15', label: 'Long store wait (> 15 mins)' },
  { code: 'ORDER_MISSING', label: 'Order missing from merchant system' },
  { code: 'ITEM_UNAVAILABLE', label: 'One or more items unavailable' },
  { code: 'MERCHANT_CONGESTION', label: 'Heavy traffic / congestion near store' },
  { code: 'ITEM_DAMAGED', label: 'Item damaged on receipt' },
  { code: 'OTHER', label: 'Other issue (describe)' },
];

function ItemRow({
  item, checked, onToggle, disabled,
}: { item: OrderItem; checked: boolean; onToggle: () => void; disabled?: boolean }) {
  return (
    <button
      type="button"
      onClick={() => { if (!disabled) onToggle(); }}
      disabled={disabled}
      className={cn(
        'w-full text-left grid grid-cols-[auto_1fr_auto] gap-3 items-start px-4 py-3.5 rounded-2xl border transition',
        'bg-surface-card/80 hover:bg-surface-card active:bg-slate-800',
        checked ? 'border-cyan-500/50 ring-1 ring-cyan-500/20 bg-cyan-500/5' : 'border-surface-border',
        disabled ? 'opacity-80' : '',
      )}
      aria-pressed={checked}
    >
      <span
        className={cn(
          'mt-0.5 h-7 w-7 rounded-lg border inline-flex items-center justify-center transition',
          checked ? 'bg-cyan-500 border-cyan-500 text-slate-950' : 'border-slate-600 bg-surface-panel text-transparent',
        )}
      >
        <LucideIcon name="Check" size={16} strokeWidth={2.5} />
      </span>
      <div>
        <div className="flex items-start gap-2">
          <span className="font-mono text-[15px] font-extrabold text-white leading-tight">{item.qty ?? 1}×</span>
          <div className="text-[15px] font-semibold text-white leading-tight">{item.name}</div>
        </div>
        {item.options && item.options.length ? (
          <ul className="mt-1.5 space-y-1">
            {item.options.map((opt, i) => (
              <li key={i} className="text-[13px] font-medium text-slate-300">
                · <span className="font-semibold">{opt.group}</span>: {opt.name}
                {opt.price_delta ? <span className="ml-2 text-slate-400">+{currencyPHP(opt.price_delta)}</span> : null}
              </li>
            ))}
          </ul>
        ) : null}
        {item.special_instructions ? (
          <div className="mt-2 flex flex-wrap gap-2">
            {item.special_instructions.split(/,|;|\//).map((t, i) => {
              const tok = t.trim().toUpperCase();
              if (!tok) return null;
              const isCritical = /NO|LESS|EXTRA|WITHOUT|NO ICE|NO MAYO|NO ONION|NO SUGAR|EXTRA RICE|LESS SALT/.test(tok);
              return (
                <span
                  key={i}
                  className={cn(
                    'inline-flex items-center rounded-md px-2.5 py-1 text-[12px] font-extrabold tracking-wider',
                    isCritical ? 'bg-cyan-500/15 text-cyan-200 border border-cyan-500/40' : 'bg-slate-800/70 text-slate-200 border border-surface-border',
                  )}
                >
                  {tok}
                </span>
              );
            })}
          </div>
        ) : null}
        {item.unit_price ? (
          <div className="mt-2 font-mono text-[13px] font-semibold text-slate-400">
            Subtotal {currencyPHP((item.qty ?? 1) * (item.unit_price ?? 0))}
          </div>
        ) : null}
      </div>
      <LucideIcon
        name={checked ? 'CheckCircle2' : 'Circle'}
        size={20}
        strokeWidth={1.75}
        className={cn('mt-1', checked ? 'text-cyan-400' : 'text-slate-500')}
      />
    </button>
  );
}

export function MerchantChecklistPage() {
  const rider = useRiderStore((s) => s.profile);
  const orders = useOrderStore((s) => s.list);
  const activeId = useOrderStore((s) => s.activeOrderId);
  const transition = useOrderStore((s) => s.transition);
  const reportIncident = useOrderStore((s) => s.reportIncident);
  const nav = useNavigate();

  const order = useMemo(() => orders.find((o) => o.id === activeId) ?? orders.find((o) =>
    ['ARRIVED_AT_PICKUP','ORDER_VERIFIED','NAVIGATING_TO_DROP_OFF'].includes(o.state) && o.rider_id === rider?.id,
  ), [orders, activeId, rider?.id]);

  const [checked, setChecked] = useState<Record<string, boolean>>({});
  const [overrideKey, setOverrideKey] = useState('');
  const [incidentOpen, setIncidentOpen] = useState(false);
  const [incidentCode, setIncidentCode] = useState<IncidentCode | null>(null);
  const [incidentNotes, setIncidentNotes] = useState('');
  const [incidentReported, setIncidentReported] = useState(false);
  const [photoOpen, setPhotoOpen] = useState(false);
  const [pickupPhoto, setPickupPhoto] = useState<{ dataUri: string } | null>(null);

  const items = order?.items ?? [];
  const allChecked = items.length > 0 && items.every((i, idx) => checked[`${i.id || i.name}-${idx}`]);
  const overrideOk = overrideKey.trim() === 'MB-OVERRIDE-2024';

  const checklistItems = useMemo(() => ([
    { key: 'order_complete', label: 'Order items complete', mandatory: true },
    { key: 'condition_ok', label: 'Items are in good condition', mandatory: true },
    { key: 'bag_sealed', label: 'Order bag is sealed', mandatory: true },
    { key: 'receipt', label: 'Receipt (if available)', mandatory: false },
  ]), []);

  const checklistRequiredOk = checklistItems.filter((c) => c.mandatory).every((c) => checked[c.key]);
  const itemRowsValid = items.length === 0 || allChecked;
  const canConfirmPickup = (checklistRequiredOk || overrideOk) && itemRowsValid;

  const toggle = (i: number, it: OrderItem) => {
    const key = `${it.id || it.name}-${i}`;
    setChecked((s) => ({ ...s, [key]: !s[key] }));
  };

  const confirmPicked = async () => {
    if (!order || !canConfirmPickup) return;
    if (order.state !== 'ORDER_VERIFIED') await transition(order.id, 'ORDER_VERIFIED');
    nav('/delivery');
  };

  const submitIncident = async () => {
    if (!order || !incidentCode) return;
    await reportIncident(order.id, incidentCode, incidentNotes || undefined);
    setIncidentReported(true);
    setTimeout(() => { setIncidentOpen(false); setIncidentReported(false); setIncidentCode(null); setIncidentNotes(''); }, 1200);
  };

  if (!order) {
    return (
      <div className="min-h-full w-full bg-slate-950">
        <div className="phone-col px-4 py-6 text-white space-y-3">
          <Link to="/delivery" className="inline-flex items-center gap-1.5 min-h-[48px] px-3 rounded-xl border border-surface-border bg-surface-card">
            <LucideIcon name="ChevronLeft" size={18} /> Back to delivery
          </Link>
          <h2 className="text-screen-title font-semibold">No active order</h2>
          <p className="text-body text-slate-300">Accept a delivery first to access the merchant checklist.</p>
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
            <h1 className="text-[16px] font-bold text-white tracking-tight leading-none">Pickup Confirmation</h1>
          </div>
        </div>
      </header>

      <main className="phone-col px-4 pt-5 space-y-4">
        <div className="flex flex-col items-center text-center py-5">
          <div className="relative">
            <div className="absolute -inset-4 rounded-full bg-cyan-500/10 animate-pulse" />
            <div className="relative h-24 w-24 rounded-[28px] bg-gradient-to-br from-cyan-500/15 to-cyan-700/10 border border-cyan-500/30 inline-flex items-center justify-center">
              <LucideIcon name="ClipboardCheck" size={46} strokeWidth={1.75} className="text-cyan-300" />
              <div className="absolute -right-2 -bottom-2 h-10 w-10 rounded-2xl bg-cyan-500/15 border border-cyan-500/30 inline-flex items-center justify-center">
                <LucideIcon name="ShoppingBag" size={20} strokeWidth={1.75} className="text-cyan-300" />
              </div>
            </div>
          </div>
          <p className="mt-5 text-[15px] font-bold text-white max-w-[260px] leading-snug">
            Confirm you have picked up the order from the store.
          </p>
        </div>

        <div className="rounded-2xl border border-slate-800/80 bg-surface-card divide-y divide-slate-800/70 overflow-hidden">
          {[
            { k: 'order_id', label: 'ORDER ID', val: <span className="font-mono text-[14px] font-extrabold text-white">{order.order_code}</span> },
            { k: 'customer', label: 'CUSTOMER', val: <span className="text-[14px] font-bold text-white">{order.dropoff_name || 'Customer'}</span> },
            { k: 'payment', label: 'PAYMENT METHOD', val: <span className="text-[14px] font-bold text-cyan-300 inline-flex items-center gap-1.5"><LucideIcon name={order.payment_method === 'COD' ? 'Banknote' : 'Smartphone'} size={14} />{order.payment_method}</span> },
            { k: 'amount', label: 'AMOUNT TO COLLECT', val: <span className="font-mono text-[15px] font-extrabold text-white">{currencyPHP(order.cod_amount > 0 ? order.cod_amount : order.payout_amount + order.tip_amount)}</span> },
          ].map((r) => (
            <div key={r.k} className="px-4 py-3.5 grid grid-cols-[120px_1fr] items-center gap-3">
              <div className="text-[10px] font-bold uppercase tracking-[0.12em] text-slate-500">{r.label}</div>
              <div className="text-right">{r.val}</div>
            </div>
          ))}
        </div>

        <div>
          <h3 className="text-[13px] font-bold uppercase tracking-[0.08em] text-slate-400 px-1 mb-2.5">Pickup Checklist</h3>
          <div className="rounded-2xl border border-slate-800/80 bg-surface-card divide-y divide-slate-800/70 overflow-hidden">
            {checklistItems.map((c) => {
              const v = !!checked[c.key];
              return (
                <button
                  key={c.key}
                  type="button"
                  onClick={() => setChecked((s) => ({ ...s, [c.key]: !s[c.key] }))}
                  className="w-full px-4 py-3.5 grid grid-cols-[auto_1fr_auto] items-center gap-3 text-left hover:bg-slate-800/40 transition"
                  aria-pressed={v}
                >
                  <span
                    className={cn(
                      'h-6 w-6 rounded-full border-2 inline-flex items-center justify-center transition',
                      v ? 'bg-cyan-500 border-cyan-500' : 'border-slate-600',
                    )}
                  >
                    {v ? <LucideIcon name="Check" size={13} strokeWidth={3} className="text-slate-950" /> : null}
                  </span>
                  <span className={cn('text-[14px] font-semibold leading-tight', v ? 'text-cyan-200' : 'text-white')}>
                    {c.label}
                    {c.mandatory ? <span className="ml-2 text-[11px] font-bold uppercase tracking-wide text-cyan-400">required</span> : null}
                  </span>
                  <LucideIcon
                    name={v ? 'CheckCircle2' : 'Circle'}
                    size={18}
                    strokeWidth={1.75}
                    className={v ? 'text-cyan-400' : 'text-slate-600'}
                  />
                </button>
              );
            })}
          </div>
        </div>

        {items.length > 0 ? (
          <div className="rounded-2xl border border-slate-800/80 bg-surface-card overflow-hidden">
            <div className="px-4 py-3 flex items-center justify-between border-b border-slate-800/70">
              <div className="inline-flex items-center gap-2">
                <LucideIcon name="ClipboardList" size={15} className="text-cyan-400" />
                <h3 className="text-[12px] font-bold uppercase tracking-[0.1em] text-slate-300">Item Verification</h3>
              </div>
              <span className="text-[11px] font-bold font-mono text-slate-400">
                {items.filter((i, idx) => checked[`${i.id || i.name}-${idx}`]).length}/{items.length}
              </span>
            </div>
            <div className="px-2 pb-2 space-y-0.5 max-h-[240px] overflow-y-auto">
              {items.map((it, idx) => (
                <ItemRow
                  key={`${it.id || it.name}-${idx}`}
                  item={it}
                  checked={!!checked[`${it.id || it.name}-${idx}`]}
                  onToggle={() => toggle(idx, it)}
                />
              ))}
            </div>
          </div>
        ) : null}

        <div className="rounded-2xl border border-slate-800/80 bg-surface-card p-3.5 space-y-3">
          <button
            type="button"
            onClick={() => setPhotoOpen((v) => !v)}
            className="w-full h-14 rounded-xl border border-dashed border-slate-700/80 bg-slate-900/60 hover:bg-slate-800/60 transition inline-flex items-center justify-center gap-2.5 group"
          >
            <span className="h-9 w-9 rounded-xl bg-cyan-500/10 border border-cyan-500/30 inline-flex items-center justify-center text-cyan-300 group-hover:bg-cyan-500/20 transition">
              <LucideIcon name="Camera" size={17} strokeWidth={1.75} />
            </span>
            <div className="text-left">
              <div className="text-[14px] font-bold text-white">Take Photo</div>
              <div className="text-[11px] font-semibold text-slate-400">
                {pickupPhoto ? 'Captured · optional' : '(Optional)'}
              </div>
            </div>
          </button>
          {photoOpen ? (
            <CameraCapture
              value={pickupPhoto?.dataUri ?? null}
              onChange={(p) => setPickupPhoto(p ? { dataUri: p.dataUri } : null)}
              watermark
            />
          ) : null}
        </div>

        <div className="rounded-2xl border border-slate-800/80 bg-surface-card overflow-hidden">
          <button
            type="button"
            onClick={() => setIncidentOpen((v) => !v)}
            className="w-full px-4 py-3.5 flex items-center justify-between gap-3 hover:bg-slate-800/40 transition"
          >
            <span className="inline-flex items-center gap-2">
              <LucideIcon name="Flag" size={15} className="text-slate-400" />
              <span className="text-[13px] font-bold text-white">Report an issue</span>
            </span>
            <LucideIcon name={incidentOpen ? 'ChevronUp' : 'ChevronDown'} size={16} className="text-slate-500" />
          </button>
          {incidentOpen ? (
            <div className="px-4 pb-4 space-y-2 border-t border-slate-800/70 pt-3">
              {INCIDENT_OPTIONS.map((opt) => (
                <button
                  key={opt.code}
                  type="button"
                  onClick={() => setIncidentCode(opt.code)}
                  className={cn(
                    'w-full text-left rounded-xl border px-3 py-2.5 text-[13px] font-semibold transition',
                    incidentCode === opt.code
                      ? 'border-cyan-500/50 bg-cyan-500/10 text-cyan-100'
                      : 'border-slate-800/80 bg-slate-900/50 text-slate-200 hover:bg-slate-800/60',
                  )}
                >
                  {opt.label}
                </button>
              ))}
              <textarea
                value={incidentNotes}
                onChange={(e) => setIncidentNotes(e.target.value)}
                rows={2}
                placeholder="Optional notes"
                className="w-full rounded-xl bg-slate-900/70 border border-slate-700/80 text-[13px] text-white px-3 py-2 resize-none"
              />
              <Button
                variant="primary"
                size="md"
                className="w-full rounded-xl"
                disabled={!incidentCode}
                onClick={submitIncident}
              >
                {incidentReported ? 'Reported' : 'Submit report'}
              </Button>
            </div>
          ) : null}
        </div>

        {overrideKey.trim().length > 0 || !canConfirmPickup ? (
          <div className="rounded-2xl border border-slate-800/80 bg-surface-card p-4 space-y-3">
            <div className="flex items-center justify-between gap-3">
              <div className="inline-flex items-center gap-2">
                <LucideIcon name="KeyRound" size={15} className="text-slate-500" />
                <h4 className="text-[12px] font-bold uppercase tracking-[0.1em] text-slate-400">Admin Override</h4>
              </div>
              <span className={cn('text-[10px] font-bold uppercase tracking-wider', overrideOk ? 'text-cyan-300' : 'text-slate-500')}>
                {overrideOk ? 'GRANTED' : 'LOCKED'}
              </span>
            </div>
            <input
              type="text"
              autoComplete="off"
              value={overrideKey}
              onChange={(e) => setOverrideKey(e.target.value)}
              placeholder="Override key for missing items"
              className={cn(
                'w-full h-12 rounded-xl border bg-slate-900/70 px-4 text-[14px] font-semibold text-white placeholder:text-slate-500',
                overrideOk ? 'border-cyan-600 focus:ring-2 focus:ring-cyan-500' : 'border-slate-700/80 focus:outline-none focus:ring-2 focus:ring-cyan-500/60',
              )}
            />
          </div>
        ) : null}
      </main>

      <div className="phone-bottom-bar z-20 bg-gradient-to-t from-slate-950 via-slate-950/95 to-slate-950/0 pt-8 pb-4 px-4">
        <div className="phone-col space-y-2.5">
          <div className="flex items-center justify-between text-[11px] font-bold uppercase tracking-[0.08em] text-slate-400 px-1">
            <span>Finalize pickup</span>
            {order.cod_amount > 0 ? (
              <span className="text-cyan-300 font-mono">COD {currencyPHP(order.cod_amount)}</span>
            ) : null}
          </div>
          <Button
            variant="primary"
            size="block"
            className="h-14 rounded-2xl text-[15px] font-bold shadow-[0_12px_30px_-10px_rgba(6,182,212,0.5)]"
            iconLeft={<LucideIcon name="PackageCheck" size={18} />}
            disabled={!canConfirmPickup}
            onClick={confirmPicked}
          >
            Confirm Pickup
          </Button>
        </div>
      </div>
    </div>
  );
}
