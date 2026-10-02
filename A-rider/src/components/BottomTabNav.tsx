import { useLocation, useNavigate } from 'react-router-dom';
import { cn, LucideIcon, type LucideIconName } from '@/components/ui/Primitive';
import { useOrderStore } from '@/store/useOrderStore';
import { useMemo } from 'react';

export type BottomTabKey = 'home' | 'active' | 'orders' | 'earnings' | 'profile';

interface TabDef {
  key: BottomTabKey;
  label: string;
  icon: LucideIconName;
  to: string;
}

const TABS: TabDef[] = [
  { key: 'home',    label: 'Home',    icon: 'Home',           to: '/' },
  { key: 'active',  label: 'Active',  icon: 'Navigation',     to: '/delivery' },
  { key: 'orders',  label: 'Orders',  icon: 'ClipboardList',  to: '/orders' },
  { key: 'earnings',label: 'Earnings',icon: 'Wallet',         to: '/earnings' },
  { key: 'profile', label: 'Profile', icon: 'UserRound',      to: '/profile' },
];

const HIDDEN_ON_ROUTES = new Set(['/delivery', '/checklist', '/pod', '/deposit']);

export function shouldShowBottomTabNav(pathname: string): boolean {
  const normalized = pathname.split('?')[0].replace(/\/$/, '') || '/';
  return !HIDDEN_ON_ROUTES.has(normalized);
}

export function BottomTabNav() {
  const location = useLocation();
  const navigate = useNavigate();
  const activeCount = useOrderStore((s) => s.list.filter(
    (o) => o.state === 'ACCEPTED'
      || o.state === 'NAVIGATING_TO_PICKUP'
      || o.state === 'ARRIVED_AT_PICKUP'
      || o.state === 'ORDER_VERIFIED'
      || o.state === 'NAVIGATING_TO_DROP_OFF'
      || o.state === 'ARRIVED_AT_DROP_OFF'
      || o.state === 'PROOF_SUBMITTED',
  ).length);

  const active = useMemo<BottomTabKey>(() => {
    const p = location.pathname.split('?')[0].replace(/\/$/, '') || '/';
    if (p === '/orders') return 'orders';
    if (p === '/earnings') return 'earnings';
    if (p === '/profile') return 'profile';
    if (p === '/delivery' || p === '/checklist' || p === '/pod') return 'active';
    return 'home';
  }, [location.pathname]);

  if (!shouldShowBottomTabNav(location.pathname)) return null;

  return (
    <nav
      className="phone-bottom-bar z-40 w-full pointer-events-auto"
      aria-label="Primary"
    >
      <div className="phone-col mx-auto px-2 pb-2">
        <div className="relative w-full rounded-3xl border border-surface-border bg-surface-dark/95 backdrop-blur-xl shadow-[0_-12px_40px_-14px_rgba(6,182,212,0.12)] px-1 py-2 grid grid-cols-5 gap-1">
          <div aria-hidden="true" className="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-cyan-500/25 to-transparent" />
          {TABS.map((tab) => {
            const isActive = tab.key === active;
            const showBadge = tab.key === 'active' && activeCount > 0;
            return (
              <button
                key={tab.key}
                type="button"
                onClick={() => navigate(tab.to)}
                aria-current={isActive ? 'page' : undefined}
                className={cn(
                  'group relative flex flex-col items-center justify-center gap-0.5 py-1.5 rounded-2xl min-h-[56px] transition-all duration-200',
                  'focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/50 focus-visible:ring-offset-1 focus-visible:ring-offset-surface-dark',
                  isActive ? 'text-primary' : 'text-slate-400 hover:text-cyan-200',
                )}
              >
                {isActive ? (
                  <span aria-hidden="true" className="absolute inset-x-3 top-1 h-1 rounded-full bg-primary shadow-[0_0_12px_rgba(6,182,212,0.5)]" />
                ) : null}
                <span className="relative inline-flex items-center justify-center h-8 w-8">
                  <LucideIcon
                    name={tab.icon}
                    size={22}
                    strokeWidth={isActive ? 2.125 : 1.75}
                    className={cn(isActive && 'drop-shadow-[0_0_6px_rgba(6,182,212,0.35)]')}
                  />
                  {showBadge ? (
                    <span className="absolute -top-0.5 -right-1 min-w-[18px] h-[18px] px-1 inline-flex items-center justify-center rounded-full border border-cyan-700/40 bg-cyan-500 text-white text-[10px] font-extrabold shadow-[0_0_10px_rgba(6,182,212,0.45)]">
                      {activeCount > 9 ? '9+' : activeCount}
                    </span>
                  ) : null}
                </span>
                <span className={cn(
                  'text-[10px] font-bold tracking-[0.02em] uppercase',
                  isActive ? 'text-primary' : 'text-slate-500 group-hover:text-slate-300',
                )}>
                  {tab.label}
                </span>
              </button>
            );
          })}
        </div>
      </div>
    </nav>
  );
}
