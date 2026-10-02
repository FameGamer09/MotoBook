import { memo } from 'react';
import { cn, minTouch } from './Primitive';

type Variant = 'primary' | 'success' | 'danger' | 'warning' | 'slate' | 'outline' | 'ghost';
type Size = 'lg' | 'md' | 'sm' | 'block';

const variants: Record<Variant, string> = {
  primary:
    'bg-primary text-white shadow-[0_6px_18px_-6px_rgba(6,182,212,.65)] border border-cyan-500/40 hover:bg-primary-pressed active:bg-cyan-700 disabled:bg-slate-700 disabled:text-slate-400 disabled:border-transparent disabled:shadow-none',
  success:
    'bg-success text-white shadow-[0_6px_18px_-6px_rgba(14,116,144,.6)] border border-cyan-700/40 hover:bg-cyan-800 active:bg-cyan-900 disabled:bg-slate-700 disabled:text-slate-400 disabled:border-transparent disabled:shadow-none',
  danger:
    'bg-slate-800 text-white shadow-[0_6px_18px_-6px_rgba(22,78,99,.6)] border border-cyan-700/30 ring-1 ring-cyan-500/20 hover:bg-slate-900 active:bg-black disabled:bg-slate-700 disabled:text-slate-400 disabled:border-transparent disabled:shadow-none',
  warning:
    'bg-slate-500 text-white shadow-[0_6px_18px_-6px_rgba(71,85,105,.5)] border border-slate-400/50 hover:bg-slate-600 active:bg-slate-700 disabled:bg-slate-700 disabled:text-slate-400 disabled:border-transparent disabled:shadow-none',
  slate:
    'bg-surface-card text-slate-100 border border-surface-border hover:bg-slate-700 active:bg-slate-800 disabled:opacity-50',
  outline:
    'bg-transparent text-slate-200 border border-surface-border hover:bg-slate-800/60 active:bg-slate-800 disabled:opacity-40',
  ghost:
    'bg-transparent text-slate-200 border border-transparent hover:bg-white/5 active:bg-white/10 disabled:opacity-40',
};

const sizes: Record<Size, string> = {
  lg: 'h-14 px-6 rounded-2xl text-body font-semibold tracking-tight',
  md: 'h-[52px] px-5 rounded-xl text-body font-semibold',
  sm: 'h-10 px-4 rounded-lg text-[14px] font-semibold',
  block: 'h-14 w-full px-6 rounded-2xl text-body font-bold',
};

export interface ButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: Variant;
  size?: Size;
  iconLeft?: React.ReactNode;
  iconRight?: React.ReactNode;
}

export const Button = memo(function Button({
  variant = 'primary',
  size = 'md',
  className,
  children,
  iconLeft,
  iconRight,
  ...rest
}: ButtonProps) {
  return (
    <button
      {...rest}
      className={cn(
        minTouch,
        'inline-flex items-center justify-center gap-2.5 select-none transition-all duration-150',
        'focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950',
        'active:scale-[0.992] disabled:cursor-not-allowed disabled:pointer-events-none',
        variants[variant],
        sizes[size],
        className,
      )}
    >
      {iconLeft}
      {children != null ? <span className="truncate">{children}</span> : null}
      {iconRight}
    </button>
  );
});
