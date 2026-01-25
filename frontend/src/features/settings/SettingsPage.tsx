import { useState, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import { getSettings, updateSetting, getAttendanceMode, updateAttendanceMode } from '@/api/settings';
import { getKiosks, createKiosk, updateKiosk } from '@/api/kiosk';
import { useAuthStore } from '@/stores/authStore';
import { Card, Button, Input } from '@/components/ui';
import type { Settings } from '@/types';

export default function SettingsPage() {
  const navigate = useNavigate();
  const user = useAuthStore((state) => state.user);
  const queryClient = useQueryClient();
  const [activeTab, setActiveTab] = useState<'general' | 'attendance' | 'kiosks'>('general');

  // Check if user is admin
  const isAdmin = user?.role === 'admin';

  const { data: settingsData, isLoading: settingsLoading, refetch: refetchSettings } = useQuery({
    queryKey: ['settings'],
    queryFn: getSettings,
    enabled: isAdmin,
  });

  const { data: attendanceModeData } = useQuery({
    queryKey: ['attendance-mode'],
    queryFn: getAttendanceMode,
    enabled: isAdmin,
  });

  const updateSettingMutation = useMutation({
    mutationFn: ({ key, value }: { key: string; value: unknown }) => updateSetting(key, value),
    onSuccess: async () => {
      queryClient.invalidateQueries({ queryKey: ['settings'] });
      // Manually refetch to ensure UI updates immediately
      await refetchSettings();
    },
    onError: (error) => {
      console.error('Failed to update setting:', error);
      // You could add a toast notification here
    },
  });

  const updateModeMutation = useMutation({
    mutationFn: (mode: 'representative' | 'kiosk') => updateAttendanceMode(mode),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['attendance-mode'] });
    },
  });

  // Show access denied for non-admins
  if (!isAdmin) {
    return (
      <Card className="text-center">
        <div className="text-yellow-600 mb-4">
          <span className="text-4xl">🚫</span>
          <p className="mt-2 font-medium">Access Denied</p>
          <p className="text-sm text-gray-600 mt-1">
            Only administrators can access settings.
          </p>
        </div>
        <Button onClick={() => navigate('/')}>Go to Home</Button>
      </Card>
    );
  }

  if (settingsLoading) {
    return (
      <Card className="text-center py-8">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto" />
        <p className="mt-2 text-gray-600">Loading settings...</p>
      </Card>
    );
  }

  const tabs = [
    { id: 'general', label: 'General' },
    { id: 'attendance', label: 'Attendance' },
    { id: 'kiosks', label: 'Kiosks' },
  ] as const;

  return (
    <div className="space-y-6">
      {/* Header */}
      <Card>
        <h2 className="text-xl font-semibold text-gray-900">Settings</h2>
        <p className="text-gray-600 mt-1">Manage system configuration</p>
      </Card>

      {/* Tabs */}
      <div className="flex gap-2 border-b border-gray-200">
        {tabs.map((tab) => (
          <button
            key={tab.id}
            onClick={() => setActiveTab(tab.id)}
            className={`px-4 py-2 font-medium text-sm border-b-2 transition-colors ${
              activeTab === tab.id
                ? 'border-blue-500 text-blue-600'
                : 'border-transparent text-gray-500 hover:text-gray-700'
            }`}
          >
            {tab.label}
          </button>
        ))}
      </div>

      {/* General Settings */}
      {activeTab === 'general' && (
        <GeneralSettings
          settings={settingsData?.settings}
          onUpdate={(key, value) => updateSettingMutation.mutate({ key, value })}
          isUpdating={updateSettingMutation.isPending}
        />
      )}

      {/* Attendance Settings */}
      {activeTab === 'attendance' && (
        <AttendanceSettings
          settings={settingsData?.settings}
          attendanceMode={attendanceModeData?.attendance_mode}
          onUpdate={(key, value) => updateSettingMutation.mutate({ key, value })}
          onModeChange={(mode) => updateModeMutation.mutate(mode)}
          isUpdating={updateSettingMutation.isPending || updateModeMutation.isPending}
        />
      )}

      {/* Kiosks Tab */}
      {activeTab === 'kiosks' && (
        <KioskSettings />
      )}
    </div>
  );
}

// General Settings Component
function GeneralSettings({
  settings,
  onUpdate,
  isUpdating,
}: {
  settings?: Settings;
  onUpdate: (key: string, value: unknown) => void;
  isUpdating: boolean;
}) {
  const workHours = settings?.work_hours || {};
  const general = settings?.general || {};

  return (
    <div className="space-y-4">
      <Card>
        <h3 className="font-medium text-gray-900 mb-4">QR Code Settings</h3>
        <div className="grid grid-cols-2 gap-4">
          <SettingField
            label="Worker QR Refresh Seconds"
            value={general.totp_refresh_seconds?.value as number || 30}
            type="number"
            min={15}
            max={60}
            onSave={(value) => {
              const parsed = parseInt(value, 10);
              if (isNaN(parsed)) {
                console.error('Invalid number value:', value);
                return;
              }
              const numValue = Math.max(15, Math.min(60, parsed));
              onUpdate('totp_refresh_seconds', numValue);
            }}
            disabled={isUpdating}
            hint="Worker QR code refresh interval (15-60 seconds)"
          />
          <SettingField
            label="Kiosk QR Refresh Seconds"
            value={general.kiosk_totp_refresh_seconds?.value as number || 30}
            type="number"
            min={15}
            max={60}
            onSave={(value) => {
              const parsed = parseInt(value, 10);
              if (isNaN(parsed)) {
                console.error('Invalid number value:', value);
                return;
              }
              const numValue = Math.max(15, Math.min(60, parsed));
              onUpdate('kiosk_totp_refresh_seconds', numValue);
            }}
            disabled={isUpdating}
            hint="Kiosk QR code refresh interval (15-60 seconds)"
          />
        </div>
      </Card>

      <Card>
        <h3 className="font-medium text-gray-900 mb-4">Work Hours</h3>
        <div className="grid grid-cols-2 gap-4">
          <SettingField
            label="Work Start Time"
            value={workHours.work_start_time?.value as string || '09:00'}
            type="time"
            onSave={(value) => onUpdate('work_start_time', value)}
            disabled={isUpdating}
          />
          <SettingField
            label="Work End Time"
            value={workHours.work_end_time?.value as string || '18:00'}
            type="time"
            onSave={(value) => onUpdate('work_end_time', value)}
            disabled={isUpdating}
          />
          <SettingField
            label="Late Threshold (minutes)"
            value={workHours.late_threshold_minutes?.value as number || 15}
            type="number"
            onSave={(value) => onUpdate('late_threshold_minutes', parseInt(value))}
            disabled={isUpdating}
          />
          <SettingField
            label="Early Departure Threshold (min)"
            value={workHours.early_departure_threshold_minutes?.value as number || 15}
            type="number"
            onSave={(value) => onUpdate('early_departure_threshold_minutes', parseInt(value))}
            disabled={isUpdating}
          />
        </div>
      </Card>

      <Card>
        <h3 className="font-medium text-gray-900 mb-4">Auto Check-out</h3>
        <div className="space-y-4">
          <SettingToggle
            label="Enable Auto Check-out"
            description="Automatically check out workers who forgot at end of day"
            value={settings?.attendance?.auto_checkout_enabled?.value as boolean || false}
            onToggle={(value) => onUpdate('auto_checkout_enabled', value)}
            disabled={isUpdating}
          />
          {(settings?.attendance?.auto_checkout_enabled?.value as boolean) && (
            <SettingField
              label="Auto Check-out Time"
              value={settings?.attendance?.auto_checkout_time?.value as string || '23:59'}
              type="time"
              onSave={(value) => onUpdate('auto_checkout_time', value)}
              disabled={isUpdating}
            />
          )}
        </div>
      </Card>
    </div>
  );
}

// Attendance Settings Component
function AttendanceSettings({
  settings,
  attendanceMode,
  onUpdate,
  onModeChange,
  isUpdating,
}: {
  settings?: Settings;
  attendanceMode?: 'representative' | 'kiosk';
  onUpdate: (key: string, value: unknown) => void;
  onModeChange: (mode: 'representative' | 'kiosk') => void;
  isUpdating: boolean;
}) {
  return (
    <div className="space-y-4">
      <Card>
        <h3 className="font-medium text-gray-900 mb-4">Attendance Mode</h3>
        <p className="text-sm text-gray-600 mb-4">
          Choose how workers record their attendance
        </p>
        <div className="space-y-3">
          <label className={`flex items-start gap-3 p-4 border rounded-lg cursor-pointer transition-colors ${
            attendanceMode === 'representative' ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:bg-gray-50'
          }`}>
            <input
              type="radio"
              name="attendance_mode"
              value="representative"
              checked={attendanceMode === 'representative'}
              onChange={() => onModeChange('representative')}
              disabled={isUpdating}
              className="mt-1"
            />
            <div>
              <div className="font-medium text-gray-900">Representative Mode</div>
              <p className="text-sm text-gray-600">
                Worker shows QR code on their phone, representative scans it to record attendance
              </p>
            </div>
          </label>
          <label className={`flex items-start gap-3 p-4 border rounded-lg cursor-pointer transition-colors ${
            attendanceMode === 'kiosk' ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:bg-gray-50'
          }`}>
            <input
              type="radio"
              name="attendance_mode"
              value="kiosk"
              checked={attendanceMode === 'kiosk'}
              onChange={() => onModeChange('kiosk')}
              disabled={isUpdating}
              className="mt-1"
            />
            <div>
              <div className="font-medium text-gray-900">Kiosk Mode</div>
              <p className="text-sm text-gray-600">
                Kiosk displays QR code, worker scans it with their phone to record attendance
              </p>
            </div>
          </label>
        </div>
      </Card>

      <Card>
        <h3 className="font-medium text-gray-900 mb-4">Toggle Mode</h3>
        <SettingToggle
          label="Enable Toggle Mode"
          description="Automatically toggle between check-in and check-out based on last action"
          value={settings?.attendance?.toggle_mode_enabled?.value as boolean || false}
          onToggle={(value) => onUpdate('toggle_mode_enabled', value)}
          disabled={isUpdating}
        />
      </Card>
    </div>
  );
}

// Kiosk Settings Component
function KioskSettings() {
  const queryClient = useQueryClient();
  const [showAddForm, setShowAddForm] = useState(false);
  const [newKiosk, setNewKiosk] = useState({ name: '', location: '' });

  const { data: kiosks, isLoading } = useQuery({
    queryKey: ['kiosks'],
    queryFn: getKiosks,
  });

  const createMutation = useMutation({
    mutationFn: (data: { name: string; location?: string }) => createKiosk(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['kiosks'] });
      setShowAddForm(false);
      setNewKiosk({ name: '', location: '' });
    },
  });

  const updateMutation = useMutation({
    mutationFn: ({ code, status }: { code: string; status: 'active' | 'inactive' | 'maintenance' }) =>
      updateKiosk(code, { status }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['kiosks'] });
    },
  });

  if (isLoading) {
    return (
      <Card className="text-center py-8">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto" />
        <p className="mt-2 text-gray-600">Loading kiosks...</p>
      </Card>
    );
  }

  return (
    <div className="space-y-4">
      <Card>
        <div className="flex justify-between items-center mb-4">
          <div>
            <h3 className="font-medium text-gray-900">Kiosk Devices</h3>
            <p className="text-sm text-gray-600">Manage kiosk devices for self check-in</p>
          </div>
          <Button onClick={() => setShowAddForm(true)} size="sm">
            Add Kiosk
          </Button>
        </div>

        {/* Add Kiosk Form */}
        {showAddForm && (
          <div className="mb-4 p-4 bg-gray-50 rounded-lg">
            <h4 className="font-medium text-gray-900 mb-3">Add New Kiosk</h4>
            <div className="space-y-3">
              <Input
                label="Name"
                value={newKiosk.name}
                onChange={(e) => setNewKiosk({ ...newKiosk, name: e.target.value })}
                placeholder="e.g., Main Entrance Kiosk"
              />
              <Input
                label="Location (optional)"
                value={newKiosk.location}
                onChange={(e) => setNewKiosk({ ...newKiosk, location: e.target.value })}
                placeholder="e.g., Building A, Ground Floor"
              />
              <div className="flex gap-2">
                <Button
                  onClick={() => createMutation.mutate(newKiosk)}
                  disabled={!newKiosk.name || createMutation.isPending}
                  size="sm"
                >
                  {createMutation.isPending ? 'Creating...' : 'Create'}
                </Button>
                <Button
                  variant="secondary"
                  onClick={() => {
                    setShowAddForm(false);
                    setNewKiosk({ name: '', location: '' });
                  }}
                  size="sm"
                >
                  Cancel
                </Button>
              </div>
            </div>
          </div>
        )}

        {/* Kiosk List */}
        {kiosks && kiosks.length > 0 ? (
          <div className="space-y-3">
            {kiosks.map((kiosk) => (
              <div
                key={kiosk.id}
                className="flex items-center justify-between p-4 border border-gray-200 rounded-lg"
              >
                <div>
                  <div className="font-medium text-gray-900">{kiosk.name}</div>
                  <div className="text-sm text-gray-500">
                    Code: <span className="font-mono">{kiosk.code}</span>
                    {kiosk.location && ` • ${kiosk.location}`}
                  </div>
                  {kiosk.last_heartbeat_at && (
                    <div className="text-xs text-gray-400 mt-1">
                      Last active: {new Date(kiosk.last_heartbeat_at).toLocaleString()}
                    </div>
                  )}
                </div>
                <div className="flex items-center gap-3">
                  <select
                    value={kiosk.status}
                    onChange={(e) => updateMutation.mutate({
                      code: kiosk.code,
                      status: e.target.value as 'active' | 'inactive' | 'maintenance',
                    })}
                    disabled={updateMutation.isPending}
                    className={`text-sm px-2 py-1 rounded border ${
                      kiosk.status === 'active'
                        ? 'bg-green-50 border-green-200 text-green-700'
                        : kiosk.status === 'maintenance'
                        ? 'bg-yellow-50 border-yellow-200 text-yellow-700'
                        : 'bg-gray-50 border-gray-200 text-gray-700'
                    }`}
                  >
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="maintenance">Maintenance</option>
                  </select>
                  <Button
                    variant="secondary"
                    size="sm"
                    onClick={() => {
                      const url = `/kiosk/${kiosk.code}/display`;
                      window.open(url, '_blank');
                    }}
                  >
                    Open Display
                  </Button>
                </div>
              </div>
            ))}
          </div>
        ) : (
          <div className="text-center py-8 text-gray-500">
            <p>No kiosks configured yet</p>
            <p className="text-sm mt-1">Add a kiosk to enable self check-in mode</p>
          </div>
        )}
      </Card>
    </div>
  );
}

// Setting Field Component
function SettingField({
  label,
  value,
  type,
  onSave,
  disabled,
  min,
  max,
  hint,
}: {
  label: string;
  value: string | number;
  type: 'text' | 'time' | 'number';
  onSave: (value: string) => void;
  disabled: boolean;
  min?: number;
  max?: number;
  hint?: string;
}) {
  const [localValue, setLocalValue] = useState(String(value));
  const [isDirty, setIsDirty] = useState(false);

  // Sync localValue when value prop changes (e.g., after successful save)
  useEffect(() => {
    setLocalValue(String(value));
    setIsDirty(false);
  }, [value]);

  const handleChange = (newValue: string) => {
    setLocalValue(newValue);
    setIsDirty(newValue !== String(value));
  };

  const handleSave = () => {
    onSave(localValue);
    // Don't set isDirty to false here - let useEffect handle it when value prop updates
  };

  return (
    <div>
      <label className="block text-sm font-medium text-gray-700 mb-1">{label}</label>
      <div className="flex gap-2">
        <input
          type={type}
          value={localValue}
          onChange={(e) => handleChange(e.target.value)}
          disabled={disabled}
          min={min}
          max={max}
          className="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 disabled:bg-gray-100"
        />
        {isDirty && (
          <Button onClick={handleSave} disabled={disabled} size="sm">
            Save
          </Button>
        )}
      </div>
      {hint && <p className="text-xs text-gray-500 mt-1">{hint}</p>}
    </div>
  );
}

// Setting Toggle Component
function SettingToggle({
  label,
  description,
  value,
  onToggle,
  disabled,
}: {
  label: string;
  description: string;
  value: boolean;
  onToggle: (value: boolean) => void;
  disabled: boolean;
}) {
  return (
    <div className="flex items-center justify-between">
      <div>
        <div className="font-medium text-gray-900">{label}</div>
        <p className="text-sm text-gray-600">{description}</p>
      </div>
      <button
        onClick={() => onToggle(!value)}
        disabled={disabled}
        className={`relative w-12 h-6 rounded-full transition-colors ${
          value ? 'bg-blue-600' : 'bg-gray-300'
        } ${disabled ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'}`}
      >
        <div
          className={`absolute top-1 w-4 h-4 bg-white rounded-full transition-transform ${
            value ? 'translate-x-7' : 'translate-x-1'
          }`}
        />
      </button>
    </div>
  );
}
