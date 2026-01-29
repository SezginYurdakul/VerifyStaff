import { useEffect, useState, useRef } from "react";
import { useParams } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { QRCodeSVG } from "qrcode.react";
import { getKioskCode } from "@/api/kiosk";
import { Card } from "@/components/ui";
import type { AxiosError } from "axios";

const DEFAULT_REFRESH_SECONDS = 30;

interface CachedKioskData {
    totp_code: string;
    kiosk_code: string;
    kiosk_name: string;
    refresh_seconds: number;
    cached_at: number;
}

function getCachedKioskData(kioskCode: string): CachedKioskData | null {
    try {
        const raw = localStorage.getItem(`kiosk-cache-${kioskCode}`);
        if (!raw) return null;
        return JSON.parse(raw);
    } catch {
        return null;
    }
}

function setCachedKioskData(kioskCode: string, data: CachedKioskData) {
    try {
        localStorage.setItem(`kiosk-cache-${kioskCode}`, JSON.stringify(data));
    } catch {
        // localStorage full or unavailable
    }
}

export default function KioskDisplayPage() {
    const { kioskCode } = useParams<{ kioskCode: string }>();
    const [remainingSeconds, setRemainingSeconds] = useState<number | null>(
        null,
    );
    const [currentTime, setCurrentTime] = useState(new Date());
    const [isOffline, setIsOffline] = useState(!navigator.onLine);

    const intervalRef = useRef<ReturnType<typeof setInterval> | null>(null);
    const refreshSecondsRef = useRef(DEFAULT_REFRESH_SECONDS);

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
        data: kioskData,
        isLoading,
        error,
        refetch,
    } = useQuery({
        queryKey: ["kiosk-code", kioskCode],
        queryFn: () => getKioskCode(kioskCode!),
        refetchInterval: () => refreshSecondsRef.current * 1000,
        staleTime: (data) => {
            const seconds =
                data?.state?.data?.refresh_seconds || DEFAULT_REFRESH_SECONDS;
            return (seconds - 5) * 1000;
        },
        enabled: !!kioskCode && !isOffline,
        retry: isOffline ? false : 2,
    });

    // Cache kiosk data when received from API
    useEffect(() => {
        if (kioskData && kioskCode) {
            setCachedKioskData(kioskCode, {
                totp_code: kioskData.totp_code,
                kiosk_code: kioskData.kiosk_code,
                kiosk_name: kioskData.kiosk_name,
                refresh_seconds: kioskData.refresh_seconds,
                cached_at: Date.now(),
            });
        }
    }, [kioskData, kioskCode]);

    // Get cached data for offline fallback
    const cachedData = kioskCode ? getCachedKioskData(kioskCode) : null;

    // Detect if error is a 403 "not in kiosk mode" (mode changed to representative)
    const isModeChanged = !!error && (error as AxiosError)?.response?.status === 403;

    // Determine what to display: live data, or cached fallback (offline OR network error, but NOT 403)
    const isNetworkError = !!error && !isModeChanged;
    const useCachedFallback = !kioskData && cachedData && (isOffline || isNetworkError);
    const displayData = kioskData || (useCachedFallback ? {
        totp_code: cachedData.totp_code,
        kiosk_code: cachedData.kiosk_code,
        kiosk_name: cachedData.kiosk_name,
        refresh_seconds: cachedData.refresh_seconds,
        remaining_seconds: cachedData.refresh_seconds,
        expires_at: '',
    } : null);
    const isUnavailable = isOffline || (!kioskData && isNetworkError);

    // Sync internal state and refs when data is available
    useEffect(() => {
        if (displayData) {
            const rSeconds =
                displayData.refresh_seconds ?? DEFAULT_REFRESH_SECONDS;
            refreshSecondsRef.current = rSeconds;

            setRemainingSeconds(displayData.remaining_seconds ?? rSeconds);
        }
    }, [kioskData, useCachedFallback]); // Re-trigger on fresh API data or fallback activation

    // Main countdown timer logic
    useEffect(() => {
        if (!kioskCode || remainingSeconds === null) return;

        if (remainingSeconds <= 0) {
            if (!isUnavailable) {
                refetch();
            } else {
                // Unavailable (offline or backend down): restart countdown with cached code
                setRemainingSeconds(refreshSecondsRef.current);
            }
            return;
        }

        intervalRef.current = setInterval(() => {
            setRemainingSeconds((prev) =>
                prev !== null && prev > 0 ? prev - 1 : 0,
            );
        }, 1000);

        return () => {
            if (intervalRef.current) clearInterval(intervalRef.current);
        };
    }, [kioskCode, remainingSeconds, refetch, isUnavailable]);

    // Independent clock timer for the UI time display
    useEffect(() => {
        const clockInterval = setInterval(
            () => setCurrentTime(new Date()),
            1000,
        );
        return () => clearInterval(clockInterval);
    }, []);

    // UI Helper calculations
    const displaySeconds = remainingSeconds ?? refreshSecondsRef.current;
    const progress = (displaySeconds / refreshSecondsRef.current) * 100;

    if (!kioskCode)
        return (
            <div className="p-10 text-white">Error: Kiosk code missing.</div>
        );

    // Show mode-changed screen when system switched to representative mode
    if (isModeChanged)
        return (
            <div className="min-h-screen bg-gradient-to-br from-blue-900 to-gray-900 flex flex-col items-center justify-center p-4">
                <Card className="text-center p-8 max-w-lg w-full">
                    <div className="text-blue-600 mb-4">
                        <span className="text-6xl">🔄</span>
                    </div>
                    <h2 className="text-2xl font-bold text-gray-900 mb-2">Mode Changed</h2>
                    <p className="text-gray-600">
                        System has switched to representative mode. Kiosk QR display is no longer active.
                    </p>
                </Card>
            </div>
        );

    // Show error only if no cached fallback available
    if (error && !displayData)
        return (
            <div className="p-10 text-white">
                Error: Failed to fetch kiosk data.
            </div>
        );

    // No data available at all (first load while offline, no cache)
    if (!displayData && !isLoading)
        return (
            <div className="min-h-screen bg-gradient-to-br from-blue-900 to-gray-900 flex flex-col items-center justify-center p-4">
                <Card className="text-center p-8 max-w-lg w-full">
                    <div className="text-yellow-600 mb-4">
                        <span className="text-6xl">📴</span>
                    </div>
                    <h2 className="text-2xl font-bold text-gray-900 mb-2">Offline</h2>
                    <p className="text-gray-600">
                        No cached kiosk data available. Please connect to the internet to load the kiosk QR code.
                    </p>
                </Card>
            </div>
        );

    return (
        <div className="min-h-screen bg-gradient-to-br from-blue-900 to-gray-900 flex flex-col items-center justify-center p-4">
            {/* Header section */}
            <div className="text-center mb-8">
                <h1 className="text-4xl font-bold text-white mb-2">
                    VerifyStaff
                </h1>
                <p className="text-blue-200 text-xl">Scan to Check In / Out</p>
            </div>

            {/* Unavailable banner */}
            {isUnavailable && displayData && (
                <div className="mb-4 px-4 py-2 bg-yellow-500 text-yellow-900 rounded-full text-sm font-bold flex items-center gap-2">
                    <span>📴</span> {isOffline ? 'Offline' : 'Server unavailable'} - Using cached code
                </div>
            )}

            <Card className="text-center p-8 max-w-lg w-full">
                {isLoading && !displayData ? (
                    <div className="w-80 h-80 flex items-center justify-center">
                        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600" />
                    </div>
                ) : (
                    <div className="relative inline-block">
                        <div className={`p-6 bg-white rounded-xl shadow-inner border-4 ${isUnavailable ? 'border-yellow-500' : 'border-blue-500'}`}>
                            <QRCodeSVG
                                value={JSON.stringify({
                                    t: "k",
                                    k: kioskCode,
                                    otp: displayData?.totp_code,
                                    w: Math.floor(Date.now() / 1000 / refreshSecondsRef.current),
                                })}
                                size={280}
                                level="H"
                            />
                        </div>
                        {/* Status indicator */}
                        <div className={`absolute -top-3 -right-3 flex items-center gap-2 ${isUnavailable ? 'bg-yellow-500' : 'bg-green-500'} text-white px-3 py-1.5 rounded-full text-sm font-bold shadow-lg`}>
                            <span className={`w-3 h-3 bg-white rounded-full ${isUnavailable ? '' : 'animate-pulse'}`} />
                            {isUnavailable ? 'OFFLINE' : 'LIVE'}
                        </div>
                    </div>
                )}

                {/* Visual Countdown Progress */}
                <div className="mt-8">
                    <div className="text-gray-600 mb-2">
                        {isUnavailable ? 'QR window refreshes in' : 'Code refreshes in'}
                    </div>
                    <div
                        className={`text-5xl font-bold ${
                            displaySeconds > refreshSecondsRef.current * 0.5
                                ? isUnavailable ? "text-yellow-600" : "text-green-600"
                                : displaySeconds > refreshSecondsRef.current * 0.2
                                  ? "text-yellow-600"
                                  : "text-red-600"
                        }`}
                    >
                        {`${displaySeconds}s`}
                    </div>

                    <div className="mt-4 h-3 bg-gray-200 rounded-full overflow-hidden max-w-xs mx-auto">
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

                {/* Kiosk metadata display */}
                <div className="mt-6 pt-6 border-t border-gray-200">
                    <p className="text-gray-500 text-sm">Kiosk ID</p>
                    <p className="text-gray-800 font-mono font-bold text-lg">
                        {kioskCode}
                    </p>
                </div>
            </Card>

            {/* Real-time Digital Clock */}
            <div className="mt-8 text-center">
                <p className="text-white text-5xl font-bold font-mono">
                    {currentTime.toLocaleTimeString("en-US", { hour12: false })}
                </p>
            </div>
        </div>
    );
}
