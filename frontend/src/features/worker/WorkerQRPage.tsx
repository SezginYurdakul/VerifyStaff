import { useEffect, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { QRCodeSVG } from 'qrcode.react';
import { useNavigate } from 'react-router-dom';
import { generateTotpCode } from '@/api/totp';
import { useAuthStore } from '@/stores/authStore';
import { Card, Button } from '@/components/ui';
import type { AxiosError } from 'axios';
import type { ApiError } from '@/types';

export default function WorkerQRPage() {
  const navigate = useNavigate();
  const user = useAuthStore((state) => state.user);
  const [remainingSeconds, setRemainingSeconds] = useState(30);

  // Check if user is a worker
  const isWorker = user?.role === 'worker';

  const {
    data: totpData,
    isLoading,
    error,
    refetch,
  } = useQuery({
    queryKey: ['totp-code'],
    queryFn: generateTotpCode,
    refetchInterval: 30000, // Refetch every 30 seconds
    staleTime: 25000, // Consider stale after 25 seconds
    enabled: isWorker, // Only fetch if user is a worker
  });

  // Countdown timer
  useEffect(() => {
    if (totpData?.remaining_seconds) {
      setRemainingSeconds(totpData.remaining_seconds);
    }

    const interval = setInterval(() => {
      setRemainingSeconds((prev) => {
        if (prev <= 1) {
          refetch();
          return 30;
        }
        return prev - 1;
      });
    }, 1000);

    return () => clearInterval(interval);
  }, [totpData, refetch]);

  // Calculate progress percentage
  const progress = (remainingSeconds / 30) * 100;

  // Determine urgency color
  const getUrgencyColor = () => {
    if (remainingSeconds > 15) return 'text-green-600';
    if (remainingSeconds > 5) return 'text-yellow-600';
    return 'text-red-600';
  };

  // Show access denied for non-workers
  if (!isWorker) {
    return (
      <Card className="text-center">
        <div className="text-yellow-600 mb-4">
          <span className="text-4xl">🚫</span>
          <p className="mt-2 font-medium">Access Denied</p>
          <p className="text-sm text-gray-600 mt-1">
            Only workers can generate QR codes. You are logged in as: <strong className="capitalize">{user?.role}</strong>
          </p>
        </div>
        <Button onClick={() => navigate('/')}>Go to Home</Button>
      </Card>
    );
  }

  if (error) {
    const axiosError = error as AxiosError<ApiError>;
    const errorMessage = axiosError.response?.data?.message || 'Failed to generate QR code';

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
      <Card className="text-center">
        <h2 className="text-xl font-semibold text-gray-900 mb-2">Your QR Code</h2>
        <p className="text-gray-600 mb-6">
          Show this code to your representative for check-in
        </p>

        {/* QR Code Display */}
        <div className="relative inline-block">
          {isLoading ? (
            <div className="w-64 h-64 bg-gray-100 rounded-lg flex items-center justify-center">
              <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600" />
            </div>
          ) : (
            <div className="p-4 bg-white rounded-lg shadow-inner border-2 border-gray-200">
              <QRCodeSVG
                value={totpData?.code || ''}
                size={240}
                level="H"
                includeMargin
              />
            </div>
          )}

          {/* Live indicator */}
          <div className="absolute -top-2 -right-2 flex items-center gap-1 bg-green-500 text-white px-2 py-1 rounded-full text-xs font-medium">
            <span className="w-2 h-2 bg-white rounded-full animate-pulse" />
            LIVE
          </div>
        </div>

        {/* Timer */}
        <div className="mt-6">
          <div className="text-sm text-gray-600 mb-2">Code expires in</div>
          <div className={`text-4xl font-bold ${getUrgencyColor()}`}>
            {remainingSeconds}s
          </div>

          {/* Progress bar */}
          <div className="mt-3 h-2 bg-gray-200 rounded-full overflow-hidden max-w-xs mx-auto">
            <div
              className={`h-full transition-all duration-1000 ${
                remainingSeconds > 15
                  ? 'bg-green-500'
                  : remainingSeconds > 5
                  ? 'bg-yellow-500'
                  : 'bg-red-500'
              }`}
              style={{ width: `${progress}%` }}
            />
          </div>
        </div>

        {/* Code display */}
        {totpData?.code && (
          <div className="mt-6 p-3 bg-gray-50 rounded-lg">
            <div className="text-sm text-gray-600">Manual Code</div>
            <div className="text-2xl font-mono font-bold tracking-widest text-gray-900">
              {totpData.code}
            </div>
          </div>
        )}
      </Card>

      {/* Instructions */}
      <Card>
        <h3 className="font-medium text-gray-900 mb-3">Instructions</h3>
        <ol className="list-decimal list-inside space-y-2 text-sm text-gray-600">
          <li>Show this QR code to your representative</li>
          <li>The code refreshes every 30 seconds for security</li>
          <li>Make sure the LIVE indicator is visible</li>
          <li>Don't take screenshots - they will become invalid</li>
        </ol>
      </Card>
    </div>
  );
}
