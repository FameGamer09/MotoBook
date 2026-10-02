import { useCallback, useEffect, useRef, useState } from 'react';
import { Button } from './ui/Button';
import { LucideIcon, cn } from './ui/Primitive';

export interface SignaturePadProps {
  onChange?: (dataUriPng: string | null) => void;
  value?: string | null;
  minPoints?: number;
  signerName?: string | null;
  onSignerNameChange?: (v: string) => void;
  className?: string;
  aspectRatio?: `${number}:${number}`;
  required?: boolean;
}

export function SignaturePad({
  onChange,
  value,
  minPoints = 12,
  signerName,
  onSignerNameChange,
  className,
  aspectRatio = '5:3',
  required,
}: SignaturePadProps) {
  const canvasRef = useRef<HTMLCanvasElement | null>(null);
  const wrapRef = useRef<HTMLDivElement | null>(null);
  const drawingRef = useRef(false);
  const pointsRef = useRef<number>(0);
  const lastRef = useRef<{ x: number; y: number } | null>(null);
  const [hydrated, setHydrated] = useState(false);
  const [sizeKey, setSizeKey] = useState(0);

  const clear = useCallback(() => {
    const canvas = canvasRef.current;
    const ctx = canvas?.getContext('2d');
    if (!canvas || !ctx) return;
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    pointsRef.current = 0;
    lastRef.current = null;
    onChange?.(null);
  }, [onChange]);

  const emit = useCallback(() => {
    const canvas = canvasRef.current;
    if (!canvas) return;
    if (pointsRef.current < (minPoints ?? 0)) {
      onChange?.(null);
      return;
    }
    const footerH = 64;
    const w = canvas.width;
    const h = canvas.height + footerH;
    const comp = document.createElement('canvas');
    comp.width = w;
    comp.height = h;
    const cctx = comp.getContext('2d');
    if (!cctx) {
      onChange?.(canvas.toDataURL('image/png'));
      return;
    }
    cctx.fillStyle = '#FFFFFF';
    cctx.fillRect(0, 0, w, h);
    cctx.drawImage(canvas, 0, 0);
    cctx.fillStyle = '#F1F5F9';
    cctx.fillRect(0, canvas.height, w, footerH);
    cctx.strokeStyle = '#CBD5E1';
    cctx.lineWidth = 1;
    cctx.beginPath();
    cctx.moveTo(0, canvas.height + 0.5);
    cctx.lineTo(w, canvas.height + 0.5);
    cctx.stroke();
    const capturedAt = new Date();
    const signatory = (signerName ?? '').trim() || 'UNSPECIFIED SIGNATORY';
    const label = `SIGNATURE CAPTURED · ${signatory} · ${capturedAt.toISOString()}`;
    const pad = 18;
    let fontSize = 18;
    cctx.font = `bold ${fontSize}px "JetBrains Mono", ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace`;
    while (fontSize > 11 && cctx.measureText(label).width > w - pad * 2) {
      fontSize -= 1;
      cctx.font = `bold ${fontSize}px "JetBrains Mono", ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace`;
    }
    cctx.fillStyle = '#334155';
    cctx.textBaseline = 'middle';
    cctx.fillText(label, pad, canvas.height + footerH / 2);
    const data = comp.toDataURL('image/png');
    onChange?.(data);
  }, [minPoints, onChange, signerName]);

  useEffect(() => { clear(); setHydrated(true); /* eslint-disable-next-line react-hooks/exhaustive-deps */ }, [sizeKey]);
  useEffect(() => {
    if (!value || hydrated) return;
    const canvas = canvasRef.current;
    if (!canvas) return;
    const img = new Image();
    img.onload = () => {
      const ctx = canvas.getContext('2d');
      if (!ctx) return;
      const scale = Math.min(canvas.width / img.width, canvas.height / img.height);
      const x = (canvas.width - img.width * scale) / 2;
      const y = (canvas.height - img.height * scale) / 2;
      ctx.drawImage(img, x, y, img.width * scale, img.height * scale);
      pointsRef.current = minPoints + 1;
      setHydrated(true);
    };
    img.src = value;
  }, [value, hydrated, minPoints]);

  useEffect(() => {
    const canvas = canvasRef.current;
    if (!canvas) return;
    const dpr = Math.max(1, Math.min(2, window.devicePixelRatio || 1));
    const parent = wrapRef.current;
    const rect = parent?.getBoundingClientRect() ?? { width: 480, height: 288 };
    canvas.width = Math.floor(rect.width * dpr);
    canvas.height = Math.floor(rect.height * dpr);
    canvas.style.width = `${rect.width}px`;
    canvas.style.height = `${rect.height}px`;
    const ctx = canvas.getContext('2d');
    if (ctx) {
      ctx.scale(dpr, dpr);
      ctx.lineWidth = 2.5;
      ctx.lineJoin = 'round';
      ctx.lineCap = 'round';
      ctx.strokeStyle = '#0F172A';
    }
    return () => { /* next render will re-measure */ };
  }, [sizeKey]);

  useEffect(() => {
    const onResize = () => setSizeKey((k) => k + 1);
    window.addEventListener('resize', onResize);
    return () => window.removeEventListener('resize', onResize);
  }, []);

  const toLocal = (e: PointerEvent | React.PointerEvent) => {
    const rect = canvasRef.current!.getBoundingClientRect();
    return { x: e.clientX - rect.left, y: e.clientY - rect.top };
  };

  const onDown = (e: React.PointerEvent<HTMLCanvasElement>) => {
    const canvas = canvasRef.current; if (!canvas) return;
    canvas.setPointerCapture(e.pointerId);
    drawingRef.current = true;
    const p = toLocal(e);
    lastRef.current = p;
    const ctx = canvas.getContext('2d');
    if (ctx) {
      ctx.beginPath();
      ctx.arc(p.x, p.y, 1.2, 0, Math.PI * 2);
      ctx.fillStyle = '#0F172A';
      ctx.fill();
    }
  };
  const onMove = (e: React.PointerEvent<HTMLCanvasElement>) => {
    if (!drawingRef.current) return;
    const canvas = canvasRef.current; const ctx = canvas?.getContext('2d');
    if (!canvas || !ctx) return;
    const p = toLocal(e);
    const last = lastRef.current ?? p;
    ctx.beginPath();
    ctx.moveTo(last.x, last.y);
    ctx.lineTo(p.x, p.y);
    ctx.stroke();
    lastRef.current = p;
    pointsRef.current += 1;
  };
  const onUp = (e: React.PointerEvent<HTMLCanvasElement>) => {
    if (!drawingRef.current) return;
    drawingRef.current = false;
    try { (e.currentTarget as HTMLCanvasElement).releasePointerCapture(e.pointerId); } catch { /* noop */ }
    lastRef.current = null;
    emit();
  };

  const [aw, ah] = aspectRatio.split(':').map((n) => parseInt(n, 10));

  return (
    <div className={cn('w-full space-y-3', className)}>
      <div
        ref={wrapRef}
        className={cn(
          'relative w-full rounded-2xl border border-surface-border bg-white overflow-hidden shadow-[inset_0_0_0_1px_rgba(15,23,42,0.05)]',
        )}
        style={{ aspectRatio: `${aw ?? 5} / ${ah ?? 3}` }}
      >
        <svg className="absolute inset-x-6 bottom-5 opacity-70 pointer-events-none" width="100%" height="1" preserveAspectRatio="none">
          <line x1="0" y1="0.5" x2="100%" y2="0.5" stroke="#CBD5E1" strokeWidth="1" strokeDasharray="3 6" />
        </svg>
        <canvas
          ref={canvasRef}
          className="relative z-10 w-full h-full touch-none cursor-crosshair"
          onPointerDown={onDown}
          onPointerMove={onMove}
          onPointerUp={onUp}
          onPointerCancel={onUp}
          aria-label="Customer signature capture pad"
        />
        {pointsRef.current === 0 ? (
          <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center gap-1 text-slate-400 select-none">
            <LucideIcon name="Signature" size={26} strokeWidth={1.75} />
            <div className="text-body font-medium">Sign within the box using finger or stylus</div>
            <div className="text-helper">Customer signature · direct hand-off orders</div>
          </div>
        ) : null}
        {required ? (
          <div className="absolute top-2 right-2 text-helper font-semibold text-danger">* Required</div>
        ) : null}
      </div>

      <div className="flex flex-wrap items-center gap-3">
        <div className="flex-1 min-w-[220px]">
          <label htmlFor="sig-name" className="block text-helper font-semibold text-slate-300 mb-1.5">
            Signatory Name
          </label>
          <input
            id="sig-name"
            type="text"
            autoComplete="off"
            spellCheck={false}
            value={signerName ?? ''}
            onChange={(e) => onSignerNameChange?.(e.target.value)}
            placeholder="Customer full name"
            className={cn(
              'w-full h-12 rounded-xl border border-surface-border bg-surface-panel px-4 text-body font-medium text-white placeholder:text-slate-500',
              'focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition',
            )}
          />
        </div>
        <div className="flex items-end gap-2">
          <Button variant="outline" size="md" onClick={clear} iconLeft={<LucideIcon name="Eraser" size={16} />}>Clear</Button>
        </div>
      </div>
    </div>
  );
}
