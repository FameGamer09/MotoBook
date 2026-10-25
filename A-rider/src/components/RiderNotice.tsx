import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { Info, X } from 'lucide-react';

type RiderNoticeContextValue = (message: string) => void;

const RiderNoticeContext = createContext<RiderNoticeContextValue>(() => undefined);

export function RiderNoticeProvider({ children }: { children: React.ReactNode }) {
  const [message, setMessage] = useState('');
  const showNotice = useCallback((nextMessage: string) => setMessage(nextMessage), []);

  useEffect(() => {
    if (!message) return undefined;
    const timeout = window.setTimeout(() => setMessage(''), 4500);
    return () => window.clearTimeout(timeout);
  }, [message]);

  const value = useMemo(() => showNotice, [showNotice]);

  return (
    <RiderNoticeContext.Provider value={value}>
      {children}
      <div className="rider-notice-host" aria-live="polite" aria-atomic="true">
        {message ? (
          <div className="rider-notice">
            <span className="rider-notice-icon" aria-hidden="true"><Info size={19} /></span>
            <p>{message}</p>
            <button type="button" className="rider-notice-close" aria-label="Dismiss message" onClick={() => setMessage('')}>
              <X size={17} />
            </button>
          </div>
        ) : null}
      </div>
    </RiderNoticeContext.Provider>
  );
}

export function useRiderNotice() {
  return useContext(RiderNoticeContext);
}
