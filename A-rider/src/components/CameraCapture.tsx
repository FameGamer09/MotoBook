import { useCallback, useEffect, useRef, useState } from 'react';
import { LucideIcon, cn } from './ui/Primitive';
import { Button } from './ui/Button';

export interface CameraCaptureProps {
  onChange?: (payload: { dataUri: string; lat?: number | null; lng?: number | null; capturedAtMs: number } | null) => void;
  value?: string | null;
  required?: boolean;
  className?: string;
  aspectRatio?: `${number}:${number}`;
  watermark?: boolean;
  bannerLabel?: string;
  bannerMeta?: string;
}

export function CameraCapture({
  onChange,
  value,
  required,
  className,
  aspectRatio = '4:3',
  watermark = true,
  bannerLabel,
  bannerMeta,
}: CameraCaptureProps) {
  const videoRef = useRef<HTMLVideoElement | null>(null);
  const canvasRef = useRef<HTMLCanvasElement | null>(null);
  const streamRef = useRef<MediaStream | null>(null);
  const fileRef = useRef<HTMLInputElement | null>(null);
  const [live, setLive] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [preview, setPreview] = useState<string | null>(value ?? null);

  useEffect(() => { setPreview(value ?? null); }, [value]);

  const emit = useCallback((dataUri: string | null) => {
    setPreview(dataUri);
    if (!onChange) return;
    if (!dataUri) { onChange(null); return; }
    if (navigator.geolocation && dataUri) {
      navigator.geolocation.getCurrentPosition(
        (p) => onChange({ dataUri, lat: p.coords.latitude, lng: p.coords.longitude, capturedAtMs: Date.now() }),
        () => onChange({ dataUri, lat: null, lng: null, capturedAtMs: Date.now() }),
        { enableHighAccuracy: true, timeout: 8_000, maximumAge: 30_000 },
      );
    } else {
      onChange({ dataUri, lat: null, lng: null, capturedAtMs: Date.now() });
    }
  }, [onChange]);

  const stopStream = () => {
    const s = streamRef.current;
    if (s) {
      s.getTracks().forEach((t) => t.stop());
      streamRef.current = null;
    }
    setLive(false);
    if (videoRef.current) videoRef.current.srcObject = null;
  };

  useEffect(() => () => stopStream(), []);

  const startCamera = async () => {
    setError(null);
    if (!navigator.mediaDevices?.getUserMedia) {
      fileRef.current?.click();
      return;
    }
    try {
      const stream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 960 } },
        audio: false,
      });
      streamRef.current = stream;
      if (videoRef.current) {
        videoRef.current.srcObject = stream;
        await videoRef.current.play();
      }
      setLive(true);
    } catch (e: any) {
      setError('CAMERA_DENIED_OR_UNAVAILABLE');
      fileRef.current?.click();
    }
  };

  const toCompressedJpeg = async (
    ctx: CanvasRenderingContext2D,
    w: number,
    h: number,
  ): Promise<string> => {
    if (watermark) {
      const ts = new Date();
      const label = (bannerLabel && bannerLabel.trim() ? bannerLabel.trim() : 'POD').toUpperCase();
      const meta = bannerMeta?.trim();
      const tsStr = ts.toLocaleString('en-PH');
      const line1Parts: string[] = [label];
      if (meta) line1Parts.push(meta);
      line1Parts.push(tsStr);
      const line1 = line1Parts.join(' · ');
      let geo = 'Loc: pending…';
      const loc = await new Promise<{ lat: number; lng: number } | null>((resolve) => {
        if (!navigator.geolocation) resolve(null);
        navigator.geolocation.getCurrentPosition(
          (p) => resolve({ lat: p.coords.latitude, lng: p.coords.longitude }),
          () => resolve(null),
          { enableHighAccuracy: true, timeout: 5_000, maximumAge: 30_000 },
        );
      });
      if (loc) geo = `Lat ${loc.lat.toFixed(6)}  Lng ${loc.lng.toFixed(6)}`;
      ctx.font = 'bold 22px "JetBrains Mono", monospace';
      const pad = 22;
      const lineH = 28;
      const metrics = [ctx.measureText(line1), ctx.measureText(geo)];
      const bw = Math.max(metrics[0].width, metrics[1].width) + pad * 2;
      const bh = pad * 2 + lineH * 2;
      ctx.fillStyle = 'rgba(15, 23, 42, 0.78)';
      ctx.fillRect(16, h - bh - 16, bw, bh);
      ctx.fillStyle = '#E2E8F0';
      ctx.fillText(line1, 16 + pad, h - bh - 16 + pad + 4);
      ctx.fillStyle = '#93C5FD';
      ctx.fillText(geo, 16 + pad, h - bh - 16 + pad + 4 + lineH);
    }
    return ctx.canvas.toDataURL('image/jpeg', 0.86);
  };

  const snap = async () => {
    const video = videoRef.current;
    const canvas = canvasRef.current;
    if (!video || !canvas) return;
    const w = video.videoWidth || 1280;
    const h = video.videoHeight || 960;
    canvas.width = w;
    canvas.height = h;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;
    ctx.drawImage(video, 0, 0, w, h);
    const dataUri = await toCompressedJpeg(ctx, w, h);
    stopStream();
    emit(dataUri);
  };

  const clear = () => {
    stopStream();
    emit(null);
  };

  const onFile = (ev: React.ChangeEvent<HTMLInputElement>) => {
    const f = ev.target.files?.[0];
    if (!f) return;
    ev.target.value = '';
    const reader = new FileReader();
    reader.onload = () => {
      const src = reader.result as string;
      const img = new Image();
      img.onload = () => {
        const off = document.createElement('canvas');
        const w = img.naturalWidth || 1280;
        const h = img.naturalHeight || 960;
        off.width = w;
        off.height = h;
        const ctx = off.getContext('2d');
        if (!ctx) {
          emit(src);
          return;
        }
        ctx.drawImage(img, 0, 0, w, h);
        toCompressedJpeg(ctx, w, h)
          .then(emit)
          .catch(() => emit(src));
      };
      img.onerror = () => emit(src);
      img.src = src;
    };
    reader.readAsDataURL(f);
  };

  const [aw, ah] = aspectRatio.split(':').map((n) => parseInt(n, 10));
  const ratioStyle = { aspectRatio: `${aw ?? 4} / ${ah ?? 3}` };

  return (
    <div className={cn('w-full', className)}>
      <div
        className={cn(
          'relative w-full rounded-2xl border-2 border-dashed border-surface-border bg-surface-panel overflow-hidden flex items-center justify-center',
          !preview && !live ? 'border-dashed' : 'border-solid border-surface-border/80',
        )}
        style={ratioStyle}
      >
        {!preview && !live ? (
          <button
            type="button"
            onClick={startCamera}
            className="min-h-[48px] min-w-[48px] absolute inset-0 flex flex-col items-center justify-center gap-3 text-slate-300 hover:text-white hover:bg-white/5"
          >
            <span className="h-16 w-16 rounded-2xl bg-primary-soft border border-primary/30 inline-flex items-center justify-center text-white">
              <LucideIcon name="Camera" size={30} strokeWidth={1.75} />
            </span>
            <div>
              <div className="text-body font-semibold text-white">Tap to capture proof-of-delivery photo</div>
              <div className="text-helper text-slate-400 mt-1">Camera or photo library · 4:3 · Timestamp + GPS watermark</div>
            </div>
            {required ? (
              <div className="mt-1 text-helper font-semibold text-danger">* Required</div>
            ) : null}
          </button>
        ) : null}

        {live ? (
          <div className="absolute inset-0 bg-black">
            <video ref={videoRef} className="w-full h-full object-cover" playsInline muted autoPlay />
            <div className="absolute top-3 left-3 inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-danger/95 text-white text-[12px] font-bold tracking-wide">
              <span className="h-2 w-2 rounded-full bg-white animate-pulse" /> LIVE
            </div>
            <canvas ref={canvasRef} className="hidden" />
            <div className="absolute inset-x-4 bottom-4 grid grid-cols-2 gap-3">
              <Button variant="slate" size="lg" onClick={stopStream} iconLeft={<LucideIcon name="X" size={18} />}>Cancel</Button>
              <Button variant="primary" size="lg" onClick={snap} iconLeft={<LucideIcon name="Aperture" size={18} />}>Capture</Button>
            </div>
          </div>
        ) : preview ? (
          <div className="absolute inset-0">
            <img src={preview} alt="Proof of delivery preview" className="w-full h-full object-cover" />
            <div className="absolute top-3 right-3">
              <Button variant="danger" size="sm" onClick={clear} iconLeft={<LucideIcon name="Trash2" size={16} />}>Replace</Button>
            </div>
          </div>
        ) : null}
      </div>

      {error ? (
        <div className="mt-2 text-helper font-medium text-danger flex items-center gap-2">
          <LucideIcon name="AlertCircle" size={15} /> {error} — fallback: upload from library
        </div>
      ) : null}

      <input
        ref={fileRef}
        type="file"
        accept="image/*"
        capture="environment"
        className="hidden"
        onChange={onFile}
      />
      <canvas ref={canvasRef} className="hidden" />
    </div>
  );
}
