import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { getDepartments, createDepartment, updateDepartment, deleteDepartment } from '@/api/departments';
import type { CreateDepartmentRequest, UpdateDepartmentRequest } from '@/api/departments';
import type { Department, ApiError } from '@/types';
import { Button, Input, Card, Modal } from '@/components/ui';
import {
  Building2,
  Plus,
  Pencil,
  Trash2,
  RefreshCw,
  AlertCircle,
  Clock,
  Users,
  CheckCircle,
  XCircle,
} from 'lucide-react';
import type { AxiosError } from 'axios';

const DAYS_OF_WEEK = [
  { value: 'monday', label: 'Mon' },
  { value: 'tuesday', label: 'Tue' },
  { value: 'wednesday', label: 'Wed' },
  { value: 'thursday', label: 'Thu' },
  { value: 'friday', label: 'Fri' },
  { value: 'saturday', label: 'Sat' },
  { value: 'sunday', label: 'Sun' },
];

interface DepartmentFormData {
  name: string;
  code: string;
  shift_start: string;
  shift_end: string;
  late_threshold_minutes: number;
  early_departure_threshold_minutes: number;
  regular_work_minutes: number;
  working_days: string[];
  description: string;
  is_active: boolean;
}

const emptyFormData: DepartmentFormData = {
  name: '',
  code: '',
  shift_start: '09:00',
  shift_end: '18:00',
  late_threshold_minutes: 15,
  early_departure_threshold_minutes: 15,
  regular_work_minutes: 480,
  working_days: ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
  description: '',
  is_active: true,
};

function formatTime(time: string): string {
  return time.slice(0, 5); // "09:00:00" -> "09:00"
}

function formatMinutesToHours(minutes: number): string {
  const hours = Math.floor(minutes / 60);
  const mins = minutes % 60;
  return mins > 0 ? `${hours}h ${mins}m` : `${hours}h`;
}

export default function DepartmentsPage() {
  const queryClient = useQueryClient();

  // Modal states
  const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
  const [isEditModalOpen, setIsEditModalOpen] = useState(false);
  const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
  const [selectedDepartment, setSelectedDepartment] = useState<Department | null>(null);
  const [formData, setFormData] = useState<DepartmentFormData>(emptyFormData);
  const [formError, setFormError] = useState('');

  // Fetch departments
  const { data, isLoading, error } = useQuery({
    queryKey: ['departments'],
    queryFn: () => getDepartments(),
  });

  // Create department mutation
  const createMutation = useMutation({
    mutationFn: createDepartment,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['departments'] });
      setIsCreateModalOpen(false);
      setFormData(emptyFormData);
      setFormError('');
    },
    onError: (err: AxiosError<ApiError>) => {
      setFormError(err.response?.data?.message || 'Failed to create department');
    },
  });

  // Update department mutation
  const updateMutation = useMutation({
    mutationFn: ({ id, data }: { id: number; data: UpdateDepartmentRequest }) => updateDepartment(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['departments'] });
      setIsEditModalOpen(false);
      setSelectedDepartment(null);
      setFormData(emptyFormData);
      setFormError('');
    },
    onError: (err: AxiosError<ApiError>) => {
      setFormError(err.response?.data?.message || 'Failed to update department');
    },
  });

  // Delete department mutation
  const deleteMutation = useMutation({
    mutationFn: deleteDepartment,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['departments'] });
      setIsDeleteModalOpen(false);
      setSelectedDepartment(null);
    },
    onError: (err: AxiosError<ApiError>) => {
      setFormError(err.response?.data?.message || 'Failed to delete department');
    },
  });

  const handleOpenCreate = () => {
    setFormData(emptyFormData);
    setFormError('');
    setIsCreateModalOpen(true);
  };

  const handleOpenEdit = (department: Department) => {
    setSelectedDepartment(department);
    setFormData({
      name: department.name,
      code: department.code,
      shift_start: formatTime(department.shift_start),
      shift_end: formatTime(department.shift_end),
      late_threshold_minutes: department.late_threshold_minutes,
      early_departure_threshold_minutes: department.early_departure_threshold_minutes,
      regular_work_minutes: department.regular_work_minutes,
      working_days: department.working_days || ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
      description: department.description || '',
      is_active: department.is_active,
    });
    setFormError('');
    setIsEditModalOpen(true);
  };

  const handleOpenDelete = (department: Department) => {
    setSelectedDepartment(department);
    setFormError('');
    setIsDeleteModalOpen(true);
  };

  const handleCreate = (e: React.FormEvent) => {
    e.preventDefault();
    const createData: CreateDepartmentRequest = {
      name: formData.name,
      code: formData.code.toUpperCase(),
      shift_start: formData.shift_start,
      shift_end: formData.shift_end,
      late_threshold_minutes: formData.late_threshold_minutes,
      early_departure_threshold_minutes: formData.early_departure_threshold_minutes,
      regular_work_minutes: formData.regular_work_minutes,
      working_days: formData.working_days,
      description: formData.description || undefined,
      is_active: formData.is_active,
    };
    createMutation.mutate(createData);
  };

  const handleUpdate = (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedDepartment) return;
    const updateData: UpdateDepartmentRequest = {
      name: formData.name,
      code: formData.code.toUpperCase(),
      shift_start: formData.shift_start,
      shift_end: formData.shift_end,
      late_threshold_minutes: formData.late_threshold_minutes,
      early_departure_threshold_minutes: formData.early_departure_threshold_minutes,
      regular_work_minutes: formData.regular_work_minutes,
      working_days: formData.working_days,
      description: formData.description || undefined,
      is_active: formData.is_active,
    };
    updateMutation.mutate({ id: selectedDepartment.id, data: updateData });
  };

  const handleDelete = () => {
    if (!selectedDepartment) return;
    deleteMutation.mutate(selectedDepartment.id);
  };

  const toggleWorkingDay = (day: string) => {
    setFormData((prev) => ({
      ...prev,
      working_days: prev.working_days.includes(day)
        ? prev.working_days.filter((d) => d !== day)
        : [...prev.working_days, day],
    }));
  };

  if (error) {
    return (
      <Card>
        <div className="text-center py-8 text-red-600">
          <AlertCircle className="w-12 h-12 mx-auto mb-4" />
          <p>Failed to load departments</p>
        </div>
      </Card>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3">
          <div className="p-2 bg-purple-100 rounded-lg">
            <Building2 className="w-6 h-6 text-purple-600" />
          </div>
          <div>
            <h1 className="text-2xl font-bold text-gray-900">Departments</h1>
            <p className="text-sm text-gray-500">Manage departments and shift schedules</p>
          </div>
        </div>
        <Button variant="primary" onClick={handleOpenCreate}>
          <Plus className="w-4 h-4 mr-2" />
          Add Department
        </Button>
      </div>

      {/* Departments List */}
      <Card>
        {isLoading ? (
          <div className="flex items-center justify-center py-12">
            <RefreshCw className="w-8 h-8 text-purple-500 animate-spin" />
          </div>
        ) : (
          <>
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead>
                  <tr className="border-b border-gray-200">
                    <th className="text-left py-3 px-4 text-sm font-medium text-gray-500">Department</th>
                    <th className="text-left py-3 px-4 text-sm font-medium text-gray-500">Shift</th>
                    <th className="text-left py-3 px-4 text-sm font-medium text-gray-500">Work Hours</th>
                    <th className="text-left py-3 px-4 text-sm font-medium text-gray-500">Thresholds</th>
                    <th className="text-left py-3 px-4 text-sm font-medium text-gray-500">Workers</th>
                    <th className="text-left py-3 px-4 text-sm font-medium text-gray-500">Status</th>
                    <th className="text-right py-3 px-4 text-sm font-medium text-gray-500">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {data?.departments.map((department) => (
                    <tr key={department.id} className="border-b border-gray-100 hover:bg-gray-50">
                      <td className="py-3 px-4">
                        <div>
                          <p className="font-medium text-gray-900">{department.name}</p>
                          <p className="text-sm text-gray-500">{department.code}</p>
                        </div>
                      </td>
                      <td className="py-3 px-4">
                        <div className="flex items-center gap-1 text-sm text-gray-600">
                          <Clock className="w-4 h-4 text-gray-400" />
                          {formatTime(department.shift_start)} - {formatTime(department.shift_end)}
                        </div>
                      </td>
                      <td className="py-3 px-4 text-sm text-gray-600">
                        {formatMinutesToHours(department.regular_work_minutes)}
                      </td>
                      <td className="py-3 px-4">
                        <div className="text-xs text-gray-500 space-y-0.5">
                          <p>Late: {department.late_threshold_minutes}m</p>
                          <p>Early: {department.early_departure_threshold_minutes}m</p>
                        </div>
                      </td>
                      <td className="py-3 px-4">
                        <div className="flex items-center gap-1 text-sm text-gray-600">
                          <Users className="w-4 h-4 text-gray-400" />
                          {department.workers_count ?? 0}
                        </div>
                      </td>
                      <td className="py-3 px-4">
                        {department.is_active ? (
                          <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                            <CheckCircle className="w-3 h-3" />
                            Active
                          </span>
                        ) : (
                          <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                            <XCircle className="w-3 h-3" />
                            Inactive
                          </span>
                        )}
                      </td>
                      <td className="py-3 px-4">
                        <div className="flex items-center justify-end gap-2">
                          <button
                            onClick={() => handleOpenEdit(department)}
                            className="p-1.5 text-gray-400 hover:text-amber-600 hover:bg-amber-50 rounded-lg transition-colors"
                            title="Edit Department"
                          >
                            <Pencil className="w-4 h-4" />
                          </button>
                          <button
                            onClick={() => handleOpenDelete(department)}
                            className="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                            title="Delete Department"
                          >
                            <Trash2 className="w-4 h-4" />
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            {data?.departments.length === 0 && (
              <div className="text-center py-12 text-gray-500">
                <Building2 className="w-12 h-12 mx-auto mb-4 opacity-50" />
                <p>No departments found</p>
                <p className="text-sm mt-1">Create your first department to get started</p>
              </div>
            )}
          </>
        )}
      </Card>

      {/* Create Modal */}
      <Modal isOpen={isCreateModalOpen} onClose={() => setIsCreateModalOpen(false)} title="Add New Department">
        <form onSubmit={handleCreate} className="space-y-4">
          {formError && (
            <div className="p-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm">{formError}</div>
          )}

          <div className="grid grid-cols-2 gap-4">
            <Input
              label="Department Name"
              value={formData.name}
              onChange={(e) => setFormData({ ...formData, name: e.target.value })}
              placeholder="e.g., Warehouse"
              required
            />
            <Input
              label="Code"
              value={formData.code}
              onChange={(e) => setFormData({ ...formData, code: e.target.value.toUpperCase() })}
              placeholder="e.g., WH"
              maxLength={10}
              required
            />
          </div>

          <div className="grid grid-cols-2 gap-4">
            <Input
              label="Shift Start"
              type="time"
              value={formData.shift_start}
              onChange={(e) => setFormData({ ...formData, shift_start: e.target.value })}
              required
            />
            <Input
              label="Shift End"
              type="time"
              value={formData.shift_end}
              onChange={(e) => setFormData({ ...formData, shift_end: e.target.value })}
              required
            />
          </div>

          <Input
            label="Regular Work Hours (minutes)"
            type="number"
            value={formData.regular_work_minutes}
            onChange={(e) => setFormData({ ...formData, regular_work_minutes: parseInt(e.target.value) || 0 })}
            min={0}
            required
          />

          <div className="grid grid-cols-2 gap-4">
            <Input
              label="Late Threshold (minutes)"
              type="number"
              value={formData.late_threshold_minutes}
              onChange={(e) => setFormData({ ...formData, late_threshold_minutes: parseInt(e.target.value) || 0 })}
              min={0}
              required
            />
            <Input
              label="Early Departure Threshold (minutes)"
              type="number"
              value={formData.early_departure_threshold_minutes}
              onChange={(e) =>
                setFormData({ ...formData, early_departure_threshold_minutes: parseInt(e.target.value) || 0 })
              }
              min={0}
              required
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">Working Days</label>
            <div className="flex gap-2 flex-wrap">
              {DAYS_OF_WEEK.map((day) => (
                <button
                  key={day.value}
                  type="button"
                  onClick={() => toggleWorkingDay(day.value)}
                  className={`px-3 py-1.5 rounded-lg text-sm font-medium transition-colors ${
                    formData.working_days.includes(day.value)
                      ? 'bg-purple-100 text-purple-700 border-2 border-purple-300'
                      : 'bg-gray-100 text-gray-600 border-2 border-transparent hover:bg-gray-200'
                  }`}
                >
                  {day.label}
                </button>
              ))}
            </div>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Description (Optional)</label>
            <textarea
              value={formData.description}
              onChange={(e) => setFormData({ ...formData, description: e.target.value })}
              className="block w-full rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500"
              rows={2}
              placeholder="Brief description of this department..."
            />
          </div>

          <div className="flex gap-3 pt-4">
            <Button type="button" variant="outline" className="flex-1" onClick={() => setIsCreateModalOpen(false)}>
              Cancel
            </Button>
            <Button type="submit" variant="primary" className="flex-1" isLoading={createMutation.isPending}>
              Create Department
            </Button>
          </div>
        </form>
      </Modal>

      {/* Edit Modal */}
      <Modal isOpen={isEditModalOpen} onClose={() => setIsEditModalOpen(false)} title="Edit Department">
        <form onSubmit={handleUpdate} className="space-y-4">
          {formError && (
            <div className="p-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm">{formError}</div>
          )}

          <div className="grid grid-cols-2 gap-4">
            <Input
              label="Department Name"
              value={formData.name}
              onChange={(e) => setFormData({ ...formData, name: e.target.value })}
              required
            />
            <Input
              label="Code"
              value={formData.code}
              onChange={(e) => setFormData({ ...formData, code: e.target.value.toUpperCase() })}
              maxLength={10}
              required
            />
          </div>

          <div className="grid grid-cols-2 gap-4">
            <Input
              label="Shift Start"
              type="time"
              value={formData.shift_start}
              onChange={(e) => setFormData({ ...formData, shift_start: e.target.value })}
              required
            />
            <Input
              label="Shift End"
              type="time"
              value={formData.shift_end}
              onChange={(e) => setFormData({ ...formData, shift_end: e.target.value })}
              required
            />
          </div>

          <Input
            label="Regular Work Hours (minutes)"
            type="number"
            value={formData.regular_work_minutes}
            onChange={(e) => setFormData({ ...formData, regular_work_minutes: parseInt(e.target.value) || 0 })}
            min={0}
            required
          />

          <div className="grid grid-cols-2 gap-4">
            <Input
              label="Late Threshold (minutes)"
              type="number"
              value={formData.late_threshold_minutes}
              onChange={(e) => setFormData({ ...formData, late_threshold_minutes: parseInt(e.target.value) || 0 })}
              min={0}
              required
            />
            <Input
              label="Early Departure Threshold (minutes)"
              type="number"
              value={formData.early_departure_threshold_minutes}
              onChange={(e) =>
                setFormData({ ...formData, early_departure_threshold_minutes: parseInt(e.target.value) || 0 })
              }
              min={0}
              required
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">Working Days</label>
            <div className="flex gap-2 flex-wrap">
              {DAYS_OF_WEEK.map((day) => (
                <button
                  key={day.value}
                  type="button"
                  onClick={() => toggleWorkingDay(day.value)}
                  className={`px-3 py-1.5 rounded-lg text-sm font-medium transition-colors ${
                    formData.working_days.includes(day.value)
                      ? 'bg-purple-100 text-purple-700 border-2 border-purple-300'
                      : 'bg-gray-100 text-gray-600 border-2 border-transparent hover:bg-gray-200'
                  }`}
                >
                  {day.label}
                </button>
              ))}
            </div>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <textarea
              value={formData.description}
              onChange={(e) => setFormData({ ...formData, description: e.target.value })}
              className="block w-full rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500"
              rows={2}
            />
          </div>

          <div className="flex items-center gap-2">
            <input
              type="checkbox"
              id="is_active"
              checked={formData.is_active}
              onChange={(e) => setFormData({ ...formData, is_active: e.target.checked })}
              className="rounded border-gray-300 text-purple-600 focus:ring-purple-500"
            />
            <label htmlFor="is_active" className="text-sm font-medium text-gray-700">
              Department is active
            </label>
          </div>

          <div className="flex gap-3 pt-4">
            <Button type="button" variant="outline" className="flex-1" onClick={() => setIsEditModalOpen(false)}>
              Cancel
            </Button>
            <Button type="submit" variant="primary" className="flex-1" isLoading={updateMutation.isPending}>
              Save Changes
            </Button>
          </div>
        </form>
      </Modal>

      {/* Delete Modal */}
      <Modal isOpen={isDeleteModalOpen} onClose={() => setIsDeleteModalOpen(false)} title="Delete Department">
        <div className="space-y-4">
          {formError && (
            <div className="p-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm">{formError}</div>
          )}

          <div className="flex items-center gap-3 p-4 bg-amber-50 rounded-lg">
            <AlertCircle className="w-8 h-8 text-amber-600 flex-shrink-0" />
            <div>
              <p className="font-medium text-amber-800">Are you sure you want to delete this department?</p>
              <p className="text-sm text-amber-600 mt-1">
                <span className="font-medium">{selectedDepartment?.name}</span> will be permanently deleted.
                {(selectedDepartment?.workers_count ?? 0) > 0 && (
                  <span className="block mt-1 text-red-600">
                    This department has {selectedDepartment?.workers_count} workers assigned. Please reassign them
                    first.
                  </span>
                )}
              </p>
            </div>
          </div>

          <div className="flex gap-3 pt-2">
            <Button type="button" variant="outline" className="flex-1" onClick={() => setIsDeleteModalOpen(false)}>
              Cancel
            </Button>
            <Button
              type="button"
              variant="danger"
              className="flex-1"
              onClick={handleDelete}
              isLoading={deleteMutation.isPending}
              disabled={(selectedDepartment?.workers_count ?? 0) > 0}
            >
              Delete Department
            </Button>
          </div>
        </div>
      </Modal>
    </div>
  );
}
