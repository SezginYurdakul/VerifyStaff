import { Link } from 'react-router-dom';
import { useAuthStore } from '@/stores/authStore';
import { useSyncStore } from '@/stores/syncStore';
import { Card } from '@/components/ui';

export default function HomePage() {
  const user = useAuthStore((state) => state.user);
  const { pendingCount, lastSyncTime } = useSyncStore();
  const isOnline = navigator.onLine;

  const roleActions = {
    worker: [
      {
        title: 'Show My QR Code',
        description: 'Display your QR code for representative check-in',
        path: '/qr',
        icon: '📱',
        color: 'bg-blue-500',
      },
      {
        title: 'Kiosk Check-in',
        description: 'Scan a kiosk QR code to check in/out',
        path: '/kiosk-scan',
        icon: '🖥️',
        color: 'bg-purple-500',
      },
    ],
    representative: [
      {
        title: 'Scan Worker QR',
        description: 'Scan worker QR codes to record attendance',
        path: '/scan',
        icon: '📷',
        color: 'bg-green-500',
      },
      {
        title: 'Kiosk QR Display',
        description: 'Open kiosk QR code display for worker self check-in',
        path: '/kiosk-display',
        icon: '🖥️',
        color: 'bg-purple-500',
      },
    ],
    admin: [
      {
        title: 'Scan Worker QR',
        description: 'Scan worker QR codes to record attendance',
        path: '/scan',
        icon: '📷',
        color: 'bg-green-500',
      },
      {
        title: 'Kiosk QR Display',
        description: 'Open kiosk QR code display for worker self check-in',
        path: '/kiosk-display',
        icon: '🖥️',
        color: 'bg-purple-500',
      },
      {
        title: 'Settings',
        description: 'Manage system configuration and kiosks',
        path: '/settings',
        icon: '⚙️',
        color: 'bg-gray-500',
      },
    ],
  };

  const actions = user ? roleActions[user.role] || [] : [];

  return (
    <div className="space-y-6">
      {/* Welcome Card */}
      <Card>
        <h2 className="text-xl font-semibold text-gray-900">
          Welcome, {user?.name}!
        </h2>
        <p className="text-gray-600 mt-1">
          Role: <span className="capitalize font-medium">{user?.role}</span>
        </p>
      </Card>

      {/* Status Card */}
      <Card>
        <h3 className="text-lg font-medium text-gray-900 mb-4">Status</h3>
        <div className="space-y-3">
          <div className="flex items-center justify-between">
            <span className="text-gray-600">Connection</span>
            <span
              className={`px-2 py-1 rounded-full text-xs font-medium ${
                isOnline
                  ? 'bg-green-100 text-green-800'
                  : 'bg-red-100 text-red-800'
              }`}
            >
              {isOnline ? 'Online' : 'Offline'}
            </span>
          </div>
          {user?.role === 'representative' && (
            <>
              <div className="flex items-center justify-between">
                <span className="text-gray-600">Pending Syncs</span>
                <span
                  className={`px-2 py-1 rounded-full text-xs font-medium ${
                    pendingCount > 0
                      ? 'bg-yellow-100 text-yellow-800'
                      : 'bg-gray-100 text-gray-800'
                  }`}
                >
                  {pendingCount}
                </span>
              </div>
              {lastSyncTime && (
                <div className="flex items-center justify-between">
                  <span className="text-gray-600">Last Sync</span>
                  <span className="text-sm text-gray-800">
                    {new Date(lastSyncTime).toLocaleTimeString()}
                  </span>
                </div>
              )}
            </>
          )}
        </div>
      </Card>

      {/* Action Cards */}
      <div className="grid gap-4">
        {actions.map((action) => (
          <Link key={action.path} to={action.path}>
            <Card className="hover:shadow-md transition-shadow cursor-pointer">
              <div className="flex items-center gap-4">
                <div
                  className={`${action.color} w-12 h-12 rounded-lg flex items-center justify-center text-2xl`}
                >
                  {action.icon}
                </div>
                <div>
                  <h3 className="font-semibold text-gray-900">{action.title}</h3>
                  <p className="text-sm text-gray-600">{action.description}</p>
                </div>
              </div>
            </Card>
          </Link>
        ))}
      </div>
    </div>
  );
}
