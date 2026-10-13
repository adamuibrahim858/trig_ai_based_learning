import { apiClient } from './api';
import type { ApiResponse } from '../types/api';

export interface OverallProgressResponse {
  overall: {
    total_lessons: number;
    completed_lessons: number;
    completion_percentage: number;
  };
  lessons: {
    lesson_id: number;
    lesson_title: string;
    status: string;
    started_at?: string;
    completed_at?: string;
  }[];
  topics: {
    topic_id: number;
    topic_title: string;
    week_number: number;
    status: string;
    completed_at?: string;
  }[];
  recent_attempts: {
    id: number;
    test_title: string;
    test_type: string;
    percentage: number;
    passed: boolean;
    submitted_at: string;
  }[];
}

export const progressService = {
  async getOverallProgress(): Promise<OverallProgressResponse> {
    const res = await apiClient.get<ApiResponse<OverallProgressResponse>>('/progress');
    return res.data.data;
  },

  async getTopicsProgress(): Promise<{
    topic_id: number;
    topic_title: string;
    week_number: number;
    status: string;
    completed_at?: string;
  }[]> {
    const res = await apiClient.get<ApiResponse<{
      topic_id: number;
      topic_title: string;
      week_number: number;
      status: string;
      completed_at?: string;
    }[]>>('/progress/topics');
    return res.data.data;
  },

  async getLessonsProgress(): Promise<{
    lesson_id: number;
    lesson_title: string;
    status: string;
    started_at?: string;
    completed_at?: string;
  }[]> {
    const res = await apiClient.get<ApiResponse<{
      lesson_id: number;
      lesson_title: string;
      status: string;
      started_at?: string;
      completed_at?: string;
    }[]>>('/progress/lessons');
    return res.data.data;
  },
};
