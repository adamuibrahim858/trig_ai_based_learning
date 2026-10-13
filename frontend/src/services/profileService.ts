import { apiClient } from './api';
import type { ApiResponse, User } from '../types/api';

export interface UpdateProfilePayload {
  name?: string;
  email?: string;
}

export interface UpdatePasswordPayload {
  current_password: string;
  password: string;
  password_confirmation: string;
}

export const profileService = {
  async getProfile(): Promise<User> {
    const res = await apiClient.get<ApiResponse<User>>('/profile');
    return res.data.data;
  },

  async updateProfile(payload: UpdateProfilePayload): Promise<User> {
    const res = await apiClient.put<ApiResponse<User>>('/profile', payload);
    return res.data.data;
  },

  async updatePassword(payload: UpdatePasswordPayload): Promise<void> {
    await apiClient.put('/profile/password', payload);
  },
};
