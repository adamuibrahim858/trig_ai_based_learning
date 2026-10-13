import { apiClient } from './api';
import type { ApiResponse, AuthResponse, User } from '../types/api';

export const authService = {
  async register(payload: {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
  }): Promise<AuthResponse> {
    const res = await apiClient.post<ApiResponse<AuthResponse>>('/auth/register', payload);
    return res.data.data;
  },

  async login(payload: { email: string; password: string }): Promise<AuthResponse> {
    const res = await apiClient.post<ApiResponse<AuthResponse>>('/auth/login', payload);
    return res.data.data;
  },

  async logout(): Promise<void> {
    await apiClient.post('/auth/logout');
  },

  async getCurrentUser(): Promise<User> {
    const res = await apiClient.get<ApiResponse<User>>('/auth/user');
    return res.data.data;
  },

  async updateProfile(payload: { name?: string; email?: string }): Promise<User> {
    const res = await apiClient.put<ApiResponse<User>>('/profile', payload);
    return res.data.data;
  },

  async updatePassword(payload: {
    current_password: string;
    password: string;
    password_confirmation: string;
  }): Promise<void> {
    await apiClient.put('/profile/password', payload);
  },
};
