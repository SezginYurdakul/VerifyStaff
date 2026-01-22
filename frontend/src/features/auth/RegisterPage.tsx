import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useMutation } from '@tanstack/react-query';
import { useAuthStore } from '@/stores/authStore';
import { register } from '@/api/auth';
import { Button, Input, Card } from '@/components/ui';
import type { ApiError } from '@/types';
import type { AxiosError } from 'axios';

export default function RegisterPage() {
  const navigate = useNavigate();
  const setAuth = useAuthStore((state) => state.setAuth);

  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [error, setError] = useState('');
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});

  const registerMutation = useMutation({
    mutationFn: register,
    onSuccess: (data) => {
      setAuth(data.user, data.token);
      navigate('/');
    },
    onError: (err: AxiosError<ApiError>) => {
      const errorData = err.response?.data;
      if (errorData?.error?.details) {
        setFieldErrors(errorData.error.details);
      }
      setError(errorData?.message || 'Registration failed. Please try again.');
    },
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setError('');
    setFieldErrors({});

    if (password !== passwordConfirmation) {
      setError('Passwords do not match');
      return;
    }

    registerMutation.mutate({
      name,
      email,
      password,
      password_confirmation: passwordConfirmation,
    });
  };

  return (
    <Card>
      <h2 className="text-2xl font-bold text-gray-900 mb-6">Create Account</h2>

      <form onSubmit={handleSubmit} className="space-y-4">
        {error && (
          <div className="p-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm">
            {error}
          </div>
        )}

        <Input
          label="Full Name"
          type="text"
          name="name"
          value={name}
          onChange={(e) => setName(e.target.value)}
          placeholder="Enter your full name"
          error={fieldErrors.name?.[0]}
          required
        />

        <Input
          label="Email"
          type="email"
          name="email"
          value={email}
          onChange={(e) => setEmail(e.target.value)}
          placeholder="Enter your email"
          error={fieldErrors.email?.[0]}
          required
        />

        <Input
          label="Password"
          type="password"
          name="password"
          value={password}
          onChange={(e) => setPassword(e.target.value)}
          placeholder="Create a password"
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
          isLoading={registerMutation.isPending}
        >
          Create Account
        </Button>
      </form>

      <p className="mt-6 text-center text-sm text-gray-600">
        Already have an account?{' '}
        <Link to="/login" className="text-blue-600 hover:underline">
          Sign In
        </Link>
      </p>
    </Card>
  );
}
