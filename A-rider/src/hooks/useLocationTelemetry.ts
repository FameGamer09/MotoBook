import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import type { GeofencePoint, LocationPoint, MotionState, OrderEntity } from '@/types/domain';
import { fetchJson } from '@/lib/api';
import { enqueueTelemetry, markSynced, peekUnsortedTelemetry, countUnsortedTelemetry } from '@/lib/telemetryQueue';
import { haversineMeters } from '@/lib/orderStateMachine';

const MOVING_SPEED_KMH = 15;
const MOVING_INTERVAL_MS = 3_000;
const IDLE_INTERVAL_MS = 15_000;
const LONG_IDLE_INTERVAL_MS = 60_000;
const LONG_IDLE_THRESHOLD_MS = 5 * 60 * 1000;
const SYNC_INTERVAL_MS = 5_000;
const PICKUP_RADIUS_M = 50;
const DROPOFF_RADIUS_M = 30;
const MIN_SIGNAL_DELTA_MS = 500;
const RAF_THROTTLE_MS = 50;
const GEOFENCE_DWELL_READS = 5;

export interface TelemetryHandle {
  lastPoint: LocationPoint | null;
  motionState: MotionState;
  queueDepth: number;
  lastSentAtMs: number | null;
  insideFence: GeofencePoint['label'] | null;
  distanceToPickupMeters: number | null;
  distanceToDropoffMeters: number | null;
  watchActive: boolean;
  error: string | null;
  flush: () => Promise<{ stored: number }>;
  requestSingleFix: () => void;
}

type IdleDetectorLike = {
  start: () => Promise<void>;
  addEventListener: (type: 'change', cb: (e: any) => void) => void;
  removeEventListener: (type: 'change', cb: (e: any) => void) => void;
  destroy?: () => void;
  userState?: 'active' | 'idle' | 'locked';
  screenState?: 'locked' | 'unlocked';
};

export function useLocationTelemetry(
  riderId: number | null,
  activeOrder: OrderEntity | null,
  onEnterGeofence: (which: GeofencePoint['label']) => void,
): TelemetryHandle {
  const [lastPoint, setLastPoint] = useState<LocationPoint | null>(null);
  const [motionState, setMotionState] = useState<MotionState>('IDLE');
  const [queueDepth, setQueueDepth] = useState<number>(0);
  const [lastSentAtMs, setLastSentAtMs] = useState<number | null>(null);
  const [watchActive, setWatchActive] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const lastSentRef = useRef<number>(0);
  const lastPointRef = useRef<GeolocationPosition | null>(null);
  const sentInsideRef = useRef<Set<GeofencePoint['label']>>(new Set());
  const dwellRef = useRef<Map<string, number>>(new Map());
  const longIdleRef = useRef<boolean>(false);

  const rafScheduledRef = useRef<boolean>(false);
  const rafLastRunRef = useRef<number>(0);
  const pendingPosRef = useRef<{ pos: GeolocationPosition; offline: boolean } | null>(null);

  const geofences: GeofencePoint[] = useMemo(() => {
    const out: GeofencePoint[] = [];
    if (activeOrder?.merchant_lat != null && activeOrder?.merchant_lng != null) {
      out.push({ label: 'pickup', lat: activeOrder.merchant_lat, lng: activeOrder.merchant_lng, radiusMeters: PICKUP_RADIUS_M });
    }
    if (activeOrder?.dropoff_lat != null && activeOrder?.dropoff_lng != null) {
      out.push({ label: 'dropoff', lat: activeOrder.dropoff_lat, lng: activeOrder.dropoff_lng, radiusMeters: DROPOFF_RADIUS_M });
    }
    return out;
  }, [activeOrder?.merchant_lat, activeOrder?.merchant_lng, activeOrder?.dropoff_lat, activeOrder?.dropoff_lng]);

  useEffect(() => {
    sentInsideRef.current = new Set();
    dwellRef.current = new Map();
  }, [activeOrder?.id]);

  const [distPickup, setDistPickup] = useState<number | null>(null);
  const [distDropoff, setDistDropoff] = useState<number | null>(null);
  const [insideFence, setInsideFence] = useState<GeofencePoint['label'] | null>(null);

  const syncIntervalMs = useMemo(() => {
    if (longIdleRef.current) return LONG_IDLE_INTERVAL_MS;
    return motionState === 'MOVING' ? MOVING_INTERVAL_MS : IDLE_INTERVAL_MS;
  }, [motionState]);

  const ingestPosition = useCallback((pos: GeolocationPosition, offlineFallback = false) => {
    const speed = typeof pos.coords.speed === 'number' ? pos.coords.speed : null;
    const speedKmh = speed != null ? speed * 3.6 : null;
    const motion: MotionState = speedKmh != null
      ? (speedKmh > MOVING_SPEED_KMH ? 'MOVING' : speedKmh > 1.0 ? 'IDLE' : 'STOPPED')
      : motionState;
    const point: LocationPoint = {
      lat: pos.coords.latitude,
      lng: pos.coords.longitude,
      heading: typeof pos.coords.heading === 'number' ? pos.coords.heading : null,
      speed_kmh: speedKmh,
      accuracy_m: typeof pos.coords.accuracy === 'number' ? pos.coords.accuracy : null,
      motion,
      offline: offlineFallback,
      t_ms: pos.timestamp,
    };
    lastPointRef.current = pos;
    setLastPoint(point);
    setMotionState(motion);

    setDistPickup(activeOrder?.merchant_lat != null && activeOrder?.merchant_lng != null
      ? haversineMeters(point.lat, point.lng, activeOrder.merchant_lat, activeOrder.merchant_lng)
      : null);
    setDistDropoff(activeOrder?.dropoff_lat != null && activeOrder?.dropoff_lng != null
      ? haversineMeters(point.lat, point.lng, activeOrder.dropoff_lat, activeOrder.dropoff_lng)
      : null);

    for (const fence of geofences) {
      const d = haversineMeters(point.lat, point.lng, fence.lat, fence.lng);
      const key = `${fence.label}-${fence.lat.toFixed(4)}-${fence.lng.toFixed(4)}`;
      if (d <= fence.radiusMeters) {
        const prev = dwellRef.current.get(key) ?? 0;
        const next = prev + 1;
        dwellRef.current.set(key, next);
        if (next >= GEOFENCE_DWELL_READS && !sentInsideRef.current.has(fence.label)) {
          sentInsideRef.current.add(fence.label);
          setInsideFence(fence.label);
          onEnterGeofence(fence.label);
        }
      } else if (d > fence.radiusMeters * 1.5) {
        dwellRef.current.delete(key);
        if (sentInsideRef.current.has(fence.label)) {
          sentInsideRef.current.delete(fence.label);
        }
      }
    }

    if (riderId) {
      void enqueueTelemetry([{
        riderId,
        riderOrderId: activeOrder?.id ?? null,
        lat: point.lat,
        lng: point.lng,
        heading: point.heading ?? null,
        speed_kmh: point.speed_kmh ?? null,
        accuracy_m: point.accuracy_m ?? null,
        motion: point.motion,
        t_ms: point.t_ms,
      }]);
    }
    void countUnsortedTelemetry().then(setQueueDepth);
  }, [activeOrder, geofences, motionState, onEnterGeofence, riderId]);

  const throttledIngest = useCallback((pos: GeolocationPosition, offlineFallback = false) => {
    pendingPosRef.current = { pos, offline: offlineFallback };
    if (rafScheduledRef.current) return;
    rafScheduledRef.current = true;
    const run = () => {
      const now = performance.now();
      const delta = now - rafLastRunRef.current;
      if (delta < RAF_THROTTLE_MS) {
        requestAnimationFrame(run);
        return;
      }
      rafLastRunRef.current = now;
      rafScheduledRef.current = false;
      const p = pendingPosRef.current;
      pendingPosRef.current = null;
      if (p) ingestPosition(p.pos, p.offline);
    };
    requestAnimationFrame(run);
  }, [ingestPosition]);

  useEffect(() => {
    if (riderId == null || !('geolocation' in navigator)) {
      setError(riderId == null ? 'NO_RIDER' : 'GEO_UNSUPPORTED');
      return;
    }
    let watchId: number | null = null;
    try {
      watchId = navigator.geolocation.watchPosition(
        (pos) => throttledIngest(pos, false),
        (err) => {
          setError(`GEO_ERROR_${err.code}`);
          if (lastPointRef.current) throttledIngest(lastPointRef.current, true);
        },
        { enableHighAccuracy: true, maximumAge: 2_000, timeout: 10_000 },
      );
      setWatchActive(true);
    } catch (e: any) { setError(e?.message ?? 'GEO_WATCH_FAIL'); }

    let idleDetector: IdleDetectorLike | null = null;
    let idleChangeListener: ((e: any) => void) | null = null;
    (async () => {
      try {
        const navAny = navigator as any;
        if (typeof navAny.IdleDetector === 'function') {
          const detector: IdleDetectorLike = new navAny.IdleDetector({ threshold: LONG_IDLE_THRESHOLD_MS });
          await detector.start();
          idleDetector = detector;
          idleChangeListener = (e: any) => {
            const isLongIdle = (detector.userState === 'idle' && detector.screenState === 'locked')
              || detector.userState === 'idle';
            longIdleRef.current = !!isLongIdle;
          };
          detector.addEventListener('change', idleChangeListener);
          idleChangeListener(null);
        }
      } catch {
        // IdleDetector unavailable; proceed without long-idle throttling.
      }
    })();

    const syncTick = setInterval(async () => {
      const count = await countUnsortedTelemetry();
      setQueueDepth(count);
      const now = Date.now();
      const intervalMs = longIdleRef.current
        ? LONG_IDLE_INTERVAL_MS
        : (motionState === 'MOVING' ? MOVING_INTERVAL_MS : IDLE_INTERVAL_MS);
      if (now - lastSentRef.current < Math.min(intervalMs, MIN_SIGNAL_DELTA_MS)) return;
      const rows = await peekUnsortedTelemetry(400);
      if (!rows.length) return;
      try {
        const hasNetwork = navigator.onLine !== false;
        if (!hasNetwork) return;
        const { data, ok } = await fetchJson<{ ok: boolean; stored: number; server_ts_ms: number }>('/telemetry/batch', {
          method: 'POST',
          body: JSON.stringify({
            rider_order_id: activeOrder?.id ?? null,
            points: rows.map((r) => ({
              lat: r.lat,
              lng: r.lng,
              heading: r.heading ?? null,
              speed_kmh: r.speed_kmh ?? null,
              accuracy_m: r.accuracy_m ?? null,
              motion: r.motion ?? 'IDLE',
              offline: 0,
              t_ms: r.t_ms,
            })),
          }),
        });
        if (ok && data?.ok) {
          lastSentRef.current = now;
          setLastSentAtMs(now);
          const ids = rows.map((r) => r.id!).filter((id): id is number => id != null);
          await markSynced(ids);
          void countUnsortedTelemetry().then(setQueueDepth);
        }
      } catch {
        // offline queued, retry on next tick
      }
    }, SYNC_INTERVAL_MS);

    return () => {
      if (watchId != null) try { navigator.geolocation.clearWatch(watchId); } catch { /* noop */ }
      if (idleDetector && idleChangeListener) {
        try { idleDetector.removeEventListener('change', idleChangeListener); } catch { /* noop */ }
        try { idleDetector.destroy?.(); } catch { /* noop */ }
      }
      clearInterval(syncTick);
      rafScheduledRef.current = false;
      pendingPosRef.current = null;
      setWatchActive(false);
    };
  }, [riderId, throttledIngest, motionState, activeOrder?.id]);

  const flush = useCallback(async () => {
    const rows = await peekUnsortedTelemetry(500);
    if (!rows.length || riderId == null) return { stored: 0 };
    try {
      const { data, ok } = await fetchJson<{ ok: boolean; stored: number }>('/telemetry/batch', {
        method: 'POST',
        body: JSON.stringify({
          rider_order_id: activeOrder?.id ?? null,
          points: rows.map((r) => ({
            lat: r.lat, lng: r.lng,
            heading: r.heading ?? null, speed_kmh: r.speed_kmh ?? null, accuracy_m: r.accuracy_m ?? null,
            motion: r.motion ?? 'IDLE',
            offline: 0, t_ms: r.t_ms,
          })),
        }),
      });
      if (ok && data?.ok) {
        await markSynced(rows.map((r) => r.id!).filter((id): id is number => id != null));
        void countUnsortedTelemetry().then(setQueueDepth);
        return { stored: data.stored };
      }
      return { stored: 0 };
    } catch { return { stored: 0 }; }
  }, [activeOrder?.id, riderId]);

  const requestSingleFix = useCallback(() => {
    if (!('geolocation' in navigator)) return;
    try {
      navigator.geolocation.getCurrentPosition(
        (p) => throttledIngest(p, true),
        () => lastPointRef.current && throttledIngest(lastPointRef.current, true),
        { enableHighAccuracy: true, maximumAge: 0, timeout: 15_000 },
      );
    } catch { /* noop */ }
  }, [throttledIngest]);

  return {
    lastPoint,
    motionState,
    queueDepth,
    lastSentAtMs,
    insideFence,
    distanceToPickupMeters: distPickup,
    distanceToDropoffMeters: distDropoff,
    watchActive,
    error,
    flush,
    requestSingleFix,
  };
}
