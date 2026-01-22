import Dexie, { type EntityTable } from 'dexie';
import type { Staff, AttendanceLog } from '@/types';

// Define the database
class VerifyStaffDB extends Dexie {
  staff!: EntityTable<Staff, 'id'>;
  pendingLogs!: EntityTable<AttendanceLog & { id: number }, 'id'>;

  constructor() {
    super('VerifyStaffDB');

    this.version(1).stores({
      // Staff table for offline validation
      staff: 'id, name, employee_id, secret_token, status',
      // Pending attendance logs waiting to be synced
      pendingLogs: '++id, event_id, worker_id, type, device_time, sync_status'
    });
  }
}

// Create database instance
export const db = new VerifyStaffDB();

// Helper functions
export async function saveStaffList(staffList: Staff[]): Promise<void> {
  await db.transaction('rw', db.staff, async () => {
    // Clear existing staff
    await db.staff.clear();
    // Add new staff
    await db.staff.bulkAdd(staffList);
  });
}

export async function getStaffById(id: number): Promise<Staff | undefined> {
  return db.staff.get(id);
}

export async function getStaffByToken(token: string): Promise<Staff | undefined> {
  return db.staff.where('secret_token').equals(token).first();
}

export async function getAllStaff(): Promise<Staff[]> {
  return db.staff.toArray();
}

export async function addPendingLog(log: Omit<AttendanceLog, 'id'>): Promise<number> {
  return db.pendingLogs.add(log as AttendanceLog & { id: number });
}

export async function getPendingLogs(): Promise<(AttendanceLog & { id: number })[]> {
  return db.pendingLogs.where('sync_status').equals('pending').toArray();
}

export async function updateLogSyncStatus(
  id: number,
  status: AttendanceLog['sync_status']
): Promise<void> {
  await db.pendingLogs.update(id, { sync_status: status });
}

export async function deleteSyncedLogs(): Promise<void> {
  await db.pendingLogs.where('sync_status').equals('synced').delete();
}

export async function getLogCount(): Promise<{
  pending: number;
  synced: number;
  failed: number;
}> {
  const pending = await db.pendingLogs.where('sync_status').equals('pending').count();
  const synced = await db.pendingLogs.where('sync_status').equals('synced').count();
  const failed = await db.pendingLogs.where('sync_status').equals('failed').count();

  return { pending, synced, failed };
}
