import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { getUsers, createUser, updateUser, deleteUser, resendInvite, restoreUser, forceDeleteUser } from '@/api/users';
import { getDepartments } from '@/api/departments';
import type { CreateUserRequest, UpdateUserRequest } from '@/api/users';
import type { User, UserRole, UserStatus, ApiError } from '@/types';
import { Button, Input, Card, Modal } from '@/components/ui';
import {
  Users,
  Plus,
  Mail,
  Pencil,
  Trash2,
  RefreshCw,
  RotateCcw,
  Archive,
  Shield,
  UserCheck,
  Briefcase,
  AlertCircle,
  QrCode,
  Printer,
  Download,
} from 'lucide-react';
import { QRCodeSVG } from 'qrcode.react';
import { useRef } from 'react';
import type { AxiosError } from 'axios';

const ROLES: { value: UserRole; label: string; icon: typeof Shield }[] = [
  { value: 'admin', label: 'Admin', icon: Shield },
  { value: 'representative', label: 'Representative', icon: UserCheck },
  { value: 'worker', label: 'Worker', icon: Briefcase },
];

const STATUSES: { value: UserStatus; label: string; color: string }[] = [
  { value: 'active', label: 'Active', color: 'bg-green-100 text-green-800' },
  { value: 'inactive', label: 'Inactive', color: 'bg-gray-100 text-gray-800' },
  { value: 'suspended', label: 'Suspended', color: 'bg-red-100 text-red-800' },
];

function StatusBadge({ status }: { status: UserStatus }) {
  const statusConfig = STATUSES.find((s) => s.value === status);
  return (
    <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${statusConfig?.color}`}>
      {statusConfig?.label}
    </span>
  );
}

function RoleBadge({ role }: { role: UserRole }) {
  const roleConfig = ROLES.find((r) => r.value === role);
  const Icon = roleConfig?.icon || Briefcase;
  const colors = {
    admin: 'bg-purple-100 text-purple-800',
    representative: 'bg-blue-100 text-blue-800',
    worker: 'bg-gray-100 text-gray-800',
  };
  return (
    <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium ${colors[role]}`}>
      <Icon className="w-3 h-3" />
      {roleConfig?.label}
    </span>
  );
}

interface UserFormData {
  name: string;
  email: string;
  phone: string;
  employee_id: string;
  department_id: number | null;
  role: UserRole;
  status: UserStatus;
}

const emptyFormData: UserFormData = {
  name: '',
  email: '',
  phone: '',
  employee_id: '',
  department_id: null,
  role: 'worker',
  status: 'active',
};

export default function UsersPage() {
  const queryClient = useQueryClient();
  const [page, setPage] = useState(1);
  const [roleFilter, setRoleFilter] = useState<UserRole | ''>('');
  const [statusFilter, setStatusFilter] = useState<UserStatus | ''>('');
  const [departmentFilter, setDepartmentFilter] = useState<number | ''>('');
  const [showTrashed, setShowTrashed] = useState(false);

  // Modal states
  const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
  const [isEditModalOpen, setIsEditModalOpen] = useState(false);
  const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
  const [isForceDeleteModalOpen, setIsForceDeleteModalOpen] = useState(false);
  const [isQRModalOpen, setIsQRModalOpen] = useState(false);
  const [selectedUser, setSelectedUser] = useState<User | null>(null);
  const [formData, setFormData] = useState<UserFormData>(emptyFormData);
  const [formError, setFormError] = useState('');
  const printRef = useRef<HTMLDivElement>(null);

  // Fetch users
  const { data, isLoading, error } = useQuery({
    queryKey: ['users', page, roleFilter, statusFilter, departmentFilter, showTrashed],
    queryFn: () =>
      getUsers({
        page,
        per_page: 20,
        role: roleFilter || undefined,
        status: showTrashed ? undefined : statusFilter || undefined,
        department_id: departmentFilter || undefined,
        trashed: showTrashed || undefined,
      }),
  });

  // Fetch departments for the dropdown
  const { data: departmentsData } = useQuery({
    queryKey: ['departments'],
    queryFn: () => getDepartments(),
  });

  // Create user mutation
  const createMutation = useMutation({
    mutationFn: createUser,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['users'] });
      setIsCreateModalOpen(false);
      setFormData(emptyFormData);
      setFormError('');
    },
    onError: (err: AxiosError<ApiError>) => {
      setFormError(err.response?.data?.message || 'Failed to create user');
    },
  });

  // Update user mutation
  const updateMutation = useMutation({
    mutationFn: ({ id, data }: { id: number; data: UpdateUserRequest }) => updateUser(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['users'] });
      setIsEditModalOpen(false);
      setSelectedUser(null);
      setFormData(emptyFormData);
      setFormError('');
    },
    onError: (err: AxiosError<ApiError>) => {
      setFormError(err.response?.data?.message || 'Failed to update user');
    },
  });

  // Delete user mutation
  const deleteMutation = useMutation({
    mutationFn: deleteUser,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['users'] });
      setIsDeleteModalOpen(false);
      setSelectedUser(null);
    },
  });

  // Resend invite mutation
  const resendMutation = useMutation({
    mutationFn: resendInvite,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['users'] });
    },
  });

  // Restore user mutation
  const restoreMutation = useMutation({
    mutationFn: restoreUser,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['users'] });
    },
  });

  // Force delete user mutation
  const forceDeleteMutation = useMutation({
    mutationFn: forceDeleteUser,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['users'] });
      setIsForceDeleteModalOpen(false);
      setSelectedUser(null);
    },
  });

  const handleOpenCreate = () => {
    setFormData(emptyFormData);
    setFormError('');
    setIsCreateModalOpen(true);
  };

  const handleOpenEdit = (user: User) => {
    setSelectedUser(user);
    setFormData({
      name: user.name,
      email: user.email,
      phone: user.phone || '',
      employee_id: user.employee_id || '',
      department_id: user.department_id,
      role: user.role,
      status: user.status,
    });
    setFormError('');
    setIsEditModalOpen(true);
  };

  const handleOpenDelete = (user: User) => {
    setSelectedUser(user);
    setIsDeleteModalOpen(true);
  };

  const handleCreate = (e: React.FormEvent) => {
    e.preventDefault();
    const createData: CreateUserRequest = {
      name: formData.name,
      email: formData.email,
      role: formData.role,
    };
    if (formData.phone) createData.phone = formData.phone;
    if (formData.employee_id) createData.employee_id = formData.employee_id;
    if (formData.department_id) createData.department_id = formData.department_id;
    createMutation.mutate(createData);
  };

  const handleUpdate = (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedUser) return;
    const updateData: UpdateUserRequest = {
      name: formData.name,
      email: formData.email,
      role: formData.role,
      status: formData.status,
      department_id: formData.department_id,
    };
    // Only include phone and employee_id if they have values
    if (formData.phone) updateData.phone = formData.phone;
    else updateData.phone = null;
    if (formData.employee_id) updateData.employee_id = formData.employee_id;
    else updateData.employee_id = null;
    updateMutation.mutate({
      id: selectedUser.id,
      data: updateData,
    });
  };

  const handleDelete = () => {
    if (!selectedUser) return;
    deleteMutation.mutate(selectedUser.id);
  };

  const handleResendInvite = (user: User) => {
    resendMutation.mutate(user.id);
  };

  const handleRestore = (user: User) => {
    restoreMutation.mutate(user.id);
  };

  const handleOpenForceDelete = (user: User) => {
    setSelectedUser(user);
    setIsForceDeleteModalOpen(true);
  };

  const handleForceDelete = () => {
    if (!selectedUser) return;
    forceDeleteMutation.mutate(selectedUser.id);
  };

  const handleOpenQR = (user: User) => {
    setSelectedUser(user);
    setIsQRModalOpen(true);
  };

  const getInviteUrl = (token: string) => {
    const baseUrl = window.location.origin;
    return `${baseUrl}/set-password?token=${token}`;
  };

  const handlePrint = () => {
    if (!printRef.current) return;
    const printContent = printRef.current.innerHTML;
    const printWindow = window.open('', '_blank');
    if (!printWindow) return;

    printWindow.document.write(`
      <!DOCTYPE html>
      <html>
        <head>
          <title>VerifyStaff - Invite QR Code</title>
          <style>
            body {
              font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
              display: flex;
              justify-content: center;
              align-items: center;
              min-height: 100vh;
              margin: 0;
              padding: 20px;
              box-sizing: border-box;
            }
            .print-card {
              border: 2px solid #e5e7eb;
              border-radius: 16px;
              padding: 32px;
              text-align: center;
              max-width: 400px;
            }
            .logo {
              font-size: 24px;
              font-weight: bold;
              color: #2563eb;
              margin-bottom: 8px;
            }
            .title {
              font-size: 14px;
              color: #6b7280;
              margin-bottom: 24px;
            }
            .qr-container {
              background: white;
              padding: 16px;
              border-radius: 12px;
              display: inline-block;
              margin-bottom: 24px;
            }
            .user-name {
              font-size: 20px;
              font-weight: 600;
              color: #111827;
              margin-bottom: 4px;
            }
            .user-role {
              font-size: 14px;
              color: #6b7280;
              margin-bottom: 16px;
            }
            .instructions {
              font-size: 12px;
              color: #9ca3af;
              line-height: 1.5;
            }
            .step {
              margin-bottom: 4px;
            }
            @media print {
              body { padding: 0; }
              .print-card { border: 1px solid #d1d5db; }
            }
          </style>
        </head>
        <body>
          ${printContent}
        </body>
      </html>
    `);
    printWindow.document.close();
    printWindow.focus();
    printWindow.print();
  };

  const handleDownloadQR = () => {
    if (!selectedUser?.invite_token) return;
    const svg = document.getElementById('invite-qr-code');
    if (!svg) return;

    const svgData = new XMLSerializer().serializeToString(svg);
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');
    const img = new Image();

    img.onload = () => {
      canvas.width = img.width;
      canvas.height = img.height;
      ctx?.drawImage(img, 0, 0);
      const pngUrl = canvas.toDataURL('image/png');
      const downloadLink = document.createElement('a');
      downloadLink.href = pngUrl;
      downloadLink.download = `verifystaff-invite-${selectedUser.name.replace(/\s+/g, '-').toLowerCase()}.png`;
      document.body.appendChild(downloadLink);
      downloadLink.click();
      document.body.removeChild(downloadLink);
    };

    img.src = 'data:image/svg+xml;base64,' + btoa(unescape(encodeURIComponent(svgData)));
  };

  if (error) {
    return (
      <Card>
        <div className="text-center py-8 text-red-600">
          <AlertCircle className="w-12 h-12 mx-auto mb-4" />
          <p>Failed to load users</p>
        </div>
      </Card>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3">
          <div className="p-2 bg-blue-100 rounded-lg">
            <Users className="w-6 h-6 text-blue-600" />
          </div>
          <div>
            <h1 className="text-2xl font-bold text-gray-900">Users</h1>
            <p className="text-sm text-gray-500">Manage system users and invitations</p>
          </div>
        </div>
        <Button variant="primary" onClick={handleOpenCreate}>
          <Plus className="w-4 h-4 mr-2" />
          Add User
        </Button>
      </div>

      {/* Filters */}
      <Card>
        <div className="flex flex-wrap items-end gap-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Role</label>
            <select
              value={roleFilter}
              onChange={(e) => {
                setRoleFilter(e.target.value as UserRole | '');
                setPage(1);
              }}
              className="block w-40 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
            >
              <option value="">All Roles</option>
              {ROLES.map((role) => (
                <option key={role.value} value={role.value}>
                  {role.label}
                </option>
              ))}
            </select>
          </div>
          {!showTrashed && (
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Status</label>
              <select
                value={statusFilter}
                onChange={(e) => {
                  setStatusFilter(e.target.value as UserStatus | '');
                  setPage(1);
                }}
                className="block w-40 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
              >
                <option value="">All Statuses</option>
                {STATUSES.map((status) => (
                  <option key={status.value} value={status.value}>
                    {status.label}
                  </option>
                ))}
              </select>
            </div>
          )}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Department</label>
            <select
              value={departmentFilter}
              onChange={(e) => {
                setDepartmentFilter(e.target.value ? parseInt(e.target.value) : '');
                setPage(1);
              }}
              className="block w-44 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
            >
              <option value="">All Departments</option>
              {departmentsData?.departments.map((dept) => (
                <option key={dept.id} value={dept.id}>
                  {dept.name}
                </option>
              ))}
            </select>
          </div>
          <div className="ml-auto">
            <button
              onClick={() => {
                setShowTrashed(!showTrashed);
                setPage(1);
              }}
              className={`flex items-center gap-2 px-4 py-2 rounded-lg border transition-colors ${
                showTrashed
                  ? 'bg-red-50 border-red-200 text-red-700'
                  : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'
              }`}
            >
              <Archive className="w-4 h-4" />
              {showTrashed ? 'Showing Deleted' : 'Show Deleted'}
            </button>
          </div>
        </div>
      </Card>

      {/* Users Table */}
      <Card>
        {isLoading ? (
          <div className="flex items-center justify-center py-12">
            <RefreshCw className="w-8 h-8 text-blue-500 animate-spin" />
          </div>
        ) : (
          <>
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead>
                  <tr className="border-b border-gray-200">
                    <th className="text-left py-3 px-4 text-sm font-medium text-gray-500">User</th>
                    <th className="text-left py-3 px-4 text-sm font-medium text-gray-500">Role</th>
                    <th className="text-left py-3 px-4 text-sm font-medium text-gray-500">Department</th>
                    <th className="text-left py-3 px-4 text-sm font-medium text-gray-500">Status</th>
                    <th className="text-left py-3 px-4 text-sm font-medium text-gray-500">Employee ID</th>
                    <th className="text-right py-3 px-4 text-sm font-medium text-gray-500">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {data?.users.map((user) => (
                    <tr
                      key={user.id}
                      className={`border-b border-gray-100 ${
                        showTrashed ? 'bg-red-50/50' : 'hover:bg-gray-50'
                      }`}
                    >
                      <td className="py-3 px-4">
                        <div>
                          <p className={`font-medium ${showTrashed ? 'text-gray-500' : 'text-gray-900'}`}>
                            {user.name}
                          </p>
                          <p className="text-sm text-gray-500">{user.email}</p>
                          {user.phone && <p className="text-sm text-gray-400">{user.phone}</p>}
                        </div>
                      </td>
                      <td className="py-3 px-4">
                        <RoleBadge role={user.role} />
                      </td>
                      <td className="py-3 px-4 text-sm text-gray-600">
                        {user.department ? (
                          <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                            {user.department.name}
                          </span>
                        ) : (
                          '-'
                        )}
                      </td>
                      <td className="py-3 px-4">
                        {showTrashed ? (
                          <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                            Deleted
                          </span>
                        ) : (
                          <StatusBadge status={user.status} />
                        )}
                      </td>
                      <td className="py-3 px-4 text-sm text-gray-600">{user.employee_id || '-'}</td>
                      <td className="py-3 px-4">
                        {showTrashed ? (
                          <div className="flex items-center justify-end gap-2">
                            <button
                              onClick={() => handleRestore(user)}
                              disabled={restoreMutation.isPending}
                              className="p-1.5 text-gray-400 hover:text-green-600 hover:bg-green-50 rounded-lg transition-colors"
                              title="Restore User"
                            >
                              <RotateCcw className="w-4 h-4" />
                            </button>
                            <button
                              onClick={() => handleOpenForceDelete(user)}
                              className="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                              title="Permanently Delete"
                            >
                              <Trash2 className="w-4 h-4" />
                            </button>
                          </div>
                        ) : (
                          <div className="flex items-center justify-end gap-2">
                            {!user.invite_accepted_at && user.invite_token && (
                              <button
                                onClick={() => handleOpenQR(user)}
                                className="p-1.5 text-gray-400 hover:text-green-600 hover:bg-green-50 rounded-lg transition-colors"
                                title="Print Invite QR"
                              >
                                <QrCode className="w-4 h-4" />
                              </button>
                            )}
                            {!user.invite_accepted_at && (
                              <button
                                onClick={() => handleResendInvite(user)}
                                disabled={resendMutation.isPending}
                                className="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                                title="Resend Invitation"
                              >
                                <Mail className="w-4 h-4" />
                              </button>
                            )}
                            <button
                              onClick={() => handleOpenEdit(user)}
                              className="p-1.5 text-gray-400 hover:text-amber-600 hover:bg-amber-50 rounded-lg transition-colors"
                              title="Edit User"
                            >
                              <Pencil className="w-4 h-4" />
                            </button>
                            <button
                              onClick={() => handleOpenDelete(user)}
                              className="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                              title="Delete User"
                            >
                              <Trash2 className="w-4 h-4" />
                            </button>
                          </div>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            {/* Pagination */}
            {data && data.last_page > 1 && (
              <div className="flex items-center justify-between pt-4 border-t border-gray-200 mt-4">
                <p className="text-sm text-gray-500">
                  Showing {(page - 1) * data.per_page + 1} to{' '}
                  {Math.min(page * data.per_page, data.total)} of {data.total} users
                </p>
                <div className="flex gap-2">
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => setPage((p) => Math.max(1, p - 1))}
                    disabled={page === 1}
                  >
                    Previous
                  </Button>
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => setPage((p) => Math.min(data.last_page, p + 1))}
                    disabled={page === data.last_page}
                  >
                    Next
                  </Button>
                </div>
              </div>
            )}

            {data?.users.length === 0 && (
              <div className="text-center py-12 text-gray-500">
                <Users className="w-12 h-12 mx-auto mb-4 opacity-50" />
                <p>No users found</p>
              </div>
            )}
          </>
        )}
      </Card>

      {/* Create Modal */}
      <Modal isOpen={isCreateModalOpen} onClose={() => setIsCreateModalOpen(false)} title="Add New User">
        <form onSubmit={handleCreate} className="space-y-4">
          {formError && (
            <div className="p-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm">
              {formError}
            </div>
          )}

          <Input
            label="Full Name"
            value={formData.name}
            onChange={(e) => setFormData({ ...formData, name: e.target.value })}
            required
          />

          <Input
            label="Email"
            type="email"
            value={formData.email}
            onChange={(e) => setFormData({ ...formData, email: e.target.value })}
            required
          />

          <Input
            label="Phone (Optional)"
            value={formData.phone}
            onChange={(e) => setFormData({ ...formData, phone: e.target.value })}
          />

          <Input
            label="Employee ID (Optional)"
            value={formData.employee_id}
            onChange={(e) => setFormData({ ...formData, employee_id: e.target.value })}
          />

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Role</label>
            <select
              value={formData.role}
              onChange={(e) => setFormData({ ...formData, role: e.target.value as UserRole })}
              className="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
            >
              {ROLES.map((role) => (
                <option key={role.value} value={role.value}>
                  {role.label}
                </option>
              ))}
            </select>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Department (Optional)</label>
            <select
              value={formData.department_id || ''}
              onChange={(e) => setFormData({ ...formData, department_id: e.target.value ? parseInt(e.target.value) : null })}
              className="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
            >
              <option value="">No Department</option>
              {departmentsData?.departments.map((dept) => (
                <option key={dept.id} value={dept.id}>
                  {dept.name} ({dept.code})
                </option>
              ))}
            </select>
          </div>

          <div className="flex gap-3 pt-4">
            <Button type="button" variant="outline" className="flex-1" onClick={() => setIsCreateModalOpen(false)}>
              Cancel
            </Button>
            <Button type="submit" variant="primary" className="flex-1" isLoading={createMutation.isPending}>
              Create & Send Invite
            </Button>
          </div>
        </form>
      </Modal>

      {/* Edit Modal */}
      <Modal isOpen={isEditModalOpen} onClose={() => setIsEditModalOpen(false)} title="Edit User">
        <form onSubmit={handleUpdate} className="space-y-4">
          {formError && (
            <div className="p-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm">
              {formError}
            </div>
          )}

          <Input
            label="Full Name"
            value={formData.name}
            onChange={(e) => setFormData({ ...formData, name: e.target.value })}
            required
          />

          <Input
            label="Email"
            type="email"
            value={formData.email}
            onChange={(e) => setFormData({ ...formData, email: e.target.value })}
            required
          />

          <Input
            label="Phone"
            value={formData.phone}
            onChange={(e) => setFormData({ ...formData, phone: e.target.value })}
          />

          <Input
            label="Employee ID"
            value={formData.employee_id}
            onChange={(e) => setFormData({ ...formData, employee_id: e.target.value })}
          />

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Role</label>
            <select
              value={formData.role}
              onChange={(e) => setFormData({ ...formData, role: e.target.value as UserRole })}
              className="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
            >
              {ROLES.map((role) => (
                <option key={role.value} value={role.value}>
                  {role.label}
                </option>
              ))}
            </select>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select
              value={formData.status}
              onChange={(e) => setFormData({ ...formData, status: e.target.value as UserStatus })}
              className="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
            >
              {STATUSES.map((status) => (
                <option key={status.value} value={status.value}>
                  {status.label}
                </option>
              ))}
            </select>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Department</label>
            <select
              value={formData.department_id || ''}
              onChange={(e) => setFormData({ ...formData, department_id: e.target.value ? parseInt(e.target.value) : null })}
              className="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
            >
              <option value="">No Department</option>
              {departmentsData?.departments.map((dept) => (
                <option key={dept.id} value={dept.id}>
                  {dept.name} ({dept.code})
                </option>
              ))}
            </select>
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

      {/* Delete Modal (Soft Delete) */}
      <Modal isOpen={isDeleteModalOpen} onClose={() => setIsDeleteModalOpen(false)} title="Delete User">
        <div className="space-y-4">
          <div className="flex items-center gap-3 p-4 bg-amber-50 rounded-lg">
            <AlertCircle className="w-8 h-8 text-amber-600 flex-shrink-0" />
            <div>
              <p className="font-medium text-amber-800">Are you sure you want to delete this user?</p>
              <p className="text-sm text-amber-600 mt-1">
                <span className="font-medium">{selectedUser?.name}</span> will be moved to trash.
                Their attendance records will be preserved and you can restore them later.
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
            >
              Delete User
            </Button>
          </div>
        </div>
      </Modal>

      {/* Force Delete Modal (Permanent) */}
      <Modal isOpen={isForceDeleteModalOpen} onClose={() => setIsForceDeleteModalOpen(false)} title="Permanently Delete User">
        <div className="space-y-4">
          <div className="flex items-center gap-3 p-4 bg-red-50 rounded-lg">
            <AlertCircle className="w-8 h-8 text-red-600 flex-shrink-0" />
            <div>
              <p className="font-medium text-red-800">This action cannot be undone!</p>
              <p className="text-sm text-red-600 mt-1">
                <span className="font-medium">{selectedUser?.name}</span> and all their attendance records
                will be permanently deleted from the system.
              </p>
            </div>
          </div>

          <div className="flex gap-3 pt-2">
            <Button type="button" variant="outline" className="flex-1" onClick={() => setIsForceDeleteModalOpen(false)}>
              Cancel
            </Button>
            <Button
              type="button"
              variant="danger"
              className="flex-1"
              onClick={handleForceDelete}
              isLoading={forceDeleteMutation.isPending}
            >
              Permanently Delete
            </Button>
          </div>
        </div>
      </Modal>

      {/* QR Code Modal */}
      <Modal isOpen={isQRModalOpen} onClose={() => setIsQRModalOpen(false)} title="Invite QR Code">
        <div className="space-y-4">
          {selectedUser?.invite_token && (
            <>
              {/* Printable Content (hidden, used only for print) */}
              <div ref={printRef} className="hidden">
                <div className="print-card">
                  <div className="logo">VerifyStaff</div>
                  <div className="title">Scan to join the team</div>
                  <div className="qr-container">
                    <QRCodeSVG
                      value={getInviteUrl(selectedUser.invite_token)}
                      size={200}
                      level="H"
                      marginSize={2}
                    />
                  </div>
                  <div className="user-name">{selectedUser.name}</div>
                  <div className="user-role">
                    {ROLES.find((r) => r.value === selectedUser.role)?.label}
                  </div>
                  <div className="instructions">
                    <div className="step">1. Scan this QR code with your phone</div>
                    <div className="step">2. Set your password</div>
                    <div className="step">3. Start using VerifyStaff</div>
                  </div>
                </div>
              </div>

              {/* Preview Card */}
              <div className="border-2 border-gray-200 rounded-xl p-6 text-center bg-gray-50">
                <p className="text-xl font-bold text-blue-600 mb-1">VerifyStaff</p>
                <p className="text-sm text-gray-500 mb-4">Scan to join the team</p>
                <div className="bg-white p-4 rounded-lg inline-block shadow-sm">
                  <QRCodeSVG
                    id="invite-qr-code"
                    value={getInviteUrl(selectedUser.invite_token)}
                    size={180}
                    level="H"
                    marginSize={2}
                  />
                </div>
                <p className="text-lg font-semibold text-gray-900 mt-4">{selectedUser.name}</p>
                <p className="text-sm text-gray-500">
                  {ROLES.find((r) => r.value === selectedUser.role)?.label}
                </p>
                <div className="text-xs text-gray-400 mt-4 space-y-1">
                  <p>1. Scan this QR code with your phone</p>
                  <p>2. Set your password</p>
                  <p>3. Start using VerifyStaff</p>
                </div>
              </div>

              {/* Action Buttons */}
              <div className="flex gap-3">
                <Button
                  type="button"
                  variant="outline"
                  className="flex-1"
                  onClick={handlePrint}
                >
                  <Printer className="w-4 h-4 mr-2" />
                  Print
                </Button>
                <Button
                  type="button"
                  variant="outline"
                  className="flex-1"
                  onClick={handleDownloadQR}
                >
                  <Download className="w-4 h-4 mr-2" />
                  Download QR
                </Button>
              </div>

              {/* Link Display */}
              <div className="p-3 bg-gray-100 rounded-lg">
                <p className="text-xs text-gray-500 mb-1">Invite Link:</p>
                <p className="text-xs text-gray-700 break-all font-mono">
                  {getInviteUrl(selectedUser.invite_token)}
                </p>
              </div>
            </>
          )}
        </div>
      </Modal>
    </div>
  );
}
