import { useEffect, useMemo, useRef, useState } from 'react';
import { cn } from './Primitive';

export interface CountdownRingProps {
  /** Total countdown duration, milliseconds */
  totalMs: number;
  /** When countdown started (ms epoch). If omitted, uses Date.now() on mount. */
  startedAtMs?: number;
  /** Triggered when remaining <= 0 */
  onExpire?: () => void;
  /** Triggered every tick with remaining ms */
  onTick?: (remainingMs: number) => void;
  size?: number;
  strokeWidth?: number;
  ringColor?: string;
  warnColor?: string;
  dangerColor?: string;
  warnAtMs?: number;
  dangerAtMs?: number;
  className?: string;
  textClassName?: string;
  showSecondsFraction?: boolean;
}

const TWO_PI = Math.PI * 2;

export function CountdownRing({
  totalMs,
  startedAtMs,
  onExpire,
  onTick,
  size = 64,
  strokeWidth = 5,
  ringColor = '#06B6D4',
  warnColor = '#0891B2',
  dangerColor = '#164E63',
  warnAtMs,
  dangerAtMs,
  className,
  textClassName,
  showSecondsFraction = false,
}: CountdownRingProps) {
  const startRef = useRef<number>(startedAtMs ?? Date.now());
  const firedRef = useRef(false);
  const [remaining, setRemaining] = useState(() => {
    return Math.max(0, totalMs - (Date.now() - startRef.current));
  });
  useEffect(() => {
    startRef.current = startedAtMs ?? Date.now();
    firedRef.current = false;
  }, [totalMs, startedAtMs]);

  useEffect(() => {
    let raf = 0;
    const tick = () => {
      const rem = Math.max(0, totalMs - (Date.now() - startRef.current));
      setRemaining(rem);
      onTick?.(rem);
      if (rem <= 0 && !firedRef.current) {
        firedRef.current = true;
        onExpire?.();
      } else {
        raf = requestAnimationFrame(tick);
      }
    };
    raf = requestAnimationFrame(tick);
    return () => cancelAnimationFrame(raf);
  }, [totalMs, onExpire, onTick]);

  const progress = totalMs <= 0 ? 0 : Math.min(1, remaining / totalMs);
  const radius = (size - strokeWidth) / 2;
  const circ = TWO_PI * radius;
  const dash = circ * progress;
  const warnMs = warnAtMs ?? totalMs * 0.45;
  const dangerMs = dangerAtMs ?? totalMs * 0.2;
  const color = remaining <= dangerMs ? dangerColor : remaining <= warnMs ? warnColor : ringColor;
  const seconds = useMemo(() => {
    const s = remaining / 1000;
    return showSecondsFraction ? s.toFixed(1) : Math.ceil(s).toFixed(0);
  }, [remaining, showSecondsFraction]);

  return (
    <div className={cn('relative inline-flex items-center justify-center', className)}>
      <svg width={size} height={size} className="inline-flex" aria-hidden="true">
        <circle
          cx={size / 2}
          cy={size / 2}
          r={radius}
          fill="none"
          stroke="rgba(148, 163, 184, 0.25)"
          strokeWidth={strokeWidth}
        />
        <circle
          cx={size / 2}
          cy={size / 2}
          r={radius}
          fill="none"
          stroke={color}
          strokeWidth={strokeWidth}
          strokeDasharray={`${dash} ${circ - dash}`}
          strokeDashoffset={circ * 0.25}
          strokeLinecap="round"
          style={{ transition: 'stroke 200ms ease' }}
        />
      </svg>
      <span
        className={cn(
          'absolute inset-0 inline-flex items-center justify-center font-mono font-bold text-white',
          textClassName,
        )}
        style={{ fontSize: Math.max(12, size * 0.26) }}
        aria-live="polite"
      >
        {seconds}s
      </span>
    </div>
  );
}
