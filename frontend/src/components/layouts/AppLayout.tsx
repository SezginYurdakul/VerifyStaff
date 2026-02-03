import { type ReactNode, useState } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { useAuthStore } from '@/stores/authStore';
import { useSyncStore } from '@/stores/syncStore';
import { BarChart3, LogOut, Camera, Monitor, QrCode, Settings, FileText, Users } from 'lucide-react';

interface AppLayoutProps {
  children: ReactNode;
}

interface NavItemProps {
  to: string;
  icon: React.ElementType;
  label: string;
  isActive: boolean;
}

function NavItem({ to, icon: Icon, label, isActive }: NavItemProps) {
  const [showTooltip, setShowTooltip] = useState(false);

  return (
    <div className="relative">
      <Link
        to={to}
        className={`p-2.5 rounded-lg transition-colors flex items-center justify-center ${
          isActive
            ? 'bg-blue-100 text-blue-600'
            : 'text-gray-500 hover:bg-gray-100 hover:text-gray-700'
        }`}
        onMouseEnter={() => setShowTooltip(true)}
        onMouseLeave={() => setShowTooltip(false)}
      >
        <Icon className="w-6 h-6" />
      </Link>
      {showTooltip && (
        <div className="absolute top-full mt-2 left-1/2 -translate-x-1/2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap z-50">
          {label}
          <div className="absolute -top-1 left-1/2 -translate-x-1/2 w-2 h-2 bg-gray-900 rotate-45" />
        </div>
      )}
    </div>
  );
}

export default function AppLayout({ children }: AppLayoutProps) {
  const location = useLocation();
  const navigate = useNavigate();
  const { user, logout } = useAuthStore();
  const { pendingCount } = useSyncStore();
  const isOnline = navigator.onLine;

  const handleLogout = () => {
    logout();
    navigate('/login');
  };

  const isWorker = user?.role === 'worker';
  const isAdmin = user?.role === 'admin';
  const isAdminOrRep = user?.role === 'admin' || user?.role === 'representative';

  return (
    <div className="min-h-screen bg-gray-50 flex flex-col">
      {/* Header */}
      <header className="bg-white shadow-sm border-b border-gray-200">
        <div className="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between">
          <div className="flex items-center gap-4">
            <Link to="/" className="flex items-center gap-2 hover:opacity-80">
              <h1 className="text-xl font-bold text-gray-900">VerifyStaff</h1>
            </Link>
            <span
              className={`text-xs px-2 py-1 rounded-full ${
                isOnline
                  ? 'bg-green-100 text-green-800'
                  : 'bg-red-100 text-red-800'
              }`}
            >
              {isOnline ? 'Online' : 'Offline'}
            </span>
            {pendingCount > 0 && (
              <span className="text-xs px-2 py-1 rounded-full bg-yellow-100 text-yellow-800">
                {pendingCount} pending
              </span>
            )}
          </div>
          <div className="flex items-center gap-1">
            {/* Navigation Links */}
            {isAdminOrRep && (
              <>
                <NavItem
                  to="/"
                  icon={BarChart3}
                  label="Dashboard"
                  isActive={location.pathname === '/'}
                />
                <NavItem
                  to="/reports"
                  icon={FileText}
                  label="Reports"
                  isActive={location.pathname === '/reports' || location.pathname.startsWith('/reports/')}
                />
                <NavItem
                  to="/scan"
                  icon={Camera}
                  label="Scan Worker QR"
                  isActive={location.pathname === '/scan'}
                />
                <NavItem
                  to="/kiosk-display"
                  icon={Monitor}
                  label="Kiosk QR Display"
                  isActive={location.pathname === '/kiosk-display'}
                />
              </>
            )}
            {isWorker && (
              <>
                <NavItem
                  to="/"
                  icon={BarChart3}
                  label="Dashboard"
                  isActive={location.pathname === '/'}
                />
                <NavItem
                  to="/qr"
                  icon={QrCode}
                  label="My QR Code"
                  isActive={location.pathname === '/qr'}
                />
                <NavItem
                  to="/kiosk-scan"
                  icon={Monitor}
                  label="Kiosk Check-in"
                  isActive={location.pathname === '/kiosk-scan'}
                />
              </>
            )}
            {isAdmin && (
              <>
                <NavItem
                  to="/users"
                  icon={Users}
                  label="Users"
                  isActive={location.pathname === '/users'}
                />
                <NavItem
                  to="/settings"
                  icon={Settings}
                  label="Settings"
                  isActive={location.pathname === '/settings'}
                />
              </>
            )}

            {/* Divider */}
            <div className="w-px h-8 bg-gray-200 mx-3" />

            {/* User Info */}
            <span className="text-sm text-gray-600 hidden sm:inline mr-2">{user?.name}</span>
            <div className="relative">
              <button
                onClick={handleLogout}
                className="p-2.5 rounded-lg text-gray-500 hover:bg-red-50 hover:text-red-600 transition-colors"
                title="Logout"
              >
                <LogOut className="w-6 h-6" />
              </button>
            </div>
          </div>
        </div>
      </header>

      {/* Main content */}
      <main className="flex-1 max-w-7xl mx-auto w-full px-4 py-6">
        {children}
      </main>
    </div>
  );
}
