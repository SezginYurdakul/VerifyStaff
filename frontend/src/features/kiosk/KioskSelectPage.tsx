import { useQuery } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import { getKiosks } from '@/api/kiosk';
import { Card, Button } from '@/components/ui';

export default function KioskSelectPage() {
  const navigate = useNavigate();

  const { data: kiosks, isLoading, error } = useQuery({
    queryKey: ['kiosks'],
    queryFn: getKiosks,
  });

  if (isLoading) {
    return (
      <Card className="text-center py-8">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto" />
        <p className="mt-2 text-gray-600">Loading kiosks...</p>
      </Card>
    );
  }

  if (error) {
    return (
      <Card className="text-center py-8">
        <p className="text-red-600">Failed to load kiosks</p>
        <Button onClick={() => navigate('/')} className="mt-4">
          Go Home
        </Button>
      </Card>
    );
  }

  const activeKiosks = kiosks?.filter((k) => k.status === 'active') || [];

  if (activeKiosks.length === 0) {
    return (
      <Card className="text-center py-8">
        <span className="text-4xl block mb-3">🖥️</span>
        <p className="font-medium text-gray-900">No active kiosks</p>
        <p className="text-sm text-gray-600 mt-1">
          Create a kiosk in Settings first
        </p>
        <Button onClick={() => navigate('/settings')} className="mt-4" size="sm">
          Go to Settings
        </Button>
      </Card>
    );
  }

  const openKioskDisplay = (kioskCode: string) => {
    window.open(`/kiosk/${kioskCode}/display`, '_blank');
    navigate('/');
  };

  // If only one active kiosk, go directly to display
  if (activeKiosks.length === 1) {
    openKioskDisplay(activeKiosks[0].code);
    return null;
  }

  return (
    <div className="space-y-6">
      <Card>
        <h2 className="text-xl font-semibold text-gray-900 mb-2">
          Select Kiosk
        </h2>
        <p className="text-gray-600">
          Choose a kiosk to open its QR display
        </p>
      </Card>

      <div className="space-y-3">
        {activeKiosks.map((kiosk) => (
          <Card
            key={kiosk.id}
            className="cursor-pointer hover:shadow-md transition-shadow"
            onClick={() => openKioskDisplay(kiosk.code)}
          >
            <div className="flex items-center justify-between">
              <div>
                <div className="font-medium text-gray-900">{kiosk.name}</div>
                <div className="text-sm text-gray-500">
                  <span className="font-mono">{kiosk.code}</span>
                  {kiosk.location && ` · ${kiosk.location}`}
                </div>
              </div>
              <span className="text-2xl">🖥️</span>
            </div>
          </Card>
        ))}
      </div>
    </div>
  );
}
