import axios, { AxiosError } from 'axios';

const BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000/api/v1';

export const apiClient = axios.create({
  baseURL: BASE_URL,
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  },
  timeout: 30000,
});

// Attach Bearer token from localStorage
apiClient.interceptors.request.use((config) => {
  const token = localStorage.getItem('trig_token');
  if (token && config.headers) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// Global response interceptor
apiClient.interceptors.response.use(
  (response) => response,
  (error: AxiosError<{ message?: string; errors?: Record<string, string[]> }>) => {
    if (error.response?.status === 401) {
      // Clear token if unauthorized, except on login/register endpoints
      const url = error.config?.url || '';
      if (!url.includes('/auth/login') && !url.includes('/auth/register')) {
        localStorage.removeItem('trig_token');
        localStorage.removeItem('trig_user');
        // Do not force full reload if already on auth page
        if (!window.location.pathname.startsWith('/login') && !window.location.pathname.startsWith('/register')) {
          window.dispatchEvent(new Event('auth:unauthorized'));
        }
      }
    }
    return Promise.reject(error);
  }
);

export function getErrorMessage(error: unknown): string {
  if (axios.isAxiosError(error)) {
    const data = error.response?.data;

    // Prioritize specific validation errors if present
    if (data && typeof data === 'object' && 'errors' in data && data.errors) {
      const errors = data.errors as Record<string, string[]>;
      const firstKey = Object.keys(errors)[0];
      if (firstKey && errors[firstKey]?.length) {
        return errors[firstKey][0];
      }
    }

    if (data && typeof data === 'object' && 'message' in data && data.message) {
      return String(data.message);
    }
    if (error.response?.status === 403) {
      return 'You are not authorized to perform this action.';
    }
    if (error.response?.status === 404) {
      return 'The requested resource was not found.';
    }
    if (error.response?.status === 429) {
      return 'Too many requests. Please slow down and try again.';
    }
    if (error.response?.status && error.response.status >= 500) {
      return 'Server error. Please try again later.';
    }
    if (error.code === 'ECONNABORTED' || error.message.includes('timeout')) {
      return 'Request timed out. Please check your internet connection.';
    }
    if (!error.response) {
      return 'Network error: Unable to reach the backend server. Is Laravel running on port 8000?';
    }
  }
  if (error instanceof Error) {
    return error.message;
  }
  return 'An unexpected error occurred. Please try again.';
}
