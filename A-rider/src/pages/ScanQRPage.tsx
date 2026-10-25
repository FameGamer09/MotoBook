import { FormEvent, useCallback, useEffect, useRef, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Camera, CameraOff, ChevronLeft, ScanLine } from 'lucide-react';
import { useOrderStore } from '@/store/useOrderStore';
import { useRiderStore } from '@/store/useRiderStore';
import { useRiderNotice } from '@/components/RiderNotice';

type DetectedBarcode = { rawValue: string };
type BarcodeDetectorLike = { detect: (source: HTMLVideoElement) => Promise<DetectedBarcode[]> };
type BarcodeWindow = typeof window & { BarcodeDetector?: new (options: { formats: string[] }) => BarcodeDetectorLike };

function normalizeScannedValue(raw: string) {
  const value = raw.trim();
  if (!value) return '';
  try {
    const parsed: unknown = JSON.parse(value);
    if (parsed && typeof parsed === 'object') {
      const data = parsed as Record<string, unknown>;
      const candidate = data.order_code ?? data.orderCode ?? data.merchant_order_id ?? data.merchantOrderId ?? data.code ?? data.order_id ?? data.orderId;
      if (candidate != null) return String(candidate).trim().toUpperCase();
    }
  } catch { /* Plain text QR payloads are supported below. */ }
  try {
    const url = new URL(value);
    const candidate = url.searchParams.get('order_code') ?? url.searchParams.get('orderCode') ?? url.searchParams.get('code') ?? url.searchParams.get('order_id');
    if (candidate) return candidate.trim().toUpperCase();
  } catch { /* Not a URL; compare as an order code. */ }
  return value.toUpperCase();
}

export function ScanQRPage() {
  const [scanning, setScanning] = useState(false);
  const [cameraReady, setCameraReady] = useState(false);
  const [cameraError, setCameraError] = useState('');
  const [manualCode, setManualCode] = useState('');
  const videoRef = useRef<HTMLVideoElement>(null);
  const orders = useOrderStore((s) => s.list);
  const riderId = useRiderStore((s) => s.profile?.id);
  const nav = useNavigate();
  const showNotice = useRiderNotice();
  const handleScannedValue = useCallback((raw: string) => {
    const value = normalizeScannedValue(raw);
    const order = orders.find((candidate) => candidate.rider_id === riderId && [
      candidate.order_code,
      candidate.merchant_order_id,
      String(candidate.id),
    ].some((code) => code?.trim().toUpperCase() === value));

    if (!order) {
      showNotice('That code does not match an active order assigned to your rider account. Check the code and try again.');
      return;
    }
    showNotice(`Order ${order.order_code} verified.`);
    if (['ARRIVED_AT_PICKUP', 'ORDER_VERIFIED'].includes(order.state)) nav('/checklist');
    else if (['NAVIGATING_TO_DROP_OFF', 'ARRIVED_AT_DROP_OFF', 'PROOF_SUBMITTED'].includes(order.state)) nav('/pod');
    else nav('/delivery');
  }, [nav, orders, riderId, showNotice]);

  const handleScannedValueRef = useRef(handleScannedValue);
  useEffect(() => { handleScannedValueRef.current = handleScannedValue; }, [handleScannedValue]);

  useEffect(() => {
    if (!scanning) return undefined;
    let cancelled = false;
    let stream: MediaStream | null = null;
    let timer = 0;
    const BarcodeDetector = (window as BarcodeWindow).BarcodeDetector;

    if (!BarcodeDetector) {
      setCameraError('Camera scanning is not supported in this browser. Enter the order code below instead.');
      setScanning(false);
      return undefined;
    }
    if (!navigator.mediaDevices?.getUserMedia) {
      setCameraError('Camera access is unavailable. Open the rider app over HTTPS or localhost, or enter the order code below.');
      setScanning(false);
      return undefined;
    }

    void (async () => {
      try {
        const detector = new BarcodeDetector({ formats: ['qr_code'] });
        stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false });
        if (cancelled) {
          stream.getTracks().forEach((track) => track.stop());
          return;
        }
        const video = videoRef.current;
        if (!video) return;
        video.srcObject = stream;
        await video.play();
        setCameraReady(true);

        const scanFrame = async () => {
          if (cancelled || video.readyState < HTMLMediaElement.HAVE_CURRENT_DATA) {
            if (!cancelled) timer = window.setTimeout(() => void scanFrame(), 250);
            return;
          }
          try {
            const results = await detector.detect(video);
            if (results[0]?.rawValue) {
              setCameraReady(false);
              setScanning(false);
              handleScannedValueRef.current(results[0].rawValue);
              return;
            }
          } catch { /* Continue scanning; transient frame decode errors are normal. */ }
          if (!cancelled) timer = window.setTimeout(() => void scanFrame(), 250);
        };
        void scanFrame();
      } catch (error) {
        if (!cancelled) {
          const name = error instanceof DOMException ? error.name : '';
          setCameraError(name === 'NotAllowedError'
            ? 'Camera permission was denied. Allow camera access in your browser or enter the order code below.'
            : 'Could not start the camera. Check that it is available, or enter the order code below.');
          setScanning(false);
        }
      }
    })();

    return () => {
      cancelled = true;
      window.clearTimeout(timer);
      stream?.getTracks().forEach((track) => track.stop());
      if (videoRef.current) videoRef.current.srcObject = null;
    };
  }, [scanning]);

  const submitManualCode = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (!manualCode.trim()) return;
    handleScannedValue(manualCode);
  };

  return (
    <main className="phone-col min-h-[100dvh] space-y-4 bg-slate-950 px-4 pb-28 pt-5 text-white lg:px-8 lg:pt-8">
      <header className="flex items-center gap-3">
        <Link to="/" aria-label="Back to dashboard" className="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-surface-border bg-surface-card text-slate-200"><ChevronLeft size={20} /></Link>
        <div><h1 className="text-lg font-extrabold tracking-tight">Scan QR</h1><p className="text-xs text-slate-400">Verify a rider order code</p></div>
      </header>

      <section className="overflow-hidden rounded-3xl border border-cyan-500/25 bg-surface-card">
        <div className="relative aspect-[4/3] bg-slate-950">
          <video ref={videoRef} className={`${cameraReady ? 'block' : 'hidden'} h-full w-full object-cover`} playsInline muted aria-label="QR code camera preview" />
          {!cameraReady ? (
            <div className="absolute inset-0 flex flex-col items-center justify-center px-8 text-center">
              <span className="inline-flex h-16 w-16 items-center justify-center rounded-3xl border border-cyan-400/25 bg-cyan-500/10 text-cyan-200"><ScanLine size={32} /></span>
              <p className="mt-4 text-sm font-semibold text-slate-200">Point your camera at the order QR code</p>
              <p className="mt-1 text-xs leading-5 text-slate-400">Keep the full code inside the frame.</p>
            </div>
          ) : null}
          {scanning && cameraReady ? <div className="pointer-events-none absolute inset-[13%] rounded-3xl border-2 border-cyan-300 shadow-[0_0_30px_rgba(34,211,238,0.25)]"><span className="absolute left-1/2 top-1/2 h-px w-full -translate-x-1/2 bg-cyan-300/90 shadow-[0_0_14px_rgba(34,211,238,0.9)]" /></div> : null}
        </div>
        <div className="space-y-3 p-4">
          {cameraError ? <p role="status" className="rounded-xl border border-amber-400/25 bg-amber-400/10 px-3 py-2.5 text-xs leading-5 text-amber-100">{cameraError}</p> : null}
          <button type="button" onClick={() => { setCameraError(''); setScanning((active) => { if (active) setCameraReady(false); return !active; }); }} className="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-2xl bg-cyan-400 px-4 text-sm font-extrabold text-slate-950 transition hover:bg-cyan-300">
            {scanning ? <><CameraOff size={18} /> Stop camera</> : <><Camera size={18} /> Start camera scanner</>}
          </button>
        </div>
      </section>

      <section className="rounded-2xl border border-surface-border bg-surface-card p-4">
        <h2 className="text-sm font-bold text-white">Enter order code manually</h2>
        <p className="mt-1 text-xs leading-5 text-slate-400">Use this if camera access is unavailable. Only orders assigned to your account can be verified.</p>
        <form onSubmit={submitManualCode} className="mt-3 flex gap-2">
          <input value={manualCode} onChange={(event) => setManualCode(event.target.value)} autoCapitalize="characters" autoComplete="off" placeholder="Example: MB-88491" aria-label="Order code" className="min-h-12 min-w-0 flex-1 rounded-xl border border-slate-700 bg-slate-950 px-3 text-sm font-mono text-white placeholder:text-slate-600 focus:border-cyan-400 focus:outline-none focus:ring-2 focus:ring-cyan-400/20" />
          <button type="submit" disabled={!manualCode.trim()} className="min-h-12 rounded-xl border border-cyan-400/30 bg-cyan-500/15 px-4 text-sm font-bold text-cyan-100 disabled:cursor-not-allowed disabled:opacity-40">Verify</button>
        </form>
      </section>
    </main>
  );
}
