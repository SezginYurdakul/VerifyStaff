import { type ReactNode, useState, useEffect } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { useAuthStore } from '@/stores/authStore';
import { useSyncStore } from '@/stores/syncStore';
import { BarChart3, LogOut, Camera, Monitor, QrCode, Settings, FileText, Users, Building2 } from 'lucide-react';

interface AppLayoutProps {
  children: ReactNode;
}

interface NavItemProps {
  to: string;
  icon: React.ElementType;
  label: string;
  isActive: boolean;
}

function NavItemHeader({ to, icon: Icon, label, isActive }: NavItemProps) {
  const [showTooltip, setShowTooltip] = useState(false);

  return (
    <div className="relative">
      <Link
        to={to}
        className={`p-2.5 rounded-lg transition-colors flex items-center justify-center ${
          isActive
            ? 'bg-brand-200/20 text-white'
            : 'text-brand-300 hover:bg-brand-800 hover:text-white'
        }`}
        onMouseEnter={() => setShowTooltip(true)}
        onMouseLeave={() => setShowTooltip(false)}
      >
        <Icon className="w-6 h-6" />
      </Link>
      {showTooltip && (
        <div className="absolute top-full mt-2 left-1/2 -translate-x-1/2 px-2 py-1 bg-white text-brand-900 text-xs rounded whitespace-nowrap z-50 shadow-lg">
          {label}
          <div className="absolute -top-1 left-1/2 -translate-x-1/2 w-2 h-2 bg-white rotate-45" />
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
  const [isOnline, setIsOnline] = useState(navigator.onLine);

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

  const handleLogout = () => {
    logout();
    navigate('/login');
  };

  const isWorker = user?.role === 'worker';
  const isAdmin = user?.role === 'admin';
  const isAdminOrRep = user?.role === 'admin' || user?.role === 'representative';

  return (
    <div className="min-h-screen bg-brand-50 flex flex-col">
      {/* Header */}
      <header className="bg-brand-900 shadow-sm border-b border-brand-800">
        <div className="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between">
          <div className="flex items-center gap-2 sm:gap-4">
            <Link to="/" className="flex items-center gap-2 hover:opacity-80">
              <img src="/logo.svg" alt="VerifyStaff" className="h-8 w-auto" />
              <h1 className="text-xl font-bold text-white hidden sm:block">VerifyStaff</h1>
            </Link>
            <span
              className={`text-xs px-2 py-1 rounded-full ${
                isOnline
                  ? 'bg-green-500/20 text-green-200'
                  : 'bg-accent-500/20 text-accent-400'
              }`}
            >
              {isOnline ? 'Online' : 'Offline'}
            </span>
            {pendingCount > 0 && (
              <span className="text-xs px-2 py-1 rounded-full bg-yellow-500/20 text-yellow-200">
                {pendingCount} pending
              </span>
            )}
          </div>
          <div className="flex items-center gap-1">
            {/* Navigation Links */}
            {isAdminOrRep && (
              <>
                <NavItemHeader
                  to="/"
                  icon={BarChart3}
                  label="Dashboard"
                  isActive={location.pathname === '/'}
                />
                <NavItemHeader
                  to="/reports"
                  icon={FileText}
                  label="Reports"
                  isActive={location.pathname === '/reports' || location.pathname.startsWith('/reports/')}
                />
                <NavItemHeader
                  to="/scan"
                  icon={Camera}
                  label="Scan Worker QR"
                  isActive={location.pathname === '/scan'}
                />
                <NavItemHeader
                  to="/kiosk-display"
                  icon={Monitor}
                  label="Kiosk QR Display"
                  isActive={location.pathname === '/kiosk-display'}
                />
              </>
            )}
            {isWorker && (
              <>
                <NavItemHeader
                  to="/"
                  icon={BarChart3}
                  label="Dashboard"
                  isActive={location.pathname === '/'}
                />
                <NavItemHeader
                  to="/qr"
                  icon={QrCode}
                  label="My QR Code"
                  isActive={location.pathname === '/qr'}
                />
                <NavItemHeader
                  to="/kiosk-scan"
                  icon={Monitor}
                  label="Kiosk Check-in"
                  isActive={location.pathname === '/kiosk-scan'}
                />
              </>
            )}
            {isAdmin && (
              <>
                <NavItemHeader
                  to="/users"
                  icon={Users}
                  label="Users"
                  isActive={location.pathname === '/users'}
                />
                <NavItemHeader
                  to="/departments"
                  icon={Building2}
                  label="Departments"
                  isActive={location.pathname === '/departments'}
                />
                <NavItemHeader
                  to="/settings"
                  icon={Settings}
                  label="Settings"
                  isActive={location.pathname === '/settings'}
                />
              </>
            )}

            {/* Divider */}
            <div className="w-px h-8 bg-brand-700 mx-3" />

            {/* User Info */}
            <span className="text-sm text-brand-200 hidden sm:inline mr-2">{user?.name}</span>
            <div className="relative">
              <button
                onClick={handleLogout}
                className="p-2.5 rounded-lg text-brand-300 hover:bg-accent-500/20 hover:text-accent-400 transition-colors"
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
