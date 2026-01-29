import { create } from 'zustand';

interface SyncState {
  isSyncing: boolean;
  lastSyncTime: string | null;
  pendingCount: number;
  syncError: string | null;

  // Actions
  setSyncing: (syncing: boolean) => void;
  setLastSyncTime: (time: string) => void;
  setPendingCount: (count: number) => void;
  setSyncError: (error: string | null) => void;
}

export const useSyncStore = create<SyncState>((set) => ({
  isSyncing: false,
  lastSyncTime: null,
  pendingCount: 0,
  syncError: null,

  setSyncing: (syncing) => set({ isSyncing: syncing }),
  setLastSyncTime: (time) => set({ lastSyncTime: time }),
  setPendingCount: (count) => set({ pendingCount: count }),
  setSyncError: (error) => set({ syncError: error }),
}));
