import { apiClient } from './api';
import type {
  ApiResponse,
  Test,
  TestAttempt,
  SubmitAnswerPayload,
  ReviewData,
} from '../types/api';

export interface FinalExamEligibility {
  eligible: boolean;
  reason: string | null;
  topics_completed: number;
  total_topics: number;
}

export interface FinalExamData {
  test: Test;
  best_score: {
    percentage: number;
    passed: boolean;
    submitted_at?: string;
  } | null;
}

export interface FinalExamSubmissionResult {
  attempt: TestAttempt;
  review: ReviewData;
}

export const finalExamService = {
  async getEligibility(courseId?: number): Promise<FinalExamEligibility> {
    const params = courseId ? { course_id: courseId } : {};
    const res = await apiClient.get<ApiResponse<FinalExamEligibility>>('/final-exam/eligibility', { params });
    return res.data.data;
  },

  async getFinalExam(courseId?: number): Promise<FinalExamData> {
    const params = courseId ? { course_id: courseId } : {};
    const res = await apiClient.get<ApiResponse<FinalExamData>>('/final-exam', { params });
    return res.data.data;
  },

  async startAttempt(courseId?: number): Promise<TestAttempt> {
    const payload = courseId ? { course_id: courseId } : {};
    const res = await apiClient.post<ApiResponse<TestAttempt>>('/final-exam/attempts', payload);
    return res.data.data;
  },

  async submitAttempt(attemptId: number, answers: SubmitAnswerPayload[]): Promise<FinalExamSubmissionResult> {
    const res = await apiClient.post<ApiResponse<FinalExamSubmissionResult>>(
      `/final-exam/attempts/${attemptId}/submit`,
      { answers }
    );
    return res.data.data;
  },
};
