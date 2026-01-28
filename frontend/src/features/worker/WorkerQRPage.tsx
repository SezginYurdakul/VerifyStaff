import { useEffect, useState, useRef } from "react";
import { useQuery } from "@tanstack/react-query";
import { QRCodeSVG } from "qrcode.react";
import { useNavigate } from "react-router-dom";
import { generateTotpCode } from "@/api/totp";
import { useAuthStore } from "@/stores/authStore";
import { Card, Button } from "@/components/ui";

const DEFAULT_REFRESH_SECONDS = 30;

type IntentType = "in" | "out";

interface CachedTotpData {
    code: string;
    refresh_seconds: number;
    cached_at: number;
}

function getCachedTotpData(): CachedTotpData | null {
    try {
        const raw = localStorage.getItem("worker-totp-cache");
        if (!raw) return null;
        return JSON.parse(raw);
    } catch {
        return null;
    }
}

function setCachedTotpData(data: CachedTotpData) {
    try {
        localStorage.setItem("worker-totp-cache", JSON.stringify(data));
    } catch {
        // localStorage full or unavailable
    }
}

export default function WorkerQRPage() {
    const navigate = useNavigate();
    const user = useAuthStore((state) => state.user);
    const [remainingSeconds, setRemainingSeconds] = useState<number | null>(
        null,
    );
    const [intent, setIntent] = useState<IntentType>("in");
    const [isOffline, setIsOffline] = useState(!navigator.onLine);

    // Use ReturnType for cross-environment compatibility
    const intervalRef = useRef<ReturnType<typeof setInterval> | null>(null);
    const refreshSecondsRef = useRef(DEFAULT_REFRESH_SECONDS);

    const isWorker = user?.role === "worker";

    // Track online/offline status
    useEffect(() => {
        const handleOnline = () => setIsOffline(false);
        const handleOffline = () => setIsOffline(true);
        window.addEventListener('online', handleOnline);
        window.addEventListener('offline', handleOffline);
        return () => {
            window.removeEventListener('online', handleOnline);
            window.removeEventListener('offline', handleOffline);
        };
    }, []);

    const {
        data: totpData,
        isLoading,
        error,
        refetch,
    } = useQuery({
        queryKey: ["totp-code"],
        queryFn: generateTotpCode,
        refetchInterval: () => refreshSecondsRef.current * 1000,
        staleTime: () => (refreshSecondsRef.current - 3) * 1000,
        enabled: isWorker && !isOffline,
        retry: isOffline ? false : 2,
    });

    // Cache TOTP data when received from API
    useEffect(() => {
        if (totpData) {
            setCachedTotpData({
                code: totpData.code,
                refresh_seconds: totpData.refresh_seconds ?? DEFAULT_REFRESH_SECONDS,
                cached_at: Date.now(),
            });
        }
    }, [totpData]);

    // Get cached data for offline fallback
    const cachedData = getCachedTotpData();

    // Determine what to display: live data, or cached fallback (offline OR API error)
    const useCachedFallback = !totpData && cachedData && (isOffline || !!error);
    const displayData = totpData || (useCachedFallback ? {
        code: cachedData.code,
        refresh_seconds: cachedData.refresh_seconds,
        remaining_seconds: cachedData.refresh_seconds,
    } : null);
    const isUnavailable = isOffline || (!totpData && !!error);

    // Sync state and refs when data is available
    useEffect(() => {
        if (displayData) {
            const rSeconds = displayData.refresh_seconds ?? DEFAULT_REFRESH_SECONDS;
            refreshSecondsRef.current = rSeconds;

            if (displayData.remaining_seconds !== undefined) {
                setRemainingSeconds(displayData.remaining_seconds);
            }
        }
    }, [totpData, useCachedFallback]);

    // Main countdown logic
    useEffect(() => {
        // Only run the timer if the user is a worker and we have a start value
        if (!isWorker || remainingSeconds === null) return;

        // Trigger manual refetch if the timer hits zero
        if (remainingSeconds <= 0) {
            if (!isUnavailable) {
                refetch();
            } else {
                // Unavailable (offline or backend down): restart countdown
                setRemainingSeconds(refreshSecondsRef.current);
            }
            return;
        }

        // Set up interval to decrement state every second.
        // We recreate it on every 'remainingSeconds' change to ensure accuracy.
        intervalRef.current = setInterval(() => {
            setRemainingSeconds((prev) =>
                prev !== null && prev > 0 ? prev - 1 : 0,
            );
        }, 1000);

        // Cleanup: Clear the previous interval to prevent memory leaks and multiple timers
        return () => {
            if (intervalRef.current) clearInterval(intervalRef.current);
        };
    }, [isWorker, remainingSeconds, refetch, isUnavailable]);

    // UI Calculations
    const displaySeconds = remainingSeconds ?? refreshSecondsRef.current;
    const progress = (displaySeconds / refreshSecondsRef.current) * 100;

    const getUrgencyColor = () => {
        const refreshSecs = refreshSecondsRef.current;
        if (displaySeconds > refreshSecs * 0.5)
            return isUnavailable ? "text-yellow-600" : "text-green-600";
        if (displaySeconds > refreshSecs * 0.2) return "text-yellow-600";
        return "text-red-600";
    };

    // Render Access Denied
    if (!isWorker) {
        return (
            <Card className="text-center">
                <div className="text-yellow-600 mb-4">
                    <span className="text-4xl">🚫</span>
                    <p className="mt-2 font-medium">Access Denied</p>
                </div>
                <Button onClick={() => navigate("/")}>Go to Home</Button>
            </Card>
        );
    }

    // Show error only if no cached fallback available
    if (error && !displayData) {
        return (
            <Card className="text-center">
                <div className="text-yellow-600 mb-4">
                    <span className="text-6xl">📴</span>
                </div>
                <h2 className="text-2xl font-bold text-gray-900 mb-2">Offline</h2>
                <p className="text-gray-600">
                    No cached QR data available. Please connect to the internet to generate a QR code.
                </p>
                <Button onClick={() => refetch()} className="mt-4">Try Again</Button>
            </Card>
        );
    }

    return (
        <div className="space-y-6">
            {/* Unavailable banner */}
            {isUnavailable && displayData && (
                <div className="px-4 py-2 bg-yellow-500 text-yellow-900 rounded-lg text-sm font-bold flex items-center gap-2 justify-center">
                    <span>📴</span> {isOffline ? 'Offline' : 'Server unavailable'} - Using cached code
                </div>
            )}

            {/* Intent Selection */}
            <Card>
                <div className="flex gap-2">
                    <button
                        onClick={() => setIntent("in")}
                        className={`flex-1 py-3 px-4 rounded-lg font-medium transition-all ${
                            intent === "in"
                                ? "bg-green-600 text-white shadow-md"
                                : "bg-gray-100 text-gray-600 hover:bg-gray-200"
                        }`}
                    >
                        <span className="text-lg mr-2">→</span>
                        Check In
                    </button>
                    <button
                        onClick={() => setIntent("out")}
                        className={`flex-1 py-3 px-4 rounded-lg font-medium transition-all ${
                            intent === "out"
                                ? "bg-red-600 text-white shadow-md"
                                : "bg-gray-100 text-gray-600 hover:bg-gray-200"
                        }`}
                    >
                        <span className="text-lg mr-2">←</span>
                        Check Out
                    </button>
                </div>
            </Card>

            <Card className="text-center">
                <h2 className="text-xl font-semibold text-gray-900 mb-2">
                    {intent === "in" ? "Check-In QR Code" : "Check-Out QR Code"}
                </h2>
                <p className="text-gray-600 mb-6">
                    Show this code to your representative
                </p>

                <div className="relative inline-block">
                    {isLoading && !displayData ? (
                        <div className="w-64 h-64 bg-gray-100 rounded-lg flex items-center justify-center">
                            <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600" />
                        </div>
                    ) : (
                        <div className={`p-4 bg-white rounded-lg shadow-inner border-4 ${
                            isUnavailable
                                ? "border-yellow-500"
                                : intent === "in" ? "border-green-500" : "border-red-500"
                        }`}>
                            <QRCodeSVG
                                value={JSON.stringify({
                                    wid: user?.id,
                                    otp: displayData?.code,
                                    i: intent,
                                    w: Math.floor(Date.now() / 1000 / refreshSecondsRef.current),
                                })}
                                size={240}
                                level="H"
                            />
                        </div>
                    )}
                    <div className={`absolute -top-2 -right-2 flex items-center gap-1 text-white px-2 py-1 rounded-full text-xs font-medium ${
                        isUnavailable
                            ? "bg-yellow-500"
                            : intent === "in" ? "bg-green-500" : "bg-red-500"
                    }`}>
                        <span className={`w-2 h-2 bg-white rounded-full ${isUnavailable ? '' : 'animate-pulse'}`} />
                        {isUnavailable ? 'OFFLINE' : intent === "in" ? "CHECK IN" : "CHECK OUT"}
                    </div>
                </div>

                <div className="mt-6">
                    <div className="text-sm text-gray-600 mb-2">
                        {isUnavailable ? 'QR window refreshes in' : 'Code expires in'}
                    </div>
                    <div className={`text-4xl font-bold ${getUrgencyColor()}`}>
                        {displaySeconds}s
                    </div>

                    <div className="mt-3 h-2 bg-gray-200 rounded-full overflow-hidden max-w-xs mx-auto">
                        <div
                            className={`h-full transition-all duration-1000 ${
                                isUnavailable
                                    ? "bg-yellow-500"
                                    : displaySeconds > refreshSecondsRef.current * 0.5
                                        ? "bg-green-500"
                                        : displaySeconds >
                                            refreshSecondsRef.current * 0.2
                                          ? "bg-yellow-500"
                                          : "bg-red-500"
                            }`}
                            style={{ width: `${progress}%` }}
                        />
                    </div>
                </div>

                {displayData?.code && (
                    <div className="mt-6 p-3 bg-gray-50 rounded-lg">
                        <div className="text-sm text-gray-600">Manual Code</div>
                        <div className="text-2xl font-mono font-bold tracking-widest text-gray-900">
                            {displayData.code}
                        </div>
                    </div>
                )}
            </Card>

            <Card>
                <h3 className="font-medium text-gray-900 mb-3">Instructions</h3>
                <ol className="list-decimal list-inside space-y-2 text-sm text-gray-600">
                    <li>
                        Code refreshes every {refreshSecondsRef.current} seconds
                    </li>
                    <li>Ensure the {isUnavailable ? 'OFFLINE indicator is shown — cached code is being used' : 'LIVE indicator is active'}</li>
                </ol>
            </Card>
        </div>
    );
}
