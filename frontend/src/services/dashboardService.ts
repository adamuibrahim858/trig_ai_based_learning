import { apiClient } from './api';
import type { ApiResponse, DashboardData } from '../types/api';

export const dashboardService = {
  async getDashboard(): Promise<DashboardData> {
    const res = await apiClient.get<ApiResponse<DashboardData>>('/dashboard');
    return res.data.data;
  },
};
