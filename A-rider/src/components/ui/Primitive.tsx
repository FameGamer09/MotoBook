import { forwardRef, type ComponentProps, type ElementRef } from 'react';
import * as Lucide from 'lucide-react';
import { twMerge } from 'tailwind-merge';

export type LucideIconName = keyof typeof Lucide;

interface LucideIconProps extends ComponentProps<'svg'> {
  name: LucideIconName;
  strokeWidth?: number;
  size?: number;
}

export const LucideIcon = forwardRef<SVGSVGElement, LucideIconProps>(
  ({ name, className, strokeWidth = 1.75, size = 20, ...rest }, ref) => {
    const Comp = Lucide[name] as
      | React.ComponentType<ComponentProps<'svg'> & { strokeWidth?: number; size?: number }>
      | undefined;
    if (!Comp) return null;
    return (
      <Comp
        ref={ref as React.Ref<SVGSVGElement>}
        size={size}
        strokeWidth={strokeWidth}
        className={twMerge('inline-flex flex-shrink-0', className)}
        {...rest}
      />
    );
  },
);

LucideIcon.displayName = 'LucideIcon';

export type IconButtonColor =
  | 'slate' | 'slate-soft'
  | 'primary' | 'primary-soft'
  | 'success' | 'danger' | 'warning'
  | 'ghost' | 'white';

export const colorTokens: Record<IconButtonColor, string> = {
  slate: 'bg-surface-card text-slate-100 hover:bg-slate-700 border border-surface-border active:bg-slate-800',
  'slate-soft': 'bg-surface-panel/70 text-slate-200 hover:bg-surface-card border border-transparent',
  primary: 'bg-primary text-white hover:bg-primary-pressed active:bg-cyan-700 border border-cyan-500/40',
  'primary-soft': 'bg-primary-soft text-cyan-200 hover:bg-primary/20 border border-primary/20',
  success: 'bg-success text-white hover:bg-cyan-800 border border-cyan-700/40',
  danger: 'bg-slate-800 text-white hover:bg-slate-900 border border-cyan-700/30 ring-1 ring-cyan-500/20',
  warning: 'bg-slate-500 text-white hover:bg-slate-600 border border-slate-400/40',
  ghost: 'bg-transparent text-slate-200 hover:bg-white/5 border border-transparent',
  white: 'bg-white text-slate-900 hover:bg-slate-100 border border-slate-200',
};

export const minTouch = 'min-h-[48px] min-w-[48px]';

export function cn(...vals: unknown[]): string {
  return twMerge(vals.filter(Boolean).join(' '));
}

export function currencyPHP(v: number): string {
  const abs = Math.abs(v);
  const sign = v < 0 ? '-' : '₱';
  const n = abs.toLocaleString('en-PH', {
    minimumFractionDigits: 2, maximumFractionDigits: 2, useGrouping: true,
  });
  return `${sign}${n}`;
}
