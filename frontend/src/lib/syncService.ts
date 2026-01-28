import { syncLogs } from '@/api/sync';
import { syncOfflineKioskLogs } from '@/api/kiosk';
import { getPendingLogs, updateLogSyncStatus, deleteSyncedLogs, getLogCount } from '@/lib/db';
import { useSyncStore } from '@/stores/syncStore';
import { useAuthStore } from '@/stores/authStore';
import type { AttendanceLog, SyncLogsRequest } from '@/types';
import type { AxiosError } from 'axios';

let syncInProgress = false;

/**
 * Check if an error is a 403 Forbidden (role/mode mismatch).
 * These are expected when e.g. a worker tries to sync rep logs or mode changed.
 */
function isForbiddenError(error: unknown): boolean {
  return (error as AxiosError)?.response?.status === 403;
}

/**
 * Attempt to sync all pending logs to the server
 */
export async function syncPendingLogs(): Promise<{ synced: number; failed: number }> {
  // Don't sync if offline or already syncing
  if (!navigator.onLine || syncInProgress) {
    return { synced: 0, failed: 0 };
  }

  syncInProgress = true;
  const store = useSyncStore.getState();
  store.setSyncing(true);
  store.setSyncError(null);

  let synced = 0;
  let failed = 0;

  try {
    const pendingLogs = await getPendingLogs();

    if (pendingLogs.length === 0) {
      return { synced: 0, failed: 0 };
    }

    const user = useAuthStore.getState().user;
    const isRep = user?.role === 'representative' || user?.role === 'admin';
    const isWorker = user?.role === 'worker';

    // Partition logs: kiosk-sourced vs representative-sourced
    const kioskLogs = pendingLogs.filter(log => log.kiosk_id && !log.rep_id);
    const repLogs = pendingLogs.filter(log => !log.kiosk_id || log.rep_id);

    // Sync representative logs (only if current user is rep/admin)
    if (repLogs.length > 0 && isRep) {
      try {
        const result = await syncRepLogs(repLogs);
        synced += result.synced;
        failed += result.failed;
      } catch (error) {
        if (!isForbiddenError(error)) throw error;
        // 403: mode mismatch — keep logs pending
      }
    }

    // Sync kiosk logs (only if current user is worker)
    if (kioskLogs.length > 0 && isWorker) {
      try {
        const result = await syncKioskLogs(kioskLogs);
        synced += result.synced;
        failed += result.failed;
      } catch (error) {
        if (!isForbiddenError(error)) throw error;
        // 403: mode mismatch — keep logs pending
      }
    }

    // Clean up synced logs
    await deleteSyncedLogs();

    // Update store
    const counts = await getLogCount();
    store.setPendingCount(counts.pending);
    if (synced > 0) {
      store.setLastSyncTime(new Date().toISOString());
    }

  } catch (error) {
    console.error('Sync failed:', error);
    store.setSyncError(error instanceof Error ? error.message : 'Sync failed');
  } finally {
    syncInProgress = false;
    store.setSyncing(false);
  }

  return { synced, failed };
}

/**
 * Sync representative-sourced logs via POST /sync/logs
 */
async function syncRepLogs(
  logs: (AttendanceLog & { id: number })[]
): Promise<{ synced: number; failed: number }> {
  let synced = 0;
  let failed = 0;

  const logsToSync: SyncLogsRequest['logs'] = logs.map((log) => ({
    event_id: log.event_id,
    worker_id: log.worker_id,
    type: log.type,
    device_time: log.device_time,
    device_timezone: log.device_timezone,
    latitude: log.latitude,
    longitude: log.longitude,
    scanned_totp: log.scanned_totp,
  }));

  const response = await syncLogs({ logs: logsToSync });

  // Backend generates its own event_ids, so we can't match by event_id.
  // Mark logs based on whether their worker_id appears in errors.
  const errorWorkerIds = new Set(response.errors.map((e: { worker_id: number }) => e.worker_id));

  for (const log of logs) {
    if (errorWorkerIds.has(log.worker_id)) {
      await updateLogSyncStatus(log.id, 'failed');
      failed++;
    } else {
      await updateLogSyncStatus(log.id, 'synced');
      synced++;
    }
  }

  return { synced, failed };
}

/**
 * Sync kiosk-sourced offline logs via POST /attendance/sync-offline
 */
async function syncKioskLogs(
  logs: (AttendanceLog & { id: number })[]
): Promise<{ synced: number; failed: number }> {
  let synced = 0;
  let failed = 0;

  const response = await syncOfflineKioskLogs({
    logs: logs.map((log) => ({
      kiosk_code: log.kiosk_id!,
      device_time: log.device_time,
      device_timezone: log.device_timezone,
      event_id: log.event_id,
      scanned_totp: log.scanned_totp,
    })),
  });

  const errorEventIds = new Set(response.errors.map(e => e.event_id));

  for (const log of logs) {
    if (errorEventIds.has(log.event_id)) {
      await updateLogSyncStatus(log.id, 'failed');
      failed++;
    } else {
      await updateLogSyncStatus(log.id, 'synced');
      synced++;
    }
  }

  return { synced, failed };
}

/**
 * Get current pending count and update store
 */
export async function refreshPendingCount(): Promise<number> {
  const counts = await getLogCount();
  useSyncStore.getState().setPendingCount(counts.pending);
  return counts.pending;
}
