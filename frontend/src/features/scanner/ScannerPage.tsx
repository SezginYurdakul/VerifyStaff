import { useEffect, useRef, useState, useCallback } from 'react';
import { Html5Qrcode, Html5QrcodeScannerState } from 'html5-qrcode';
import { useMutation } from '@tanstack/react-query';
import { verifyTotpCode } from '@/api/totp';
import { useAuthStore } from '@/stores/authStore';
import { useSyncStore } from '@/stores/syncStore';
import { addPendingLog, getStaffById, getLogCount } from '@/lib/db';
import { syncPendingLogs } from '@/lib/syncService';
import { Card, Button, SyncStatusBadge } from '@/components/ui';
import type { Staff } from '@/types';
import type { AxiosError } from 'axios';
import type { ApiError } from '@/types';

const SCAN_COOLDOWN_MS = 5000; // Ignore same QR for 5 seconds

type ScanResult = {
  success: boolean;
  worker?: Staff;
  message: string;
  type?: 'in' | 'out';
  isProvisional?: boolean;
};

// Worker QR format: {wid: number, otp: string, i: "in"|"out", w: number}
type WorkerQRData = {
  wid: number;
  otp: string;
  i?: 'in' | 'out';
  w?: number;
};

function parseWorkerQR(decodedText: string): WorkerQRData | null {
  try {
    const data = JSON.parse(decodedText);
    if (data.wid && data.otp) {
      return {
        wid: data.wid,
        otp: data.otp,
        i: data.i,
        w: data.w,
      };
    }
  } catch {
    // Not JSON - invalid format
  }
  return null;
}

export default function ScannerPage() {
  const user = useAuthStore((state) => state.user);
  const { setPendingCount } = useSyncStore();
  const [isOnline, setIsOnline] = useState(navigator.onLine);

  const [isScanning, setIsScanning] = useState(false);
  const [lastResult, setLastResult] = useState<ScanResult | null>(null);
  const [cameraError, setCameraError] = useState<string | null>(null);

  // Track online/offline status reactively
  useEffect(() => {
    const handleOnline = () => setIsOnline(true);
    const handleOffline = () => setIsOnline(false);
    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);
    return () => {
      window.removeEventListener('online', handleOnline);
      window.removeEventListener('offline', handleOffline);
    };
  }, []);

  const scannerRef = useRef<Html5Qrcode | null>(null);
  const lastScanRef = useRef<{ text: string; time: number } | null>(null);
  const scannerContainerId = 'qr-reader';

  // Verify TOTP mutation (online mode)
  const verifyMutation = useMutation({
    mutationFn: verifyTotpCode,
  });

  // Handle successful scan
  const handleSuccessfulScan = async (worker: Staff, type: 'in' | 'out', provisional: boolean = false, totpCode: string | null = null) => {
    // Create attendance log
    const eventId = generateEventId(worker.id, user!.id, type);
    const now = new Date();

    await addPendingLog({
      event_id: eventId,
      worker_id: worker.id,
      rep_id: user!.id,
      kiosk_id: null,
      type,
      device_time: now.toISOString(),
      device_timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
      sync_time: null,
      sync_status: 'pending',
      sync_attempt: 0,
      offline_duration_seconds: 0,
      flagged: false,
      flag_reason: null,
      latitude: null,
      longitude: null,
      work_minutes: null,
      is_late: null,
      is_early_departure: null,
      is_overtime: null,
      scanned_totp: totpCode,
    });

    // Update pending count
    const counts = await getLogCount();
    setPendingCount(counts.pending);

    // Trigger sync after a short delay (catch 403 silently — mode/role mismatch)
    if (navigator.onLine) {
      setTimeout(() => syncPendingLogs().catch(() => {}), 1000);
    }

    setLastResult({
      success: true,
      worker,
      message: `${worker.name} checked ${type}`,
      type,
      isProvisional: provisional,
    });
    triggerFeedback(true);
  };

  // Generate unique event ID
  const generateEventId = (workerId: number, repId: number, type: string): string => {
    const timestamp = Date.now();
    const data = `${workerId}-${repId}-${timestamp}-${type}`;
    // Simple hash (in production, use crypto.subtle.digest)
    let hash = 0;
    for (let i = 0; i < data.length; i++) {
      const char = data.charCodeAt(i);
      hash = (hash << 5) - hash + char;
      hash = hash & hash;
    }
    return Math.abs(hash).toString(16).padStart(16, '0');
  };

  // Trigger haptic/visual feedback
  const triggerFeedback = (success: boolean) => {
    // Haptic feedback
    if (navigator.vibrate) {
      navigator.vibrate(success ? [100] : [100, 50, 100]);
    }

    // Clear result after 3 seconds
    setTimeout(() => setLastResult(null), 3000);
  };

  // Resume scanner after a delay
  const resumeScanner = useCallback(() => {
    setTimeout(async () => {
      try {
        if (scannerRef.current?.getState() === Html5QrcodeScannerState.PAUSED) {
          await scannerRef.current.resume();
        }
      } catch {
        // Scanner may have been stopped
      }
    }, 2000);
  }, []);

  // Handle QR code scan
  const onScanSuccess = useCallback(
    async (decodedText: string) => {
      // Deduplication: ignore same QR code within cooldown period
      const now = Date.now();
      if (
        lastScanRef.current &&
        lastScanRef.current.text === decodedText &&
        now - lastScanRef.current.time < SCAN_COOLDOWN_MS
      ) {
        return;
      }
      lastScanRef.current = { text: decodedText, time: now };

      // Pause scanning while processing
      if (scannerRef.current?.getState() === Html5QrcodeScannerState.SCANNING) {
        await scannerRef.current.pause(true);
      }

      try {
        // Parse QR data - expecting JSON format {wid, otp, i, w}
        const qrData = parseWorkerQR(decodedText);

        if (!qrData) {
          setLastResult({
            success: false,
            message: 'Invalid QR code format',
          });
          triggerFeedback(false);
          resumeScanner();
          return;
        }

        const { wid, otp, i: intent } = qrData;
        const scanType = intent || 'in'; // Default to check-in if not specified

        setLastResult({
          success: false,
          message: 'Processing...',
        });

        if (isOnline) {
          // Online: verify with server using worker_id from QR
          verifyMutation.mutate(
            { worker_id: wid, code: otp },
            {
              onSuccess: (data) => {
                if (data.valid && data.worker_id) {
                  // API returns worker_id and worker_name, construct Staff object
                  const verifiedWorker: Staff = {
                    id: data.worker_id,
                    name: data.worker_name || 'Unknown',
                    employee_id: null,
                    secret_token: '',
                    status: 'active',
                  };
                  handleSuccessfulScan(verifiedWorker, scanType, false, otp);
                } else {
                  setLastResult({
                    success: false,
                    message: data.message || 'Invalid code',
                  });
                  triggerFeedback(false);
                }
                resumeScanner();
              },
              onError: async (err) => {
                const axiosErr = err as AxiosError<ApiError>;
                const isNetworkError = !axiosErr.response;

                // Network error (offline/server down): save locally as provisional
                if (isNetworkError) {
                  const worker = await getStaffById(wid);
                  if (worker && worker.status === 'active') {
                    handleSuccessfulScan(worker, scanType, true, otp);
                  } else {
                    // No cached staff data — create minimal worker object
                    handleSuccessfulScan(
                      { id: wid, name: `Worker #${wid}`, employee_id: null, secret_token: '', status: 'active' },
                      scanType,
                      true,
                      otp,
                    );
                  }
                  resumeScanner();
                  return;
                }

                const status = axiosErr.response?.status;
                let message = 'Verification failed. Please try again.';
                if (status === 403) {
                  message = axiosErr.response?.data?.message || 'Access denied. Only representatives can scan worker QR codes.';
                }
                setLastResult({
                  success: false,
                  message,
                });
                triggerFeedback(false);
                resumeScanner();
              },
            }
          );
        } else {
          // Offline: validate locally using cached staff data
          const worker = await getStaffById(wid);
          if (worker && worker.status === 'active') {
            // Mark as provisional since it's offline and will sync later
            handleSuccessfulScan(worker, scanType, true, otp);
          } else {
            setLastResult({
              success: false,
              message: worker ? 'Worker is inactive' : 'Worker not found in cache',
            });
            triggerFeedback(false);
          }
          resumeScanner();
        }
      } catch (error) {
        console.error('Scan processing error:', error);
        setLastResult({
          success: false,
          message: 'Failed to process scan',
        });
        triggerFeedback(false);
        resumeScanner();
      }
    },
    [isOnline, verifyMutation, user, resumeScanner]
  );

  // Start scanner
  const startScanner = async () => {
    try {
      setCameraError(null);

      if (!scannerRef.current) {
        scannerRef.current = new Html5Qrcode(scannerContainerId);
      }

      await scannerRef.current.start(
        { facingMode: 'environment' },
        {
          fps: 10,
          qrbox: { width: 250, height: 250 },
        },
        onScanSuccess,
        () => {} // Ignore scan failures
      );

      setIsScanning(true);
    } catch (error) {
      console.error('Camera error:', error);
      setCameraError(
        'Failed to access camera. Please check permissions and try again.'
      );
    }
  };

  // Stop scanner
  const stopScanner = async () => {
    const state = scannerRef.current?.getState();
    if (state === Html5QrcodeScannerState.PAUSED) {
      scannerRef.current!.resume();
    }
    if (state === Html5QrcodeScannerState.PAUSED || state === Html5QrcodeScannerState.SCANNING) {
      await scannerRef.current!.stop();
    }
    setIsScanning(false);
  };

  // Cleanup on unmount
  useEffect(() => {
    return () => {
      const state = scannerRef.current?.getState();
      if (state === Html5QrcodeScannerState.PAUSED) {
        scannerRef.current!.resume();
        scannerRef.current!.stop();
      } else if (state === Html5QrcodeScannerState.SCANNING) {
        scannerRef.current!.stop();
      }
    };
  }, []);

  return (
    <div className="space-y-6">
      <Card>
        <h2 className="text-xl font-semibold text-gray-900 mb-2">
          Scan Worker QR Code
        </h2>
        <p className="text-gray-600 mb-4">
          Point your camera at a worker's QR code to record attendance
        </p>

        {/* Scanner container */}
        <div
          id={scannerContainerId}
          className={`w-full aspect-square max-w-md mx-auto rounded-lg overflow-hidden ${
            !isScanning ? 'bg-gray-100' : ''
          }`}
        />

        {/* Camera error */}
        {cameraError && (
          <div className="mt-4 p-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm">
            {cameraError}
          </div>
        )}

        {/* Controls */}
        <div className="mt-4 flex justify-center gap-4">
          {!isScanning ? (
            <Button onClick={startScanner} variant="primary" size="lg">
              Start Camera
            </Button>
          ) : (
            <Button onClick={stopScanner} variant="danger" size="lg">
              Stop Camera
            </Button>
          )}
        </div>
      </Card>

      {/* Scan Result */}
      {lastResult && (
        <Card
          className={`${
            lastResult.success
              ? 'border-2 border-green-500 bg-green-50'
              : 'border-2 border-red-500 bg-red-50'
          }`}
        >
          <div className="flex items-center gap-4">
            <div
              className={`text-4xl ${
                lastResult.success ? 'text-green-500' : 'text-red-500'
              }`}
            >
              {lastResult.success ? '✓' : '✗'}
            </div>
            <div className="flex-1">
              <div className="flex items-center gap-2">
                <span
                  className={`font-semibold ${
                    lastResult.success ? 'text-green-800' : 'text-red-800'
                  }`}
                >
                  {lastResult.message}
                </span>
                {lastResult.isProvisional && (
                  <SyncStatusBadge isProvisional size="sm" />
                )}
              </div>
              {lastResult.worker && (
                <div className="text-sm text-gray-600">
                  Worker ID: {lastResult.worker.id}
                </div>
              )}
              {lastResult.isProvisional && (
                <div className="text-xs text-amber-700 mt-1">
                  Will sync when online
                </div>
              )}
            </div>
          </div>
        </Card>
      )}

      {/* Offline indicator */}
      {!isOnline && (
        <Card className="bg-yellow-50 border-2 border-yellow-300">
          <div className="flex items-center gap-3">
            <span className="text-2xl">📴</span>
            <div>
              <div className="font-medium text-yellow-800">Offline Mode</div>
              <div className="text-sm text-yellow-700">
                Scans will be saved locally and synced when online
              </div>
            </div>
          </div>
        </Card>
      )}
    </div>
  );
}
