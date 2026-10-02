import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { cn } from './Primitive';
import { LucideIcon, type LucideIconName } from './Primitive';

export interface SwipeConfirmSliderProps {
  label: string;
  onComplete: () => void | Promise<void>;
  color?: 'primary' | 'success' | 'warning' | 'slate';
  icon?: LucideIconName;
  disabled?: boolean;
  resetAfterMs?: number;
  progressClassName?: string;
  className?: string;
}

const colorBg: Record<NonNullable<SwipeConfirmSliderProps['color']>, string> = {
  primary: 'bg-primary',
  success: 'bg-success',
  warning: 'bg-warning',
  slate: 'bg-slate-700',
};
const colorRing: Record<NonNullable<SwipeConfirmSliderProps['color']>, string> = {
  primary: 'focus-visible:ring-primary/40',
  success: 'focus-visible:ring-success/40',
  warning: 'focus-visible:ring-warning/40',
  slate: 'focus-visible:ring-slate-500/40',
};

export function SwipeConfirmSlider({
  label,
  onComplete,
  color = 'success',
  icon = 'ArrowRight',
  disabled = false,
  resetAfterMs = 1400,
  className,
  progressClassName,
}: SwipeConfirmSliderProps) {
  const trackRef = useRef<HTMLDivElement>(null);
  const [progressPx, setProgressPx] = useState(0);
  const [completed, setCompleted] = useState(false);
  const startXRef = useRef<number | null>(null);
  const knobStartRef = useRef<number>(0);
  const committedRef = useRef(false);

  const maxWidth = useMemo(() => {
    return Math.max(0, (trackRef.current?.clientWidth ?? 0) - 64);
  }, [progressPx]);

  useEffect(() => {
    const handleResize = () => setProgressPx((p) => Math.min(p, Math.max(0, (trackRef.current?.clientWidth ?? 0) - 64)));
    window.addEventListener('resize', handleResize);
    return () => window.removeEventListener('resize', handleResize);
  }, []);

  const fire = useCallback(async () => {
    if (committedRef.current) return;
    committedRef.current = true;
    setCompleted(true);
    try {
      await onComplete();
    } finally {
      if (resetAfterMs > 0) {
        setTimeout(() => {
          committedRef.current = false;
          setProgressPx(0);
          setCompleted(false);
        }, resetAfterMs);
      }
    }
  }, [onComplete, resetAfterMs]);

  const onPointerDown = (e: React.PointerEvent<HTMLButtonElement>) => {
    if (disabled || completed) return;
    (e.currentTarget as HTMLButtonElement).setPointerCapture(e.pointerId);
    startXRef.current = e.clientX;
    knobStartRef.current = progressPx;
  };

  const onPointerMove = (e: React.PointerEvent<HTMLButtonElement>) => {
    if (startXRef.current == null) return;
    const delta = e.clientX - startXRef.current;
    const next = Math.max(0, Math.min(maxWidth, knobStartRef.current + delta));
    setProgressPx(next);
    if (next >= maxWidth && !committedRef.current) void fire();
  };

  const onPointerUp = (e: React.PointerEvent<HTMLButtonElement>) => {
    if (startXRef.current == null) return;
    (e.currentTarget as HTMLButtonElement).releasePointerCapture(e.pointerId);
    startXRef.current = null;
    if (progressPx < maxWidth) setProgressPx(0);
  };

  const onKey = (e: React.KeyboardEvent<HTMLButtonElement>) => {
    if (disabled || completed) return;
    if (e.key === 'ArrowRight' || e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      setProgressPx(maxWidth);
      if (!committedRef.current) void fire();
    } else if (e.key === 'ArrowLeft') {
      setProgressPx(0);
    }
  };

  const pct = maxWidth ? Math.min(100, (progressPx / maxWidth) * 100) : 0;

  return (
    <div
      ref={trackRef}
      className={cn(
        'relative w-full h-16 rounded-2xl border border-surface-border overflow-hidden',
        'bg-surface-panel/60 select-none transition-colors duration-200',
        colorRing[color],
        className,
      )}
      role="slider"
      aria-valuemin={0}
      aria-valuemax={100}
      aria-valuenow={Math.round(pct)}
      aria-label={label}
    >
      <div
        className={cn('absolute inset-y-0 left-0 transition-[width] duration-75', colorBg[color], progressClassName)}
        style={{ width: `calc(${pct}% + 48px)` }}
      />
      <div className="absolute inset-0 flex items-center justify-center px-6 pointer-events-none">
        <span
          className={cn(
            'text-body font-semibold tracking-wide whitespace-nowrap transition-opacity',
            completed ? 'opacity-0' : 'opacity-95 text-white/90',
          )}
          style={{ mixBlendMode: 'difference' }}
        >
          {completed ? 'CONFIRMED' : label}
        </span>
      </div>
      <button
        type="button"
        disabled={disabled || completed}
        onPointerDown={onPointerDown}
        onPointerMove={onPointerMove}
        onPointerUp={onPointerUp}
        onPointerCancel={() => { startXRef.current = null; setProgressPx(0); }}
        onKeyDown={onKey}
        className={cn(
          'absolute top-1/2 left-1 -translate-y-1/2 h-14 w-14 rounded-xl',
          'bg-white text-slate-900 shadow-[0_14px_30px_-14px_rgba(0,0,0,.8)] border border-slate-200',
          'flex items-center justify-center touch-none transition-transform',
          'focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-surface-dark',
          'disabled:opacity-60 disabled:cursor-not-allowed',
        )}
        style={{ transform: `translate(${progressPx}px, -50%)` }}
        aria-label={completed ? 'Confirmed' : `Swipe to confirm: ${label}`}
      >
        <LucideIcon
          name={completed ? 'Check' : icon}
          size={24}
          strokeWidth={2.25}
          className={cn(completed ? 'text-success' : 'text-slate-900')}
        />
      </button>
    </div>
  );
}
