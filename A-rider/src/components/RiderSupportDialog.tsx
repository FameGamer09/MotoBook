import { createContext, FormEvent, useContext, useEffect, useState } from 'react';
import { Headphones, LoaderCircle, Send, X } from 'lucide-react';
import { fetchJson } from '@/lib/api';
import { useRiderNotice } from '@/components/RiderNotice';

const RiderSupportContext = createContext<() => void>(() => undefined);

export function RiderSupportProvider({ children }: { children: React.ReactNode }) {
  const [open, setOpen] = useState(false);
  const [sending, setSending] = useState(false);
  const [error, setError] = useState('');
  const showNotice = useRiderNotice();

  useEffect(() => {
    if (!open) return undefined;
    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape' && !sending) setOpen(false);
    };
    document.addEventListener('keydown', onKeyDown);
    return () => document.removeEventListener('keydown', onKeyDown);
  }, [open, sending]);

  const sendMessage = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const form = event.currentTarget;
    const fields = new FormData(form);
    setSending(true);
    setError('');
    try {
      const { data, ok } = await fetchJson<{ ok?: boolean; request_id?: number; message?: string }>('/session/support', {
        method: 'POST',
        body: JSON.stringify(Object.fromEntries(fields.entries())),
      });
      if (!ok || !data?.ok || !data.request_id) {
        setError(data?.message ?? 'Your message could not be sent. Please try again.');
        return;
      }
      form.reset();
      setOpen(false);
      showNotice(`Message sent to rider support. Request #${data.request_id}.`);
    } catch {
      setError('Could not reach rider support. Check your connection and try again.');
    } finally {
      setSending(false);
    }
  };

  return (
    <RiderSupportContext.Provider value={() => { setError(''); setOpen(true); }}>
      {children}
      {open ? (
        <div className="fixed inset-0 z-[1200] flex items-end justify-center bg-slate-950/75 p-3 backdrop-blur-sm sm:items-center sm:p-5" onMouseDown={(event) => { if (event.target === event.currentTarget && !sending) setOpen(false); }}>
          <section role="dialog" aria-modal="true" aria-labelledby="rider-support-title" className="phone-col max-h-[min(88dvh,720px)] w-full overflow-y-auto rounded-t-[28px] border border-cyan-500/25 bg-[#09141f] p-5 pb-[max(1.25rem,env(safe-area-inset-bottom))] shadow-[0_24px_80px_rgba(0,0,0,0.6)] sm:max-h-[90dvh] sm:rounded-[28px] sm:p-6">
            <header className="flex items-start gap-3">
              <span className="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl border border-cyan-400/25 bg-cyan-500/15 text-cyan-200"><Headphones size={22} /></span>
              <div className="min-w-0 flex-1 pt-0.5">
                <h2 id="rider-support-title" className="text-lg font-extrabold tracking-tight text-white">Message rider support</h2>
                <p className="mt-1 text-xs leading-5 text-slate-400">Send a message to the MotoBook operations team.</p>
              </div>
              <button type="button" aria-label="Close support panel" disabled={sending} onClick={() => setOpen(false)} className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-slate-700 text-slate-300 hover:bg-slate-800 disabled:opacity-50"><X size={18} /></button>
            </header>

            <form onSubmit={(event) => void sendMessage(event)} className="mt-5 space-y-4">
              <label className="block space-y-1.5">
                <span className="text-xs font-bold text-slate-300">Topic</span>
                <select name="category" required defaultValue="Delivery" className="min-h-12 w-full rounded-xl border border-slate-700 bg-slate-950 px-3 text-sm text-white focus:border-cyan-400 focus:outline-none focus:ring-2 focus:ring-cyan-400/20">
                  <option>Delivery</option><option>Account</option><option>Payment</option><option>Safety</option><option>App / Technical</option><option>Other</option>
                </select>
              </label>
              <label className="block space-y-1.5">
                <span className="text-xs font-bold text-slate-300">Subject</span>
                <input name="subject" required maxLength={120} placeholder="What do you need help with?" className="min-h-12 w-full rounded-xl border border-slate-700 bg-slate-950 px-3 text-sm text-white placeholder:text-slate-600 focus:border-cyan-400 focus:outline-none focus:ring-2 focus:ring-cyan-400/20" />
              </label>
              <label className="block space-y-1.5">
                <span className="text-xs font-bold text-slate-300">Message</span>
                <textarea name="message" required minLength={2} maxLength={4000} rows={5} placeholder="Describe what happened. Add an order code if it helps." className="w-full resize-y rounded-xl border border-slate-700 bg-slate-950 px-3 py-3 text-sm leading-6 text-white placeholder:text-slate-600 focus:border-cyan-400 focus:outline-none focus:ring-2 focus:ring-cyan-400/20" />
                <span className="block text-right text-[11px] text-slate-500">Please do not include your password or PIN.</span>
              </label>
              {error ? <p role="alert" className="rounded-xl border border-rose-400/25 bg-rose-500/10 px-3 py-2.5 text-xs leading-5 text-rose-100">{error}</p> : null}
              <div className="grid grid-cols-2 gap-2 border-t border-slate-800 pt-4">
                <button type="button" disabled={sending} onClick={() => setOpen(false)} className="min-h-12 rounded-xl border border-slate-700 bg-slate-900 px-3 text-sm font-bold text-slate-200 hover:bg-slate-800 disabled:opacity-50">Cancel</button>
                <button type="submit" disabled={sending} className="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl bg-cyan-400 px-3 text-sm font-extrabold text-slate-950 hover:bg-cyan-300 disabled:opacity-60">
                  {sending ? <><LoaderCircle size={17} className="animate-spin" /> Sending…</> : <><Send size={16} /> Send message</>}
                </button>
              </div>
            </form>
          </section>
        </div>
      ) : null}
    </RiderSupportContext.Provider>
  );
}

export function useRiderSupport() {
  return useContext(RiderSupportContext);
}
