import { cn } from './Primitive';

export type StatusDotState =
  | 'online'
  | 'offline'
  | 'on-duty'
  | 'success'
  | 'warning'
  | 'danger'
  | 'in-transit';

const dots: Record<StatusDotState, string> = {
  online: 'bg-cyan-400 shadow-[0_0_0_3px_rgba(34,211,238,.22)]',
  offline: 'bg-slate-500 shadow-[0_0_0_3px_rgba(100,116,139,.15)]',
  'on-duty': 'bg-primary shadow-[0_0_0_3px_rgba(6,182,212,.24)]',
  success: 'bg-success shadow-[0_0_0_3px_rgba(14,116,144,.24)]',
  warning: 'bg-slate-400 shadow-[0_0_0_3px_rgba(148,163,184,.22)]',
  danger: 'bg-slate-900 shadow-[0_0_0_3px_rgba(15,23,42,.22)] ring-1 ring-cyan-500/30',
  'in-transit': 'bg-primary shadow-[0_0_0_3px_rgba(6,182,212,.24)]',
};

export function StatusDot({
  state,
  label,
  className,
  size = 10,
  pulse = false,
}: {
  state: StatusDotState;
  label?: string;
  className?: string;
  size?: number;
  pulse?: boolean;
}) {
  return (
    <span className={cn('inline-flex items-center gap-2', className)}>
      <span className="relative inline-flex">
        {pulse && (
          <span
            className={cn(
              'absolute inline-flex rounded-full opacity-60 animate-ping',
              dots[state],
            )}
            style={{ width: size, height: size }}
          />
        )}
        <span
          className={cn('inline-block rounded-full', dots[state])}
          style={{ width: size, height: size }}
          role="status"
          aria-label={label || state}
        />
      </span>
      {label ? (
        <span className="text-helper text-surface-muted font-medium">{label}</span>
      ) : null}
    </span>
  );
}
