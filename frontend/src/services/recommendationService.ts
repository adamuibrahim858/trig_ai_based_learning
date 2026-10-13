import { apiClient } from './api';
import type { ApiResponse, LearningRecommendation } from '../types/api';

export const recommendationService = {
  async getRecommendations(): Promise<LearningRecommendation[]> {
    const res = await apiClient.get<ApiResponse<LearningRecommendation[]>>('/recommendations');
    return res.data.data;
  },

  async dismissRecommendation(recommendationId: number): Promise<void> {
    await apiClient.post(`/recommendations/${recommendationId}/dismiss`);
  },
};
