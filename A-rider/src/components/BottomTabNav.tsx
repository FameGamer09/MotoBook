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

  return (
    <>
      <aside className="fixed inset-y-0 left-0 z-40 hidden w-[248px] flex-col border-r border-slate-800/80 bg-[#07121b] px-4 py-6 lg:flex">
        <div className="flex items-center gap-3 border-b border-slate-800/80 px-2 pb-6">
          <span className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-cyan-400/30 bg-cyan-400/10 text-cyan-300">
            <LucideIcon name="Bike" size={21} strokeWidth={1.9} />
          </span>
          <span className="min-w-0">
            <span className="block text-[15px] font-bold text-white">MotoBook</span>
            <span className="block text-[10px] font-bold uppercase tracking-[0.16em] text-slate-500">Rider console</span>
          </span>
        </div>
        <div className="px-3 pb-2 pt-7 text-[10px] font-bold uppercase tracking-[0.16em] text-slate-500">Workspace</div>
        <nav className="flex flex-1 flex-col gap-1" aria-label="Primary">
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
                  'group flex min-h-12 w-full items-center gap-3 rounded-xl px-3 text-left transition-colors',
                  isActive ? 'bg-cyan-400/10 text-cyan-200 ring-1 ring-inset ring-cyan-400/20' : 'text-slate-400 hover:bg-slate-800/70 hover:text-slate-100',
                )}
              >
                <LucideIcon name={tab.icon} size={19} strokeWidth={isActive ? 2 : 1.75} />
                <span className="flex-1 text-[13px] font-semibold">{tab.label}</span>
                {showBadge ? (
                  <span className="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-cyan-400 px-1 text-[10px] font-extrabold text-slate-950">
                    {activeCount > 9 ? '9+' : activeCount}
                  </span>
                ) : null}
                {isActive ? <span className="h-1.5 w-1.5 rounded-full bg-cyan-300" /> : null}
              </button>
            );
          })}
        </nav>
        <div className="rounded-xl border border-slate-800 bg-slate-900/70 p-3">
          <div className="flex items-center gap-2 text-[12px] font-semibold text-slate-300">
            <span className="h-2 w-2 rounded-full bg-cyan-400" />
            Delivery network
          </div>
          <div className="mt-1 text-[11px] text-slate-500">Location and orders sync automatically.</div>
        </div>
      </aside>

      {shouldShowBottomTabNav(location.pathname) ? (
        <nav className="phone-bottom-bar z-40 w-full pointer-events-auto lg:hidden" aria-label="Primary">
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
                    {isActive ? <span aria-hidden="true" className="absolute inset-x-3 top-1 h-1 rounded-full bg-primary shadow-[0_0_12px_rgba(6,182,212,0.5)]" /> : null}
                    <span className="relative inline-flex h-8 w-8 items-center justify-center">
                      <LucideIcon name={tab.icon} size={22} strokeWidth={isActive ? 2.125 : 1.75} />
                      {showBadge ? <span className="absolute -right-1 -top-0.5 inline-flex h-[18px] min-w-[18px] items-center justify-center rounded-full border border-cyan-700/40 bg-cyan-500 px-1 text-[10px] font-extrabold text-white">{activeCount > 9 ? '9+' : activeCount}</span> : null}
                    </span>
                    <span className={cn('text-[10px] font-bold uppercase tracking-[0.02em]', isActive ? 'text-primary' : 'text-slate-500 group-hover:text-slate-300')}>
                      {tab.label}
                    </span>
                  </button>
                );
              })}
            </div>
          </div>
        </nav>
      ) : null}
    </>
  );
}
