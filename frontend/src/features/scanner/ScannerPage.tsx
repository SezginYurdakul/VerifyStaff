import { useEffect, useRef, useState, useCallback } from 'react';
import { Html5Qrcode, Html5QrcodeScannerState } from 'html5-qrcode';
import { useMutation } from '@tanstack/react-query';
import { verifyTotpCode } from '@/api/totp';
import { useAuthStore } from '@/stores/authStore';
import { useSyncStore } from '@/stores/syncStore';
import { addPendingLog, getStaffByToken, getLogCount } from '@/lib/db';
import { Card, Button, SyncStatusBadge } from '@/components/ui';
import type { Staff } from '@/types';

type ScanResult = {
  success: boolean;
  worker?: Staff;
  message: string;
  type?: 'in' | 'out';
  isProvisional?: boolean;
};

export default function ScannerPage() {
  const user = useAuthStore((state) => state.user);
  const { isOnline, setPendingCount } = useSyncStore();

  const [isScanning, setIsScanning] = useState(false);
  const [lastResult, setLastResult] = useState<ScanResult | null>(null);
  const [cameraError, setCameraError] = useState<string | null>(null);

  const scannerRef = useRef<Html5Qrcode | null>(null);
  const scannerContainerId = 'qr-reader';

  // Verify TOTP mutation (online mode)
  const verifyMutation = useMutation({
    mutationFn: verifyTotpCode,
    onSuccess: (data) => {
      if (data.valid && data.worker) {
        handleSuccessfulScan(data.worker, 'in'); // TODO: determine type
      } else {
        setLastResult({
          success: false,
          message: data.message || 'Invalid code',
        });
        triggerFeedback(false);
      }
    },
    onError: () => {
      setLastResult({
        success: false,
        message: 'Verification failed. Please try again.',
      });
      triggerFeedback(false);
    },
  });

  // Handle successful scan
  const handleSuccessfulScan = async (worker: Staff, type: 'in' | 'out', provisional: boolean = false) => {
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
    });

    // Update pending count
    const counts = await getLogCount();
    setPendingCount(counts.pending);

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

  // Handle QR code scan
  const onScanSuccess = useCallback(
    async (decodedText: string) => {
      // Pause scanning while processing
      if (scannerRef.current?.getState() === Html5QrcodeScannerState.SCANNING) {
        await scannerRef.current.pause();
      }

      try {
        // Try to parse as TOTP code (6 digits)
        const code = decodedText.trim();

        if (isOnline) {
          // Online: verify with server
          // We need worker_id, but QR only has code.
          // In real implementation, QR would contain JSON with worker_id and code
          // For now, try to find worker by checking all staff tokens
          setLastResult({
            success: false,
            message: 'Processing...',
          });

          // Try to verify - this is a simplified version
          // Real implementation would have worker_id in QR
          const worker = await getStaffByToken(code);
          if (worker) {
            verifyMutation.mutate({ worker_id: worker.id, code });
          } else {
            setLastResult({
              success: false,
              message: 'Worker not found',
            });
            triggerFeedback(false);
          }
        } else {
          // Offline: validate locally using cached staff data
          const worker = await getStaffByToken(code);
          if (worker && worker.status === 'active') {
            // Mark as provisional since it's offline and will sync later
            handleSuccessfulScan(worker, 'in', true); // TODO: toggle in/out
          } else {
            setLastResult({
              success: false,
              message: worker ? 'Worker is inactive' : 'Invalid code',
            });
            triggerFeedback(false);
          }
        }
      } catch (error) {
        console.error('Scan processing error:', error);
        setLastResult({
          success: false,
          message: 'Failed to process scan',
        });
        triggerFeedback(false);
      }

      // Resume scanning after delay
      setTimeout(async () => {
        if (scannerRef.current?.getState() === Html5QrcodeScannerState.PAUSED) {
          await scannerRef.current.resume();
        }
      }, 2000);
    },
    [isOnline, verifyMutation, user]
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
    if (scannerRef.current?.getState() === Html5QrcodeScannerState.SCANNING) {
      await scannerRef.current.stop();
    }
    setIsScanning(false);
  };

  // Cleanup on unmount
  useEffect(() => {
    return () => {
      if (scannerRef.current?.getState() === Html5QrcodeScannerState.SCANNING) {
        scannerRef.current.stop();
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
