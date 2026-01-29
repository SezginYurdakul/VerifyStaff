import { useSyncStore } from "@/stores/syncStore";

interface SyncStatusBadgeProps {
    /** Whether this specific item was saved offline */
    isProvisional?: boolean;
    /** Override message for the badge */
    message?: string;
    /** Size variant */
    size?: "sm" | "md";
}

/**
 * Badge component to indicate sync/provisional status of records.
 * Shows "Provisional" when a record was saved offline and pending sync.
 */
export default function SyncStatusBadge({
    isProvisional = false,
    message,
    size = "md",
}: SyncStatusBadgeProps) {
    const { isSyncing, pendingCount } = useSyncStore();
    const isOnline = navigator.onLine;

    // If explicitly marked as provisional
    if (isProvisional) {
        const sizeClasses = size === "sm" ? "text-xs px-2 py-0.5" : "text-sm px-3 py-1";
        return (
            <span
                className={`inline-flex items-center gap-1.5 rounded-full bg-amber-100 text-amber-800 font-medium ${sizeClasses}`}
            >
                <span className="w-2 h-2 bg-amber-500 rounded-full animate-pulse" />
                {message || "Provisional"}
            </span>
        );
    }

    // Global sync status
    if (isSyncing) {
        const sizeClasses = size === "sm" ? "text-xs px-2 py-0.5" : "text-sm px-3 py-1";
        return (
            <span
                className={`inline-flex items-center gap-1.5 rounded-full bg-blue-100 text-blue-800 font-medium ${sizeClasses}`}
            >
                <span className="w-2 h-2 border-2 border-blue-500 border-t-transparent rounded-full animate-spin" />
                Syncing...
            </span>
        );
    }

    if (!isOnline && pendingCount > 0) {
        const sizeClasses = size === "sm" ? "text-xs px-2 py-0.5" : "text-sm px-3 py-1";
        return (
            <span
                className={`inline-flex items-center gap-1.5 rounded-full bg-amber-100 text-amber-800 font-medium ${sizeClasses}`}
            >
                <span className="w-2 h-2 bg-amber-500 rounded-full" />
                {pendingCount} pending
            </span>
        );
    }

    return null;
}
