import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
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
      setError(err.response?.data?.message || 'Login failed. Please try again.');
    },
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setError('');
    loginMutation.mutate({ identifier, password });
  };

  return (
    <Card>
      <h2 className="text-2xl font-bold text-gray-900 mb-6">Sign In</h2>

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

      <p className="mt-6 text-center text-sm text-gray-600">
        Don't have an account?{' '}
        <Link to="/register" className="text-blue-600 hover:underline">
          Register
        </Link>
      </p>
    </Card>
  );
}
