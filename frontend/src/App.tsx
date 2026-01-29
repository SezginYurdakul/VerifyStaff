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
import RegisterPage from '@/features/auth/RegisterPage';

// App pages
import HomePage from '@/features/home/HomePage';
import WorkerQRPage from '@/features/worker/WorkerQRPage';
import ScannerPage from '@/features/scanner/ScannerPage';

// Kiosk pages
import { KioskDisplayPage, KioskSelectPage, WorkerKioskScanPage } from '@/features/kiosk';

// Settings pages
import { SettingsPage } from '@/features/settings';

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

  if (!isAuthenticated) {
    return <Navigate to="/login" replace />;
  }

  return <>{children}</>;
}

// Public route wrapper (redirect if authenticated)
function PublicRoute({ children }: { children: React.ReactNode }) {
  const isAuthenticated = useAuthStore((state) => state.isAuthenticated);

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
            path="/register"
            element={
              <PublicRoute>
                <AuthLayout>
                  <RegisterPage />
                </AuthLayout>
              </PublicRoute>
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
