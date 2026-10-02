import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useRiderStore } from '@/store/useRiderStore';
import { Button } from '@/components/ui/Button';
import { LucideIcon, cn, currencyPHP } from '@/components/ui/Primitive';
import { AccountSettingsPanel, type AccountSection } from '@/components/AccountSettingsPanel';
import type { RiderProfile } from '@/types/domain';

interface AccountRow {
  key: AccountSection;
  icon: Parameters<typeof LucideIcon>[0]['name'];
  title: string;
  subtitle?: string;
  divider?: boolean;
}

const ACCOUNT_ROWS: AccountRow[] = [
  { key: 'personal',    icon: 'UserCog2',       title: 'Personal Information', subtitle: 'Name, phone, address', divider: true },
  { key: 'vehicle',     icon: 'Bike',           title: 'Vehicle Information',  subtitle: 'Plate, OR/CR details', divider: true },
  { key: 'documents',   icon: 'FileCheck2',     title: 'Documents',            subtitle: 'License, insurance, permits', divider: true },
  { key: 'notifications', icon: 'BellRing',     title: 'Notifications',        subtitle: 'SMS, app, dispatch alerts', divider: true },
  { key: 'help',        icon: 'Headphones',     title: 'Help & Support',       subtitle: 'Send a request to rider support', divider: true },
  { key: 'privacy',     icon: 'ShieldCheck',    title: 'Privacy & Security',   subtitle: 'PIN, 2FA, sessions' },
];

const RIDER_AVATAR = 'data:image/svg+xml;utf8,' + encodeURIComponent(
  `<svg xmlns='http://www.w3.org/2000/svg' width='128' height='128' viewBox='0 0 128 128'>
    <defs>
      <linearGradient id='g' x1='0' x2='1' y1='0' y2='1'>
        <stop offset='0%' stop-color='#06B6D4'/>
        <stop offset='100%' stop-color='#0E7490'/>
      </linearGradient>
    </defs>
    <rect width='128' height='128' rx='64' fill='url(#g)'/>
    <circle cx='64' cy='52' r='22' fill='white' opacity='0.95'/>
    <path d='M20 116 C26 88 94 88 108 116 Z' fill='white' opacity='0.95'/>
    <rect x='46' y='100' width='36' height='10' rx='5' fill='#0891B2'/>
  </svg>`,
);

export function ProfilePage() {
  const navigate = useNavigate();
  const rider = useRiderStore((s) => s.profile);
  const patchProfile = useRiderStore((s) => s.patchProfile);
  const logout = useRiderStore((s) => s.logout);
  const setDuty = useRiderStore((s) => s.setDuty);
  const [activeSection, setActiveSection] = useState<AccountSection | null>(null);

  const isOnDuty = rider?.status === 'ON_SHIFT';
  const riderName = rider?.name?.trim() || 'Juan Dela Cruz';
  const riderCode = rider?.rider_code || 'MB-10234';
  const totalDeliveries = 142;
  const cancellationRate = 2;
  const rating = 4.9;
  const reviews = 128;
  const level = 5;
  const payoutSoFar = rider?.duty_today_payout ?? 1240;

  return (
    <div className="min-h-[100dvh] w-full flex flex-col bg-gradient-to-b from-slate-950 via-slate-950 to-slate-950 text-white">
      <header className="phone-col px-4 pt-[44px] sm:pt-[52px] pb-2 flex items-center justify-between gap-2">
        <Button variant="ghost" size="sm" aria-label="QR ID">
          <LucideIcon name="QrCode" size={22} strokeWidth={1.75} />
        </Button>
        <h1 className="text-screen-title font-semibold text-white">Profile</h1>
        <Button variant="ghost" size="sm" aria-label="Settings">
          <LucideIcon name="Settings" size={20} strokeWidth={1.75} />
        </Button>
      </header>

      <main className="phone-col px-4 pt-2 pb-4 space-y-5">
        {/* Rider identity card */}
        <section className="relative overflow-hidden rounded-3xl border border-cyan-600/30 bg-gradient-to-br from-slate-900 via-[#0c1a24] to-slate-900 p-4">
          <div aria-hidden="true" className="absolute -right-16 -top-20 h-60 w-60 rounded-full bg-cyan-500/10 blur-3xl" />
          <div className="relative grid grid-cols-[auto_1fr] items-center gap-3.5">
            <div className="relative">
              <span
                className="h-20 w-20 rounded-[28px] inline-flex items-center justify-center overflow-hidden border-2 border-cyan-400/40 shadow-[0_12px_24px_-8px_rgba(6,182,212,0.55)] bg-slate-800"
                aria-label={`${riderName} profile picture`}
              >
                <img src={RIDER_AVATAR} alt="" className="h-full w-full object-cover" />
              </span>
              <span aria-hidden="true" className="absolute -bottom-1 -right-1 h-6 w-6 rounded-2xl inline-flex items-center justify-center border-2 border-slate-900 bg-cyan-400">
                <LucideIcon name="BadgeCheck" size={16} strokeWidth={2.25} className="text-slate-900" />
              </span>
            </div>
            <div className="min-w-0">
              <div className="flex items-center gap-2 flex-wrap">
                <h2 className="text-[19px] font-extrabold tracking-tight text-white truncate">{riderName}</h2>
                <span className="inline-flex h-5 w-5 items-center justify-center rounded-full border border-cyan-400/50 bg-cyan-500/15 text-cyan-300">
                  <LucideIcon name="Check" size={13} strokeWidth={2.75} />
                </span>
              </div>
              <div className="mt-1 flex items-center gap-2 flex-wrap">
                <span className="inline-flex items-center gap-1 rounded-full h-6 px-2.5 bg-gradient-to-r from-cyan-500 to-cyan-700 text-white text-[11px] font-extrabold uppercase tracking-[0.08em] shadow-[0_0_18px_rgba(6,182,212,0.25)]">
                  Level {level} Rider
                </span>
                <span className="inline-flex items-center gap-1 text-[12px] font-semibold text-cyan-300">
                  <LucideIcon name="Star" size={14} strokeWidth={2} className="fill-cyan-400 text-cyan-400" />
                  {rating.toFixed(1)}
                  <span className="text-slate-400">({reviews})</span>
                </span>
              </div>
              <div className="mt-1 text-helper font-mono text-slate-400">{riderCode}</div>
            </div>
          </div>
        </section>

        {/* Stats */}
        <section>
          <h3 className="text-[12px] font-bold uppercase tracking-[0.1em] text-cyan-300 mb-2 px-0.5">My Statistics</h3>
          <div className="grid grid-cols-3 gap-2">
            <StatCard
              icon="PackageCheck"
              label="Total Deliveries"
              value={String(totalDeliveries)}
              sub={'All time'}
            />
            <StatCard
              icon="Zap"
              label="Auto-Assign"
              value={isOnDuty ? 'ON' : 'OFF'}
              sub="When on duty"
            />
            <StatCard
              icon="XCircle"
              label="Cancellation Rate"
              value={`${cancellationRate}%`}
              sub="Last 30 days"
            />
          </div>
        </section>

        {/* Duty + payout quick controls */}
        <section className="grid grid-cols-2 gap-2.5">
          <button
            type="button"
            onClick={() => void setDuty(!isOnDuty)}
            className={cn(
              'relative rounded-2xl border p-3.5 text-left overflow-hidden transition-colors',
              isOnDuty
                ? 'bg-gradient-to-br from-cyan-500 to-cyan-700 border-cyan-400/60 text-white shadow-[0_12px_28px_-12px_rgba(6,182,212,0.75)]'
                : 'bg-surface-card border-surface-border text-white hover:border-cyan-500/40',
            )}
          >
            <span className="inline-flex items-center justify-center h-9 w-9 rounded-2xl bg-white/10 border border-white/15 mb-2">
              <LucideIcon name={isOnDuty ? 'Bike' : 'PauseCircle'} size={18} strokeWidth={1.75} />
            </span>
            <div className="text-[11px] font-bold uppercase tracking-[0.08em] opacity-85">
              {isOnDuty ? 'Currently On-Duty' : 'Go On-Duty'}
            </div>
            <div className="text-[15px] font-extrabold mt-0.5">
              {isOnDuty ? 'Tap to go offline' : 'Receive deliveries'}
            </div>
          </button>
          <div className="rounded-2xl border border-surface-border bg-surface-card p-3.5 overflow-hidden">
            <div className="inline-flex items-center justify-center h-9 w-9 rounded-2xl bg-cyan-500/15 border border-cyan-500/25 mb-2 text-cyan-300">
              <LucideIcon name="Wallet" size={18} strokeWidth={1.75} />
            </div>
            <div className="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-400">Today&apos;s Payout</div>
            <div className="text-[18px] font-extrabold font-mono text-cyan-300 mt-0.5">{currencyPHP(payoutSoFar)}</div>
          </div>
        </section>

        {/* Account rows */}
        <section className="rounded-3xl border border-surface-border bg-surface-card/80 overflow-hidden">
          <div className="px-4 pt-3 pb-1.5 flex items-center justify-between">
            <h3 className="text-[12px] font-bold uppercase tracking-[0.1em] text-cyan-300">Account</h3>
            <button type="button" onClick={() => navigate('/earnings')} className="text-[12px] font-semibold text-cyan-300 inline-flex items-center gap-1">
              Earnings <LucideIcon name="ChevronRight" size={14} strokeWidth={2} />
            </button>
          </div>
          <ul className="px-2">
            {ACCOUNT_ROWS.map((row, idx) => (
              <li
                key={row.key}
                className={cn(
                  'rounded-2xl transition-colors hover:bg-surface-panel/60',
                  idx !== ACCOUNT_ROWS.length - 1 && row.divider && 'border-b border-surface-border/80',
                )}
              >
                <button type="button" onClick={() => setActiveSection(row.key)} className="grid min-h-[76px] w-full grid-cols-[auto_1fr_auto] items-center gap-3 px-2 py-3 text-left focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-cyan-400" aria-label={`Open ${row.title}`}>
                  <span className="h-9 w-9 rounded-2xl inline-flex items-center justify-center bg-cyan-500/12 border border-cyan-500/20 text-cyan-300">
                    <LucideIcon name={row.icon} size={18} strokeWidth={1.75} />
                  </span>
                  <span className="min-w-0">
                    <span className="block truncate text-[14px] font-semibold text-white">{row.title}</span>
                    {row.subtitle ? <span className="mt-0.5 block text-[12px] text-slate-400">{row.subtitle}</span> : null}
                  </span>
                  <LucideIcon name="ChevronRight" size={18} strokeWidth={2} className="text-slate-500" />
                </button>
              </li>
            ))}
          </ul>
          <div className="px-2 pb-3">
            <button
              type="button"
              onClick={() => void logout()}
              className="w-full rounded-2xl border border-cyan-800/40 bg-slate-900/60 hover:bg-slate-900 text-slate-200 h-12 inline-flex items-center justify-center gap-2 text-[14px] font-bold transition-colors"
            >
              <LucideIcon name="LogOut" size={18} strokeWidth={1.75} className="text-cyan-400" />
              Sign Out
            </button>
          </div>
        </section>
      </main>
      {activeSection && rider ? (
        <AccountSettingsPanel
          section={activeSection}
          rider={rider}
          onClose={() => setActiveSection(null)}
          onSectionChange={setActiveSection}
          onProfileSaved={(profile: Partial<RiderProfile>) => patchProfile(profile)}
        />
      ) : null}
    </div>
  );
}

function StatCard({ icon, label, value, sub }: { icon: Parameters<typeof LucideIcon>[0]['name']; label: string; value: string; sub: string }) {
  return (
    <div className="rounded-2xl border border-surface-border bg-surface-card p-3 relative overflow-hidden">
      <div aria-hidden="true" className="absolute -right-6 -top-6 h-16 w-16 rounded-full bg-cyan-500/8 blur-2xl" />
      <span className="inline-flex items-center justify-center h-8 w-8 rounded-xl bg-cyan-500/12 border border-cyan-500/20 text-cyan-300">
        <LucideIcon name={icon} size={15} strokeWidth={1.75} />
      </span>
      <div className="mt-2 text-[20px] font-extrabold font-mono tracking-tight text-white">{value}</div>
      <div className="mt-0.5 text-[11px] font-bold uppercase tracking-[0.02em] text-slate-400 leading-tight">{label}</div>
      <div className="text-[10px] font-medium text-slate-500 mt-0.5">{sub}</div>
    </div>
  );
}
