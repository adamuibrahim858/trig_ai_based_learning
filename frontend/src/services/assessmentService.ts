import { apiClient } from './api';
import type {
  ApiResponse,
  Test,
  TestAttempt,
  SubmitAnswerPayload,
  ReviewData,
  Question,
} from '../types/api';

export const assessmentService = {
  async getTests(): Promise<Test[]> {
    const res = await apiClient.get<ApiResponse<{ tests: Test[] }>>('/tests');
    return res.data.data.tests;
  },

  async getTest(testId: number): Promise<Test> {
    const res = await apiClient.get<ApiResponse<Test>>(`/tests/${testId}`);
    return res.data.data;
  },

  async startAttempt(testId: number): Promise<TestAttempt> {
    const res = await apiClient.post<ApiResponse<TestAttempt>>(`/tests/${testId}/attempts`);
    return res.data.data;
  },

  async getAttempt(attemptId: number): Promise<{ attempt: TestAttempt; questions: Question[] }> {
    const res = await apiClient.get<ApiResponse<{ attempt: TestAttempt; questions: Question[] }>>(
      `/attempts/${attemptId}`
    );
    return res.data.data;
  },

  async submitAttempt(
    attemptId: number,
    answers: SubmitAnswerPayload[]
  ): Promise<{
    attempt: TestAttempt;
    score: number;
    total_marks: number;
    percentage: number;
    passed: boolean;
    pass_mark: number;
  }> {
    const res = await apiClient.post<
      ApiResponse<{
        attempt: TestAttempt;
        score: number;
        total_marks: number;
        percentage: number;
        passed: boolean;
        pass_mark: number;
      }>
    >(`/attempts/${attemptId}/submit`, { answers });
    return res.data.data;
  },

  async getAttemptResult(attemptId: number): Promise<TestAttempt> {
    const res = await apiClient.get<ApiResponse<TestAttempt>>(`/attempts/${attemptId}/result`);
    return res.data.data;
  },

  async getAttemptReview(attemptId: number): Promise<ReviewData> {
    const res = await apiClient.get<ApiResponse<ReviewData>>(`/attempts/${attemptId}/review`);
    return res.data.data;
  },

  async getTestAttempts(testId: number): Promise<TestAttempt[]> {
    const res = await apiClient.get<ApiResponse<TestAttempt[]>>(`/tests/${testId}/attempts`);
    return res.data.data;
  },
};
