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

function BootHydration() {
  const bootstrap = useRiderStore((s) => s.bootstrapFromServer);
  useEffect(() => { void bootstrap(); }, [bootstrap]);
  return null;
}

function PhoneShell({ children }: { children: React.ReactNode }) {
  return (
    <div className="rider-app min-h-[100dvh] w-full text-white antialiased text-[15px] font-sans [color-scheme:dark]">
      <div className="mx-auto flex min-h-[100dvh] w-full max-w-[1680px] lg:pl-[248px]">
        <BottomTabNav />
        <div className="min-w-0 flex-1">
          <div className="relative min-h-[100dvh] w-full pb-[96px] lg:pb-0">
            {children}
          </div>
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
