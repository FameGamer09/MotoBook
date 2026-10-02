import { StrictMode, useEffect } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import './global.css';
import { HomeDashboardPage } from '@/pages/HomeDashboard';
import { ActiveDeliveryPage } from '@/pages/ActiveDelivery';
import { MerchantChecklistPage } from '@/pages/MerchantChecklist';
import { ProofOfDeliveryPage } from '@/pages/ProofOfDelivery';
import { EarningsLedgerPage } from '@/pages/EarningsLedgerPage';
import { DepositPage } from '@/pages/DepositPage';
import { OrderHistoryPage } from '@/pages/OrderHistoryPage';
import { ProfilePage } from '@/pages/ProfilePage';
import { useRiderStore } from '@/store/useRiderStore';
import { BottomTabNav } from '@/components/BottomTabNav';
import { cn } from '@/components/ui/Primitive';

function BootHydration() {
  const bootstrap = useRiderStore((s) => s.bootstrapFromServer);
  useEffect(() => { void bootstrap(); }, [bootstrap]);
  return null;
}

function PhoneShell({ children }: { children: React.ReactNode }) {
  return (
    <div className="min-h-[100dvh] w-full bg-slate-950 text-white antialiased text-[15px] font-sans relative [color-scheme:dark]">
      {/* Desktop phone frame (>= 560px): centered iPhone-width bezel column with side rails */}
      <div className="relative mx-auto min-h-[100dvh] w-full
                      max-w-[430px]
                      sm:my-4 sm:h-[calc(100dvh-2rem)] sm:min-h-[760px] sm:max-h-[920px]
                      sm:rounded-[44px] sm:border sm:border-slate-800/80 sm:bg-slate-950 sm:shadow-[0_40px_80px_-20px_rgba(15,23,42,0.9),0_0_0_10px_rgba(2,6,23,0.9),0_0_0_12px_rgba(30,41,59,0.8)]
                      sm:overflow-hidden">
        {/* Dynamic island notch on desktop-only for authentic app look */}
        <div aria-hidden="true" className="pointer-events-none absolute top-2 left-1/2 z-40 hidden sm:block -translate-x-1/2 h-7 w-32 rounded-full bg-black/90 border border-slate-800" />
        {/* Content column */}
        <div className="relative min-h-[100dvh] w-full h-full sm:min-h-full flex flex-col">
          <div className="flex-1 w-full relative pb-[96px] sm:pb-[104px]">
            {children}
          </div>
          <BottomTabNav />
        </div>
      </div>
    </div>
  );
}

function App() {
  const basename = (window as any)?.__RIDER_BOOT__?.routerBasename
    || '/IM-101/motobook/A-rider';
  return (
    <BrowserRouter basename={basename}>
      <BootHydration />
      <PhoneShell>
        <Routes>
          <Route path="/" element={<HomeDashboardPage />} />
          <Route path="/delivery" element={<ActiveDeliveryPage />} />
          <Route path="/checklist" element={<MerchantChecklistPage />} />
          <Route path="/pod" element={<ProofOfDeliveryPage />} />
          <Route path="/earnings" element={<EarningsLedgerPage />} />
          <Route path="/deposit" element={<DepositPage />} />
          <Route path="/orders" element={<OrderHistoryPage />} />
          <Route path="/profile" element={<ProfilePage />} />
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </PhoneShell>
    </BrowserRouter>
  );
}

const el = document.getElementById('rider-root');
if (el) {
  createRoot(el).render(
    <StrictMode>
      <App />
    </StrictMode>,
  );
} else {
  // eslint-disable-next-line no-console
  console.error('[MotoBook Rider] Root element #rider-root missing in index.php shell');
}
