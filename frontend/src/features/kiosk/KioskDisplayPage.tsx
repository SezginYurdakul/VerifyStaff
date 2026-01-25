import { useEffect, useState, useRef } from "react";
import { useParams } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { QRCodeSVG } from "qrcode.react";
import { getKioskCode } from "@/api/kiosk";
import { Card} from "@/components/ui";

const DEFAULT_REFRESH_SECONDS = 30;

export default function KioskDisplayPage() {
    const { kioskCode } = useParams<{ kioskCode: string }>();
    const [remainingSeconds, setRemainingSeconds] = useState<number | null>(
        null,
    );
    const [currentTime, setCurrentTime] = useState(new Date());

    // Use ReturnType to avoid 'NodeJS.Timeout' namespace issues in different environments
    const intervalRef = useRef<ReturnType<typeof setInterval> | null>(null);
    const refreshSecondsRef = useRef(DEFAULT_REFRESH_SECONDS);

    const {
        data: kioskData,
        isLoading,
        error,
        refetch,
    } = useQuery({
        queryKey: ["kiosk-code", kioskCode],
        queryFn: () => getKioskCode(kioskCode!),
        // Fetch interval is dynamically set based on the current configuration
        refetchInterval: () => refreshSecondsRef.current * 1000,
        staleTime: (data) => {
            const seconds =
                data?.state?.data?.refresh_seconds || DEFAULT_REFRESH_SECONDS;
            return (seconds - 5) * 1000;
        },
        enabled: !!kioskCode,
    });

    // Sync internal state and refs when API data is received
    useEffect(() => {
        if (kioskData) {
            const rSeconds =
                kioskData.refresh_seconds ?? DEFAULT_REFRESH_SECONDS;
            refreshSecondsRef.current = rSeconds;

            // Initialize countdown with remaining_seconds from server or the default interval
            setRemainingSeconds(kioskData.remaining_seconds ?? rSeconds);
        }
    }, [kioskData]);

    // Main countdown timer logic
    useEffect(() => {
        // Prevent timer from running if no code is present or data hasn't loaded
        if (!kioskCode || remainingSeconds === null) return;

        // Trigger manual refetch when counter hits zero
        if (remainingSeconds <= 0) {
            refetch();
            return;
        }

        // Set up a 1-second interval to decrement the state
        intervalRef.current = setInterval(() => {
            setRemainingSeconds((prev) =>
                prev !== null && prev > 0 ? prev - 1 : 0,
            );
        }, 1000);

        // Cleanup: Clear the interval before re-running the effect or unmounting
        return () => {
            if (intervalRef.current) clearInterval(intervalRef.current);
        };
    }, [kioskCode, remainingSeconds, refetch]);

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

    // Render error state if API fails
    if (!kioskCode)
        return (
            <div className="p-10 text-white">Error: Kiosk code missing.</div>
        );
    if (error)
        return (
            <div className="p-10 text-white">
                Error: Failed to fetch kiosk data.
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

            <Card className="text-center p-8 max-w-lg w-full">
                {isLoading ? (
                    <div className="w-80 h-80 flex items-center justify-center">
                        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600" />
                    </div>
                ) : (
                    <div className="relative inline-block">
                        <div className="p-6 bg-white rounded-xl shadow-inner border-4 border-blue-500">
                            <QRCodeSVG
                                value={JSON.stringify({
                                    t: "k",
                                    k: kioskCode,
                                    otp: kioskData?.code,
                                    w: Math.floor(Date.now() / 1000 / refreshSecondsRef.current),
                                })}
                                size={280}
                                level="H"
                            />
                        </div>
                        {/* Pulsing live status indicator */}
                        <div className="absolute -top-3 -right-3 flex items-center gap-2 bg-green-500 text-white px-3 py-1.5 rounded-full text-sm font-bold shadow-lg">
                            <span className="w-3 h-3 bg-white rounded-full animate-pulse" />
                            LIVE
                        </div>
                    </div>
                )}

                {/* Visual Countdown Progress */}
                <div className="mt-8">
                    <div className="text-gray-600 mb-2">Code refreshes in</div>
                    <div
                        className={`text-5xl font-bold ${
                            displaySeconds > refreshSecondsRef.current * 0.5
                                ? "text-green-600"
                                : displaySeconds >
                                    refreshSecondsRef.current * 0.2
                                  ? "text-yellow-600"
                                  : "text-red-600"
                        }`}
                    >
                        {displaySeconds}s
                    </div>

                    <div className="mt-4 h-3 bg-gray-200 rounded-full overflow-hidden max-w-xs mx-auto">
                        <div
                            className={`h-full transition-all duration-1000 ${
                                displaySeconds > refreshSecondsRef.current * 0.5
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
