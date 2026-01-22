import { create } from 'zustand';

interface SyncState {
  isOnline: boolean;
  isSyncing: boolean;
  lastSyncTime: string | null;
  pendingCount: number;
  syncError: string | null;

  // Actions
  setOnline: (online: boolean) => void;
  setSyncing: (syncing: boolean) => void;
  setLastSyncTime: (time: string) => void;
  setPendingCount: (count: number) => void;
  setSyncError: (error: string | null) => void;
}

export const useSyncStore = create<SyncState>((set) => ({
  isOnline: navigator.onLine,
  isSyncing: false,
  lastSyncTime: null,
  pendingCount: 0,
  syncError: null,

  setOnline: (online) => set({ isOnline: online }),
  setSyncing: (syncing) => set({ isSyncing: syncing }),
  setLastSyncTime: (time) => set({ lastSyncTime: time }),
  setPendingCount: (count) => set({ pendingCount: count }),
  setSyncError: (error) => set({ syncError: error }),
}));

// Initialize online status listeners
if (typeof window !== 'undefined') {
  window.addEventListener('online', () => {
    useSyncStore.getState().setOnline(true);
  });

  window.addEventListener('offline', () => {
    useSyncStore.getState().setOnline(false);
  });
}
