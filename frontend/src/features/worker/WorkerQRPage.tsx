import { useEffect, useState, useRef } from "react";
import { useQuery } from "@tanstack/react-query";
import { QRCodeSVG } from "qrcode.react";
import { useNavigate } from "react-router-dom";
import { generateTotpCode } from "@/api/totp";
import { useAuthStore } from "@/stores/authStore";
import { Card, Button } from "@/components/ui";
import type { AxiosError } from "axios";
import type { ApiError } from "@/types";

const DEFAULT_REFRESH_SECONDS = 30;

type IntentType = "in" | "out";

export default function WorkerQRPage() {
    const navigate = useNavigate();
    const user = useAuthStore((state) => state.user);
    const [remainingSeconds, setRemainingSeconds] = useState<number | null>(
        null,
    );
    const [intent, setIntent] = useState<IntentType>("in");

    // Use ReturnType for cross-environment compatibility
    const intervalRef = useRef<ReturnType<typeof setInterval> | null>(null);
    const refreshSecondsRef = useRef(DEFAULT_REFRESH_SECONDS);

    const isWorker = user?.role === "worker";

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
        enabled: isWorker,
    });

    // Sync state and refs when API returns fresh TOTP data
    useEffect(() => {
        if (totpData) {
            if (totpData.refresh_seconds !== undefined) {
                refreshSecondsRef.current = totpData.refresh_seconds;
            }
            if (totpData.remaining_seconds !== undefined) {
                setRemainingSeconds(totpData.remaining_seconds);
            }
        }
    }, [totpData]);

    // Main countdown logic
    useEffect(() => {
        // Only run the timer if the user is a worker and we have a start value
        if (!isWorker || remainingSeconds === null) return;

        // Trigger manual refetch if the timer hits zero
        if (remainingSeconds <= 0) {
            refetch();
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
    }, [isWorker, remainingSeconds, refetch]);

    // UI Calculations
    const displaySeconds = remainingSeconds ?? refreshSecondsRef.current;
    const progress = (displaySeconds / refreshSecondsRef.current) * 100;

    const getUrgencyColor = () => {
        const refreshSecs = refreshSecondsRef.current;
        if (displaySeconds > refreshSecs * 0.5) return "text-green-600";
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

    // Render Error State
    if (error) {
        const axiosError = error as AxiosError<ApiError>;
        const errorMessage =
            axiosError.response?.data?.message || "Failed to generate QR code";
        return (
            <Card className="text-center">
                <div className="text-red-600 mb-4">
                    <span className="text-4xl">⚠️</span>
                    <p className="mt-2">{errorMessage}</p>
                </div>
                <Button onClick={() => refetch()}>Try Again</Button>
            </Card>
        );
    }

    return (
        <div className="space-y-6">
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
                    {isLoading ? (
                        <div className="w-64 h-64 bg-gray-100 rounded-lg flex items-center justify-center">
                            <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600" />
                        </div>
                    ) : (
                        <div className={`p-4 bg-white rounded-lg shadow-inner border-4 ${
                            intent === "in" ? "border-green-500" : "border-red-500"
                        }`}>
                            <QRCodeSVG
                                value={JSON.stringify({
                                    wid: user?.id,
                                    otp: totpData?.code,
                                    i: intent,
                                    w: Math.floor(Date.now() / 1000 / refreshSecondsRef.current),
                                })}
                                size={240}
                                level="H"
                            />
                        </div>
                    )}
                    <div className={`absolute -top-2 -right-2 flex items-center gap-1 text-white px-2 py-1 rounded-full text-xs font-medium ${
                        intent === "in" ? "bg-green-500" : "bg-red-500"
                    }`}>
                        <span className="w-2 h-2 bg-white rounded-full animate-pulse" />
                        {intent === "in" ? "CHECK IN" : "CHECK OUT"}
                    </div>
                </div>

                <div className="mt-6">
                    <div className="text-sm text-gray-600 mb-2">
                        Code expires in
                    </div>
                    <div className={`text-4xl font-bold ${getUrgencyColor()}`}>
                        {displaySeconds}s
                    </div>

                    <div className="mt-3 h-2 bg-gray-200 rounded-full overflow-hidden max-w-xs mx-auto">
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

                {totpData?.code && (
                    <div className="mt-6 p-3 bg-gray-50 rounded-lg">
                        <div className="text-sm text-gray-600">Manual Code</div>
                        <div className="text-2xl font-mono font-bold tracking-widest text-gray-900">
                            {totpData.code}
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
                    <li>Ensure the LIVE indicator is active</li>
                </ol>
            </Card>
        </div>
    );
}
