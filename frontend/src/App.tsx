import { useEffect } from 'react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { useAuthStore } from '@/stores/authStore';
import { syncPendingLogs, refreshPendingCount } from '@/lib/syncService';

// Layouts
import AuthLayout from '@/components/layouts/AuthLayout';
import AppLayout from '@/components/layouts/AppLayout';

// Auth pages
import LoginPage from '@/features/auth/LoginPage';
import SetPasswordPage from '@/features/auth/SetPasswordPage';

// App pages
import WorkerQRPage from '@/features/worker/WorkerQRPage';
import WorkerDashboard from '@/features/worker/WorkerDashboard';
import ScannerPage from '@/features/scanner/ScannerPage';

// Kiosk pages
import { KioskDisplayPage, KioskSelectPage, WorkerKioskScanPage } from '@/features/kiosk';

// Settings pages
import { SettingsPage } from '@/features/settings';

// Dashboard pages
import { DashboardPage, AnomaliesPage } from '@/features/dashboard';

// Reports pages
import { ReportsPage, WorkerDetailPage } from '@/features/reports';

// Users pages
import { UsersPage } from '@/features/users';

// Create query client
const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 1000 * 60 * 5, // 5 minutes
      retry: 1,
    },
  },
});

// Protected route wrapper
function ProtectedRoute({ children }: { children: React.ReactNode }) {
  const isAuthenticated = useAuthStore((state) => state.isAuthenticated);
  const hasHydrated = useAuthStore((state) => state.hasHydrated);

  // Wait for hydration before making auth decisions
  if (!hasHydrated) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600" />
      </div>
    );
  }

  if (!isAuthenticated) {
    return <Navigate to="/login" replace />;
  }

  return <>{children}</>;
}

// Home page - redirects based on role
function HomePage() {
  const user = useAuthStore((state) => state.user);

  // Worker goes to Worker Dashboard, Admin/Rep goes to Admin Dashboard
  if (user?.role === 'worker') {
    return <WorkerDashboard />;
  }

  return <DashboardPage />;
}

// Public route wrapper (redirect if authenticated)
function PublicRoute({ children }: { children: React.ReactNode }) {
  const isAuthenticated = useAuthStore((state) => state.isAuthenticated);
  const hasHydrated = useAuthStore((state) => state.hasHydrated);

  // Wait for hydration before making auth decisions
  if (!hasHydrated) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600" />
      </div>
    );
  }

  if (isAuthenticated) {
    return <Navigate to="/" replace />;
  }

  return <>{children}</>;
}

function AppSyncInit() {
  const isAuthenticated = useAuthStore((state) => state.isAuthenticated);

  useEffect(() => {
    if (!isAuthenticated) return;

    // Load pending count from IndexedDB on startup
    refreshPendingCount();

    // Sync pending logs if online (catch 403 silently — mode/role mismatch)
    if (navigator.onLine) {
      syncPendingLogs().catch(() => {});
    }

    // Auto-sync when coming back online
    const handleOnline = () => {
      syncPendingLogs().catch(() => {});
    };
    window.addEventListener('online', handleOnline);
    return () => window.removeEventListener('online', handleOnline);
  }, [isAuthenticated]);

  return null;
}

function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <AppSyncInit />
      <BrowserRouter>
        <Routes>
          {/* Auth routes */}
          <Route
            path="/login"
            element={
              <PublicRoute>
                <AuthLayout>
                  <LoginPage />
                </AuthLayout>
              </PublicRoute>
            }
          />
          <Route
            path="/set-password"
            element={
              <AuthLayout>
                <SetPasswordPage />
              </AuthLayout>
            }
          />

          {/* Protected routes */}
          <Route
            path="/"
            element={
              <ProtectedRoute>
                <AppLayout>
                  <HomePage />
                </AppLayout>
              </ProtectedRoute>
            }
          />
          <Route
            path="/qr"
            element={
              <ProtectedRoute>
                <AppLayout>
                  <WorkerQRPage />
                </AppLayout>
              </ProtectedRoute>
            }
          />
          <Route
            path="/scan"
            element={
              <ProtectedRoute>
                <AppLayout>
                  <ScannerPage />
                </AppLayout>
              </ProtectedRoute>
            }
          />
          <Route
            path="/kiosk-scan"
            element={
              <ProtectedRoute>
                <AppLayout>
                  <WorkerKioskScanPage />
                </AppLayout>
              </ProtectedRoute>
            }
          />
          <Route
            path="/kiosk-display"
            element={
              <ProtectedRoute>
                <AppLayout>
                  <KioskSelectPage />
                </AppLayout>
              </ProtectedRoute>
            }
          />
          <Route
            path="/settings"
            element={
              <ProtectedRoute>
                <AppLayout>
                  <SettingsPage />
                </AppLayout>
              </ProtectedRoute>
            }
          />
          <Route
            path="/anomalies"
            element={
              <ProtectedRoute>
                <AppLayout>
                  <AnomaliesPage />
                </AppLayout>
              </ProtectedRoute>
            }
          />
          <Route
            path="/reports"
            element={
              <ProtectedRoute>
                <AppLayout>
                  <ReportsPage />
                </AppLayout>
              </ProtectedRoute>
            }
          />
          <Route
            path="/reports/worker/:workerId"
            element={
              <ProtectedRoute>
                <AppLayout>
                  <WorkerDetailPage />
                </AppLayout>
              </ProtectedRoute>
            }
          />
          <Route
            path="/users"
            element={
              <ProtectedRoute>
                <AppLayout>
                  <UsersPage />
                </AppLayout>
              </ProtectedRoute>
            }
          />

          {/* Public kiosk display (no auth required) */}
          <Route path="/kiosk/:kioskCode/display" element={<KioskDisplayPage />} />

          {/* Catch all */}
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </BrowserRouter>
    </QueryClientProvider>
  );
}

export default App;
