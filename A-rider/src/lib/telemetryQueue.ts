import { openDB, type IDBPDatabase } from 'idb';

export interface TelemetryPointRow {
  id?: number;
  riderId: number;
  riderOrderId?: number | null;
  lat: number;
  lng: number;
  heading?: number | null;
  speed_kmh?: number | null;
  accuracy_m?: number | null;
  motion?: 'MOVING' | 'IDLE' | 'STOPPED';
  t_ms: number;
  synced?: 0 | 1;
}

interface TelemetrySchema {
  telemetry: TelemetryPointRow;
  meta: { key: string; value: unknown };
}

let dbPromise: Promise<IDBPDatabase<TelemetrySchema>> | null = null;

function getDB(): Promise<IDBPDatabase<TelemetrySchema>> {
  if (dbPromise) return dbPromise;
  dbPromise = openDB<TelemetrySchema>('motobook-rider-telemetry', 1, {
    upgrade(db) {
      if (!db.objectStoreNames.contains('telemetry')) {
        const store = db.createObjectStore('telemetry', { keyPath: 'id', autoIncrement: true });
        store.createIndex('by_synced_time', ['synced', 't_ms']);
        store.createIndex('by_rider_time', ['riderId', 't_ms']);
      }
      if (!db.objectStoreNames.contains('meta')) {
        db.createObjectStore('meta', { keyPath: 'key' });
      }
    },
  });
  return dbPromise;
}

export async function enqueueTelemetry(rows: Omit<TelemetryPointRow, 'id' | 'synced'>[]): Promise<number> {
  if (!rows.length) return 0;
  const db = await getDB();
  const tx = db.transaction('telemetry', 'readwrite');
  let count = 0;
  for (const row of rows) {
    tx.store.add({ ...row, synced: 0 as const });
    count++;
  }
  await tx.done;
  return count;
}

export async function peekUnsortedTelemetry(limit = 200): Promise<TelemetryPointRow[]> {
  const db = await getDB();
  const idx = db.transaction('telemetry', 'readonly').store.index('by_synced_time');
  const range = IDBKeyRange.bound([0, 0], [0, Date.now() + 86_400_000], false, false);
  return (await idx.getAll(range, limit)).map((r) => ({ ...r }));
}

export async function markSynced(ids: number[]): Promise<void> {
  if (!ids.length) return;
  const db = await getDB();
  const tx = db.transaction('telemetry', 'readwrite');
  await Promise.all(ids.map(async (id) => {
    const existing = await tx.store.get(id);
    if (existing) {
      existing.synced = 1;
      await tx.store.put(existing);
    }
  }));
  await tx.done;
}

export async function countUnsortedTelemetry(): Promise<number> {
  const db = await getDB();
  const idx = db.transaction('telemetry', 'readonly').store.index('by_synced_time');
  const range = IDBKeyRange.bound([0, 0], [0, Date.now() + 86_400_000], false, false);
  return idx.count(range);
}
