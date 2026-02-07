import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useMutation } from '@tanstack/react-query';
import { useAuthStore } from '@/stores/authStore';
import { login } from '@/api/auth';
import { Button, Input, Card } from '@/components/ui';
import type { ApiError } from '@/types';
import type { AxiosError } from 'axios';

export default function LoginPage() {
  const navigate = useNavigate();
  const setAuth = useAuthStore((state) => state.setAuth);

  const [identifier, setIdentifier] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');

  const loginMutation = useMutation({
    mutationFn: login,
    onSuccess: (data) => {
      setAuth(data.user, data.token);
      navigate('/');
    },
    onError: (err: AxiosError<ApiError>) => {
      // Network error (no response) - server unreachable
      if (!err.response) {
        setError('Unable to connect to server. Please check your internet connection.');
        return;
      }
      // Server error (5xx) - backend/database issue
      if (err.response.status >= 500) {
        setError('Server error. Please try again later.');
        return;
      }
      // Client error (4xx) - authentication failed
      setError(err.response.data?.message || 'Invalid credentials. Please try again.');
    },
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setError('');
    loginMutation.mutate({ identifier, password });
  };

  return (
    <Card>
      <div className="flex flex-col items-center mb-6">
        <img src="/logo.svg" alt="VerifyStaff" className="h-16 w-auto mb-4" />
        <h2 className="text-2xl font-bold text-brand-900">Sign In</h2>
      </div>

      <form onSubmit={handleSubmit} className="space-y-4">
        {error && (
          <div className="p-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm">
            {error}
          </div>
        )}

        <Input
          label="Email, Phone, or Employee ID"
          type="text"
          name="identifier"
          value={identifier}
          onChange={(e) => setIdentifier(e.target.value)}
          placeholder="Enter your email, phone, or employee ID"
          required
        />

        <Input
          label="Password"
          type="password"
          name="password"
          value={password}
          onChange={(e) => setPassword(e.target.value)}
          placeholder="Enter your password"
          required
        />

        <Button
          type="submit"
          variant="primary"
          size="lg"
          className="w-full"
          isLoading={loginMutation.isPending}
        >
          Sign In
        </Button>
      </form>

      <p className="mt-6 text-center text-sm text-gray-500">
        Contact your administrator if you need an account.
      </p>
    </Card>
  );
}
