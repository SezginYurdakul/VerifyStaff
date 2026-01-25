import { useState, useCallback, useEffect, useRef } from "react";
import { useNavigate } from "react-router-dom";
import { useMutation } from "@tanstack/react-query";
import { Html5QrcodeScanner, Html5QrcodeScanType } from "html5-qrcode";
import { kioskCheckIn } from "@/api/kiosk";
import { useAuthStore } from "@/stores/authStore";
import { Card, Button } from "@/components/ui";
import type { AxiosError } from "axios";
import type { ApiError } from "@/types";

// New compact format: {t:"k", k:"KIOSK-001", otp:"123456", w:123456}
// Legacy format: {type:"kiosk", code:"123456", kiosk:"KIOSK-001"}
type KioskQRData =
    | { t: "k"; k: string; otp: string; w: number }
    | { type: "kiosk"; code: string; kiosk: string };

function parseKioskQR(data: KioskQRData): { kioskCode: string; totpCode: string } | null {
    // New compact format
    if ("t" in data && data.t === "k" && data.k && data.otp) {
        return { kioskCode: data.k, totpCode: data.otp };
    }
    // Legacy format
    if ("type" in data && data.type === "kiosk" && data.kiosk && data.code) {
        return { kioskCode: data.kiosk, totpCode: data.code };
    }
    return null;
}

export default function WorkerKioskScanPage() {
    const navigate = useNavigate();
    const user = useAuthStore((state) => state.user);
    const scannerRef = useRef<Html5QrcodeScanner | null>(null);
    
    const [scanResult, setScanResult] = useState<{
        success: boolean;
        message: string;
        action?: "check_in" | "check_out";
        time?: string;
    } | null>(null);
    const [isScanning, setIsScanning] = useState(true);

    const isWorker = user?.role === "worker";

    const checkInMutation = useMutation({
        mutationFn: (data: { kiosk_code: string; totp_code: string }) =>
            kioskCheckIn(data.kiosk_code, data.totp_code),
        onSuccess: (data) => {
            setScanResult({
                success: true,
                message: data.action === "check_in" ? "Successfully checked in!" : "Successfully checked out!",
                action: data.action,
                time: data.time,
            });
            setIsScanning(false);
        },
        onError: (error: AxiosError<ApiError>) => {
            setScanResult({
                success: false,
                message: error.response?.data?.message || "Check-in failed. Please try again.",
            });
            setIsScanning(false);
        },
    });

    const handleScanSuccess = useCallback(
        (decodedText: string) => {
            try {
                const data: KioskQRData = JSON.parse(decodedText);

                // Parse QR data (supports both compact and legacy formats)
                const parsed = parseKioskQR(data);

                if (!parsed) {
                    setScanResult({
                        success: false,
                        message: "Invalid QR code. Please scan a valid kiosk QR code.",
                    });
                    setIsScanning(false);
                    return;
                }

                // Stop and clear the scanner instance immediately upon success
                if (scannerRef.current) {
                    scannerRef.current.clear().catch(console.error);
                }

                // Execute the check-in/out mutation
                checkInMutation.mutate({
                    kiosk_code: parsed.kioskCode,
                    totp_code: parsed.totpCode,
                });
            } catch (e) {
                setScanResult({
                    success: false,
                    message: "Could not read QR code format. Please try again.",
                });
                setIsScanning(false);
            }
        },
        [checkInMutation],
    );

    const handleScanError = useCallback((_errorMessage: string) => {
        // Standard scanning noise (e.g., No QR found in frame) is ignored
    }, []);

    useEffect(() => {
        // Only initialize scanner if user is authorized and scanning is active
        if (!isWorker || !isScanning) return;

        // Cleanup previous DOM leftovers to prevent duplicate scanner instances
        const qrReaderElem = document.getElementById("qr-reader");
        if (qrReaderElem) qrReaderElem.innerHTML = "";

        const scanner = new Html5QrcodeScanner(
            "qr-reader",
            {
                fps: 10,
                qrbox: { width: 250, height: 250 },
                supportedScanTypes: [Html5QrcodeScanType.SCAN_TYPE_CAMERA],
                rememberLastUsedCamera: true,
            },
            false
        );

        scannerRef.current = scanner;

        // Small delay ensures the DOM element is fully ready
        const timeoutId = setTimeout(() => {
            scanner.render(handleScanSuccess, handleScanError);
        }, 50);

        // Comprehensive cleanup on unmount or state change
        return () => {
            clearTimeout(timeoutId);
            if (scannerRef.current) {
                scannerRef.current.clear().catch((err) => {
                    // Log but don't crash if scanner was already closed
                    console.warn("Scanner cleanup warning:", err);
                });
            }
        };
    }, [isWorker, isScanning, handleScanSuccess, handleScanError]);

    const handleScanAgain = () => {
        setScanResult(null);
        setIsScanning(true);
    };

    if (!isWorker) {
        return (
            <Card className="text-center">
                <div className="text-yellow-600 mb-4">
                    <span className="text-4xl">🚫</span>
                    <p className="mt-2 font-medium">Access Denied</p>
                    <p className="text-sm text-gray-600 mt-1">
                        Only workers can use kiosk check-in.
                    </p>
                </div>
                <Button onClick={() => navigate("/")}>Go to Home</Button>
            </Card>
        );
    }

    if (scanResult) {
        return (
            <div className="space-y-6">
                <Card className="text-center">
                    <div className={scanResult.success ? "text-green-600" : "text-red-600"}>
                        <span className="text-6xl">{scanResult.success ? "✓" : "✗"}</span>
                    </div>
                    <h2 className="text-2xl font-bold text-gray-900 mt-4 mb-2">
                        {scanResult.success 
                            ? (scanResult.action === "check_in" ? "Checked In" : "Checked Out")
                            : "Check-in Failed"}
                    </h2>
                    <p className="text-gray-600">{scanResult.message}</p>
                    
                    {scanResult.success && scanResult.time && (
                        <p className="text-lg font-mono text-gray-800 mt-4">
                            {new Date(scanResult.time).toLocaleTimeString("en-US", {
                                hour: "2-digit", minute: "2-digit", second: "2-digit", hour12: false,
                            })}
                        </p>
                    )}

                    <div className="mt-6 flex gap-3 justify-center">
                        <Button onClick={handleScanAgain}>Scan Again</Button>
                        <Button variant="secondary" onClick={() => navigate("/")}>Go Home</Button>
                    </div>
                </Card>
            </div>
        );
    }

    return (
        <div className="space-y-6">
            <Card>
                <h2 className="text-xl font-semibold text-gray-900 mb-2 text-center">Kiosk Check-in</h2>
                <p className="text-gray-600 text-center mb-6">Scan the QR code displayed on the kiosk</p>

                <div className="relative">
                    {checkInMutation.isPending && (
                        <div className="absolute inset-0 bg-white/80 flex items-center justify-center z-10 rounded-lg">
                            <div className="text-center">
                                <div className="animate-spin rounded-full h-10 w-10 border-b-2 border-blue-600 mx-auto" />
                                <p className="mt-2 text-gray-600">Processing...</p>
                            </div>
                        </div>
                    )}
                    <div id="qr-reader" className="w-full overflow-hidden rounded-lg" />
                </div>
            </Card>

            <Card>
                <h3 className="font-medium text-gray-900 mb-3">Instructions</h3>
                <ol className="list-decimal list-inside space-y-2 text-sm text-gray-600">
                    <li>Point your camera at the kiosk QR code</li>
                    <li>Hold steady until recognized</li>
                    <li>Wait for check-in/out confirmation</li>
                </ol>
            </Card>

            <div className="text-center">
                <Button variant="secondary" onClick={() => navigate("/")}>Cancel</Button>
            </div>
        </div>
    );
}