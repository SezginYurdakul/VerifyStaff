import { useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { useMutation, useQuery } from '@tanstack/react-query';
import { useAuthStore } from '@/stores/authStore';
import { validateInvite, acceptInvite } from '@/api/auth';
import { Button, Input, Card } from '@/components/ui';
import type { ApiError } from '@/types';
import type { AxiosError } from 'axios';
import { CheckCircle, XCircle, Loader2 } from 'lucide-react';

export default function SetPasswordPage() {
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const token = searchParams.get('token');
  const setAuth = useAuthStore((state) => state.setAuth);

  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [error, setError] = useState('');
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});

  // Validate token on mount
  const {
    data: validationData,
    isLoading: isValidating,
    error: validationError,
  } = useQuery({
    queryKey: ['invite-validate', token],
    queryFn: () => validateInvite(token!),
    enabled: !!token,
    retry: false,
  });

  const acceptMutation = useMutation({
    mutationFn: acceptInvite,
    onSuccess: (data) => {
      setAuth(data.user, data.token);
      navigate('/');
    },
    onError: (err: AxiosError<ApiError>) => {
      const errorData = err.response?.data;
      if (errorData?.error?.details) {
        setFieldErrors(errorData.error.details);
      }
      setError(errorData?.message || 'Failed to set password. Please try again.');
    },
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setError('');
    setFieldErrors({});

    if (password.length < 8) {
      setError('Password must be at least 8 characters');
      return;
    }

    if (password !== passwordConfirmation) {
      setError('Passwords do not match');
      return;
    }

    acceptMutation.mutate({
      token: token!,
      password,
      password_confirmation: passwordConfirmation,
    });
  };

  // No token provided
  if (!token) {
    return (
      <Card>
        <div className="text-center py-8">
          <XCircle className="w-16 h-16 text-red-500 mx-auto mb-4" />
          <h2 className="text-2xl font-bold text-gray-900 mb-2">Invalid Link</h2>
          <p className="text-gray-600 mb-6">
            No invitation token found. Please check your email for the correct link.
          </p>
          <Link to="/login">
            <Button variant="primary">Go to Login</Button>
          </Link>
        </div>
      </Card>
    );
  }

  // Validating token
  if (isValidating) {
    return (
      <Card>
        <div className="text-center py-8">
          <Loader2 className="w-16 h-16 text-blue-500 mx-auto mb-4 animate-spin" />
          <h2 className="text-2xl font-bold text-gray-900 mb-2">Validating Invitation</h2>
          <p className="text-gray-600">Please wait while we verify your invitation...</p>
        </div>
      </Card>
    );
  }

  // Invalid or expired token
  if (validationError || (validationData && !validationData.valid)) {
    return (
      <Card>
        <div className="text-center py-8">
          <XCircle className="w-16 h-16 text-red-500 mx-auto mb-4" />
          <h2 className="text-2xl font-bold text-gray-900 mb-2">Invalid or Expired Link</h2>
          <p className="text-gray-600 mb-6">
            This invitation link is invalid or has expired. Please contact your administrator for a new invitation.
          </p>
          <Link to="/login">
            <Button variant="primary">Go to Login</Button>
          </Link>
        </div>
      </Card>
    );
  }

  // Valid token - show password form
  return (
    <Card>
      <div className="text-center mb-6">
        <CheckCircle className="w-12 h-12 text-green-500 mx-auto mb-3" />
        <h2 className="text-2xl font-bold text-gray-900">Set Your Password</h2>
        <p className="text-gray-600 mt-2">
          Welcome, <span className="font-medium">{validationData?.user?.name}</span>!
        </p>
        <p className="text-sm text-gray-500">{validationData?.user?.email}</p>
      </div>

      <form onSubmit={handleSubmit} className="space-y-4">
        {error && (
          <div className="p-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm">
            {error}
          </div>
        )}

        <Input
          label="Password"
          type="password"
          name="password"
          value={password}
          onChange={(e) => setPassword(e.target.value)}
          placeholder="Create a password (min. 8 characters)"
          error={fieldErrors.password?.[0]}
          required
        />

        <Input
          label="Confirm Password"
          type="password"
          name="password_confirmation"
          value={passwordConfirmation}
          onChange={(e) => setPasswordConfirmation(e.target.value)}
          placeholder="Confirm your password"
          required
        />

        <Button
          type="submit"
          variant="primary"
          size="lg"
          className="w-full"
          isLoading={acceptMutation.isPending}
        >
          Set Password & Login
        </Button>
      </form>

      <p className="mt-6 text-center text-sm text-gray-600">
        Already have a password?{' '}
        <Link to="/login" className="text-blue-600 hover:underline">
          Sign In
        </Link>
      </p>
    </Card>
  );
}
