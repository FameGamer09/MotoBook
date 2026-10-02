import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import type { RiderProfile } from '@/types/domain';
import { fetchJson } from '@/lib/api';
import { requestNotificationPermission } from '@/hooks/usePushNotifications';
import { Button } from '@/components/ui/Button';
import { LucideIcon } from '@/components/ui/Primitive';
import { useRiderStore } from '@/store/useRiderStore';

export type AccountSection = 'personal' | 'vehicle' | 'documents' | 'notifications' | 'help' | 'privacy';

const SECTION_TITLES: Record<AccountSection, string> = {
  personal: 'Personal Information',
  vehicle: 'Vehicle Information',
  documents: 'Documents',
  notifications: 'Notifications',
  help: 'Help & Support',
  privacy: 'Privacy & Security',
};

const INPUT_CLASS = 'min-h-12 w-full rounded-xl border border-surface-border bg-slate-950/70 px-3.5 text-[14px] text-white placeholder:text-slate-500 focus:border-cyan-400 focus:outline-none focus:ring-2 focus:ring-cyan-500/20';
const DOCUMENT_TYPES = [
  { key: 'license', label: 'Driver license' },
  { key: 'insurance', label: 'Insurance' },
  { key: 'permit', label: 'Delivery permit' },
] as const;

interface AccountDocument {
  document_type: (typeof DOCUMENT_TYPES)[number]['key'];
  original_name: string;
  mime_type: string;
  status: 'PENDING' | 'VERIFIED' | 'REJECTED';
  uploaded_at: string;
}

interface AccountSettingsPanelProps {
  section: AccountSection;
  rider: RiderProfile;
  onClose: () => void;
  onSectionChange: (section: AccountSection) => void;
  onProfileSaved: (profile: Partial<RiderProfile>) => void;
}

export function AccountSettingsPanel({ section, rider, onClose, onSectionChange, onProfileSaved }: AccountSettingsPanelProps) {
  const logout = useRiderStore((state) => state.logout);
  const [notice, setNotice] = useState('');
  const [saving, setSaving] = useState(false);
  const [documents, setDocuments] = useState<AccountDocument[]>([]);
  const [notifications, setNotifications] = useState({
    in_app: rider.notification_preferences?.in_app ?? true,
    browser: rider.notification_preferences?.browser ?? false,
  });
  const [pinEnabled, setPinEnabled] = useState(Boolean(rider.quick_pin_enabled));

  useEffect(() => {
    setNotice('');
    if (section !== 'documents') return;
    let active = true;
    void fetchJson<{ documents: AccountDocument[] }>('/session/documents')
      .then(({ data, ok }) => {
        if (active && ok) setDocuments(Array.isArray(data?.documents) ? data.documents : []);
      })
      .catch(() => {
        if (active) setNotice('Could not load your documents. Try again.');
      });
    return () => { active = false; };
  }, [section]);

  const saveProfile = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const payload = Object.fromEntries(form.entries());
    setSaving(true);
    setNotice('');
    try {
      const { data, ok } = await fetchJson<{ ok: boolean; profile: Partial<RiderProfile>; message?: string }>('/session/profile', {
        method: 'POST',
        body: JSON.stringify(payload),
      });
      if (!ok || !data?.ok) {
        setNotice(data?.message ?? 'Could not save your information.');
        return;
      }
      onProfileSaved(data.profile);
      setNotice('Information saved.');
    } catch {
      setNotice('Could not reach the server. Try again.');
    } finally {
      setSaving(false);
    }
  };

  const updateNotifications = async (next: typeof notifications) => {
    setSaving(true);
    setNotice('');
    try {
      const { data, ok } = await fetchJson<{ ok: boolean; preferences: typeof notifications }>('/session/notifications', {
        method: 'POST',
        body: JSON.stringify({ preferences: next }),
      });
      if (!ok || !data?.ok) {
        setNotice('Could not save notification preferences.');
        return;
      }
      setNotifications(data.preferences);
      onProfileSaved({ notification_preferences: data.preferences });
      setNotice('Notification preferences saved.');
    } catch {
      setNotice('Could not reach the server. Try again.');
    } finally {
      setSaving(false);
    }
  };

  const uploadDocument = async (documentType: AccountDocument['document_type'], file?: File) => {
    if (!file) return;
    if (file.size > 4 * 1024 * 1024) {
      setNotice('Choose a document smaller than 4 MB.');
      return;
    }
    setSaving(true);
    setNotice('');
    try {
      const dataUri = await fileAsDataUri(file);
      const { data, ok } = await fetchJson<{ ok: boolean; document: AccountDocument; message?: string }>('/session/documents', {
        method: 'POST',
        body: JSON.stringify({ document_type: documentType, file_name: file.name, data_uri: dataUri }),
      });
      if (!ok || !data?.ok) {
        setNotice(data?.message ?? 'Document upload failed.');
        return;
      }
      setDocuments((current) => [...current.filter((item) => item.document_type !== documentType), data.document]);
      setNotice('Document uploaded for review.');
    } catch {
      setNotice('Document upload failed. Check your connection and try again.');
    } finally {
      setSaving(false);
    }
  };

  const saveSupportRequest = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const formElement = event.currentTarget;
    const form = new FormData(formElement);
    setSaving(true);
    setNotice('');
    try {
      const { data, ok } = await fetchJson<{ ok: boolean; request_id: number; message?: string }>('/session/support', {
        method: 'POST',
        body: JSON.stringify(Object.fromEntries(form.entries())),
      });
      if (!ok || !data?.ok) {
        setNotice(data?.message ?? 'Could not send your request.');
        return;
      }
      formElement.reset();
      setNotice(`Request #${data.request_id} sent to rider support.`);
    } catch {
      setNotice('Could not reach the server. Try again.');
    } finally {
      setSaving(false);
    }
  };

  const changePassword = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const formElement = event.currentTarget;
    const form = new FormData(formElement);
    const currentPassword = String(form.get('current_password') ?? '');
    const newPassword = String(form.get('new_password') ?? '');
    if (newPassword !== String(form.get('confirm_password') ?? '')) {
      setNotice('The new passwords do not match.');
      return;
    }
    setSaving(true);
    setNotice('');
    try {
      const { data, ok } = await fetchJson<{ ok: boolean; error?: string; message?: string }>('/session/security/password', {
        method: 'POST',
        body: JSON.stringify({ current_password: currentPassword, new_password: newPassword }),
      });
      if (!ok || !data?.ok) {
        setNotice(data?.message ?? (data?.error === 'CURRENT_PASSWORD_INVALID' ? 'Current password is incorrect.' : 'Could not update your password.'));
        return;
      }
      formElement.reset();
      setNotice('Password updated.');
    } catch {
      setNotice('Could not reach the server. Try again.');
    } finally {
      setSaving(false);
    }
  };

  const savePin = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const formElement = event.currentTarget;
    const form = new FormData(formElement);
    const pin = String(form.get('pin') ?? '');
    if (pin !== String(form.get('confirm_pin') ?? '')) {
      setNotice('The PIN values do not match.');
      return;
    }
    setSaving(true);
    setNotice('');
    try {
      const { data, ok } = await fetchJson<{ ok: boolean; quick_pin_enabled: boolean; error?: string; message?: string }>('/session/security/pin', {
        method: 'POST',
        body: JSON.stringify({ current_password: String(form.get('current_password') ?? ''), pin }),
      });
      if (!ok || !data?.ok) {
        setNotice(data?.message ?? (data?.error === 'CURRENT_PASSWORD_INVALID' ? 'Current password is incorrect.' : 'PIN must contain 4 to 6 digits.'));
        return;
      }
      setPinEnabled(data.quick_pin_enabled);
      formElement.reset();
      setNotice(data.quick_pin_enabled ? 'Quick PIN enabled.' : 'Quick PIN removed.');
    } catch {
      setNotice('Could not reach the server. Try again.');
    } finally {
      setSaving(false);
    }
  };

  const toggleBrowserNotifications = async () => {
    const granted = await requestNotificationPermission();
    if (!granted) {
      setNotice('Browser notifications were not enabled. Check your browser permission.');
      return;
    }
    await updateNotifications({ ...notifications, browser: true });
  };

  return (
    <div className="fixed inset-0 z-[150] flex justify-center bg-slate-950/80 backdrop-blur-sm">
      <section className="phone-col flex h-[100dvh] w-full max-w-xl flex-col border-x border-surface-border bg-slate-950 shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="account-panel-title">
        <header className="flex shrink-0 items-center gap-3 border-b border-surface-border px-4 pb-3 pt-[max(16px,env(safe-area-inset-top))]">
          <button type="button" onClick={onClose} className="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-surface-border bg-surface-card text-slate-200" aria-label="Back to profile">
            <LucideIcon name="ArrowLeft" size={19} />
          </button>
          <h2 id="account-panel-title" className="min-w-0 flex-1 truncate text-[17px] font-bold text-white">{SECTION_TITLES[section]}</h2>
          <button type="button" onClick={onClose} className="inline-flex h-11 w-11 items-center justify-center rounded-xl text-slate-400 hover:bg-surface-card" aria-label="Close settings">
            <LucideIcon name="X" size={18} />
          </button>
        </header>

        <div className="flex-1 space-y-4 overflow-y-auto px-4 py-4 pb-10">
          {notice ? <div role="status" className="rounded-xl border border-cyan-700/40 bg-cyan-500/10 px-3.5 py-3 text-[13px] font-medium text-cyan-100">{notice}</div> : null}

          {section === 'personal' || section === 'vehicle' ? (
            <form className="space-y-4" onSubmit={(event) => void saveProfile(event)}>
              {section === 'personal' ? (
                <>
                  <Field label="Full name"><input className={INPUT_CLASS} name="name" required maxLength={120} defaultValue={rider.name} autoComplete="name" /></Field>
                  <Field label="Phone number"><input className={INPUT_CLASS} name="phone" maxLength={24} defaultValue={rider.phone ?? ''} autoComplete="tel" inputMode="tel" /></Field>
                  <Field label="Home address"><input className={INPUT_CLASS} name="address" maxLength={255} defaultValue={rider.address ?? ''} autoComplete="street-address" /></Field>
                  <Field label="City"><input className={INPUT_CLASS} name="city" maxLength={80} defaultValue={rider.city ?? ''} autoComplete="address-level2" /></Field>
                  <input type="hidden" name="vehicle_type" value={rider.vehicle_type ?? 'MOTORCYCLE'} />
                  <input type="hidden" name="vehicle_plate" value={rider.vehicle_plate ?? ''} />
                  <input type="hidden" name="vehicle_or_number" value={rider.vehicle_or_number ?? ''} />
                  <input type="hidden" name="vehicle_cr_number" value={rider.vehicle_cr_number ?? ''} />
                </>
              ) : (
                <>
                  <Field label="Vehicle type">
                    <select className={INPUT_CLASS} name="vehicle_type" defaultValue={rider.vehicle_type ?? 'MOTORCYCLE'}>
                      <option value="MOTORCYCLE">Motorcycle</option><option value="EBIKE">E-bike</option><option value="CAR">Car</option><option value="VAN">Van</option>
                    </select>
                  </Field>
                  <Field label="Plate number"><input className={INPUT_CLASS} name="vehicle_plate" maxLength={32} defaultValue={rider.vehicle_plate ?? ''} /></Field>
                  <Field label="OR number"><input className={INPUT_CLASS} name="vehicle_or_number" maxLength={64} defaultValue={rider.vehicle_or_number ?? ''} /></Field>
                  <Field label="CR number"><input className={INPUT_CLASS} name="vehicle_cr_number" maxLength={64} defaultValue={rider.vehicle_cr_number ?? ''} /></Field>
                  <input type="hidden" name="name" value={rider.name} />
                  <input type="hidden" name="phone" value={rider.phone ?? ''} />
                  <input type="hidden" name="address" value={rider.address ?? ''} />
                  <input type="hidden" name="city" value={rider.city ?? ''} />
                </>
              )}
              <Button type="submit" size="block" disabled={saving}>{saving ? 'Saving…' : 'Save changes'}</Button>
            </form>
          ) : null}

          {section === 'documents' ? (
            <div className="space-y-3">
              <p className="text-[13px] leading-5 text-slate-400">Upload clear PDF or image copies. New uploads are marked pending until reviewed.</p>
              {DOCUMENT_TYPES.map((document) => {
                const saved = documents.find((item) => item.document_type === document.key);
                return (
                  <div key={document.key} className="flex flex-wrap items-center gap-3 border-b border-surface-border py-3 last:border-0">
                    <div className="min-w-0 flex-1">
                      <div className="text-[14px] font-semibold text-white">{document.label}</div>
                      <div className="truncate text-[12px] text-slate-400">{saved ? saved.original_name : 'No file uploaded'}</div>
                    </div>
                    {saved ? <StatusBadge status={saved.status} /> : null}
                    <label className="inline-flex min-h-10 cursor-pointer items-center gap-2 rounded-xl border border-surface-border bg-surface-card px-3 text-[12px] font-bold text-cyan-200 hover:bg-slate-800">
                      <LucideIcon name="Upload" size={15} />
                      {saved ? 'Replace' : 'Upload'}
                      <input className="sr-only" type="file" accept=".pdf,image/png,image/jpeg,image/webp" disabled={saving} onChange={(event) => void uploadDocument(document.key, event.target.files?.[0])} />
                    </label>
                  </div>
                );
              })}
              <p className="text-[11px] text-slate-500">Maximum 4 MB per file. Supported: PDF, PNG, JPG, WebP.</p>
            </div>
          ) : null}

          {section === 'notifications' ? (
            <div className="space-y-2">
              <ToggleRow title="In-app dispatch alerts" description="Show new assignment and delivery updates in the rider app." checked={notifications.in_app} disabled={saving} onChange={(checked) => void updateNotifications({ ...notifications, in_app: checked })} />
              <ToggleRow title="Browser notifications" description="Allow this browser to show MotoBook alerts." checked={notifications.browser && (typeof Notification === 'undefined' || Notification.permission === 'granted')} disabled={saving} onChange={(checked) => checked ? void toggleBrowserNotifications() : void updateNotifications({ ...notifications, browser: false })} />
              <div className="border-b border-surface-border px-3 py-4">
                <div className="text-[14px] font-semibold text-white">SMS alerts</div>
                <div className="mt-1 text-[12px] leading-5 text-slate-400">SMS delivery is not connected yet. Your saved phone number is {rider.phone || 'not set'}.</div>
              </div>
            </div>
          ) : null}

          {section === 'help' ? (
            <form className="space-y-4" onSubmit={(event) => void saveSupportRequest(event)}>
              <Field label="Topic">
                <select className={INPUT_CLASS} name="category" defaultValue="Account">
                  <option>Account</option><option>Delivery</option><option>Payment</option><option>Documents</option><option>Safety</option><option>Other</option>
                </select>
              </Field>
              <Field label="Subject"><input className={INPUT_CLASS} name="subject" maxLength={120} required placeholder="What do you need help with?" /></Field>
              <Field label="Message"><textarea className={`${INPUT_CLASS} min-h-32 resize-y py-3`} name="message" maxLength={4000} required placeholder="Describe the issue. Include an order code if relevant." /></Field>
              <Button type="submit" size="block" disabled={saving}>{saving ? 'Sending…' : 'Send support request'}</Button>
            </form>
          ) : null}

          {section === 'privacy' ? (
            <div className="space-y-5">
              <section className="space-y-3">
                <h3 className="text-[12px] font-bold uppercase tracking-[0.08em] text-cyan-300">Change password</h3>
                <form className="space-y-3" onSubmit={(event) => void changePassword(event)}>
                  <Field label="Current password"><input className={INPUT_CLASS} type="password" name="current_password" autoComplete="current-password" required /></Field>
                  <Field label="New password"><input className={INPUT_CLASS} type="password" name="new_password" autoComplete="new-password" minLength={10} required /></Field>
                  <Field label="Confirm new password"><input className={INPUT_CLASS} type="password" name="confirm_password" autoComplete="new-password" minLength={10} required /></Field>
                  <Button type="submit" variant="slate" size="block" disabled={saving}>{saving ? 'Updating…' : 'Update password'}</Button>
                </form>
              </section>

              <section className="space-y-3 border-t border-surface-border pt-4">
                <div className="flex items-center justify-between gap-3">
                  <div><h3 className="text-[12px] font-bold uppercase tracking-[0.08em] text-cyan-300">Quick PIN</h3><p className="mt-1 text-[12px] text-slate-400">{pinEnabled ? 'PIN enabled' : 'No PIN set'}</p></div>
                  {pinEnabled ? <StatusBadge status="VERIFIED" /> : null}
                </div>
                <form className="space-y-3" onSubmit={(event) => void savePin(event)}>
                  <Field label="Current password"><input className={INPUT_CLASS} type="password" name="current_password" autoComplete="current-password" required /></Field>
                  <Field label={pinEnabled ? 'New PIN (leave blank to remove)' : 'New 4–6 digit PIN'}><input className={INPUT_CLASS} type="password" name="pin" inputMode="numeric" pattern="[0-9]{4,6}" maxLength={6} autoComplete="off" /></Field>
                  <Field label="Confirm PIN"><input className={INPUT_CLASS} type="password" name="confirm_pin" inputMode="numeric" maxLength={6} autoComplete="off" /></Field>
                  <Button type="submit" variant="slate" size="block" disabled={saving}>{saving ? 'Saving…' : pinEnabled ? 'Update or remove PIN' : 'Enable PIN'}</Button>
                </form>
              </section>

              <section className="space-y-3 border-t border-surface-border pt-4">
                <div className="flex items-start justify-between gap-3">
                  <div><h3 className="text-[12px] font-bold uppercase tracking-[0.08em] text-cyan-300">Two-factor authentication</h3><p className="mt-1 text-[12px] leading-5 text-slate-400">2FA is not available until MotoBook connects an email or SMS verification provider.</p></div>
                  <StatusBadge status="UNAVAILABLE" />
                </div>
                <Button type="button" variant="outline" size="md" onClick={() => onSectionChange('help')}>Contact support about 2FA</Button>
              </section>

              <section className="flex items-center justify-between gap-3 border-t border-surface-border pt-4">
                <div><h3 className="text-[12px] font-bold uppercase tracking-[0.08em] text-cyan-300">Active session</h3><p className="mt-1 text-[12px] text-slate-400">This device · signed in as {rider.email}</p></div>
                <Button type="button" variant="danger" size="sm" onClick={() => void logout()}>Sign out</Button>
              </section>
            </div>
          ) : null}
        </div>
      </section>
    </div>
  );
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return <label className="block space-y-1.5"><span className="text-[12px] font-semibold text-slate-300">{label}</span>{children}</label>;
}

function ToggleRow({ title, description, checked, disabled, onChange }: { title: string; description: string; checked: boolean; disabled: boolean; onChange: (checked: boolean) => void }) {
  return (
    <div className="flex items-center justify-between gap-4 border-b border-surface-border px-3 py-4 last:border-0">
      <div className="min-w-0"><div className="text-[14px] font-semibold text-white">{title}</div><div className="mt-1 text-[12px] leading-5 text-slate-400">{description}</div></div>
      <button type="button" role="switch" aria-checked={checked} disabled={disabled} onClick={() => onChange(!checked)} className={`relative h-7 w-12 shrink-0 rounded-full border transition-colors disabled:opacity-50 ${checked ? 'border-cyan-300 bg-cyan-500' : 'border-slate-600 bg-slate-800'}`}>
        <span className={`absolute top-0.5 h-5 w-5 rounded-full bg-white transition-transform ${checked ? 'translate-x-[22px]' : 'translate-x-0.5'}`} />
      </button>
    </div>
  );
}

function StatusBadge({ status }: { status: AccountDocument['status'] | 'UNAVAILABLE' }) {
  const label = status === 'VERIFIED' ? 'Verified' : status === 'REJECTED' ? 'Needs update' : status === 'UNAVAILABLE' ? 'Unavailable' : 'Pending review';
  const color = status === 'VERIFIED' ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300' : status === 'REJECTED' ? 'border-rose-500/30 bg-rose-500/10 text-rose-300' : status === 'UNAVAILABLE' ? 'border-slate-600 bg-slate-800 text-slate-300' : 'border-amber-500/30 bg-amber-500/10 text-amber-200';
  return <span className={`shrink-0 rounded-full border px-2.5 py-1 text-[10px] font-bold uppercase ${color}`}>{label}</span>;
}

function fileAsDataUri(file: File): Promise<string> {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onerror = () => reject(new Error('FILE_READ_FAILED'));
    reader.onload = () => typeof reader.result === 'string' ? resolve(reader.result) : reject(new Error('FILE_READ_FAILED'));
    reader.readAsDataURL(file);
  });
}