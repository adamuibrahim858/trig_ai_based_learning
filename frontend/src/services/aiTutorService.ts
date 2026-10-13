import { apiClient } from './api';
import type { ApiResponse, AiConversation, AiMessage } from '../types/api';

export const aiTutorService = {
  async getConversations(): Promise<{ data: AiConversation[] } | AiConversation[]> {
    const res = await apiClient.get<ApiResponse<any>>('/ai/conversations');
    // If paginated, returns { data: [...] }
    if (res.data.data?.data) {
      return res.data.data.data;
    }
    return res.data.data;
  },

  async createConversation(payload: { lesson_id?: number | null; title?: string | null }): Promise<AiConversation> {
    const res = await apiClient.post<ApiResponse<AiConversation>>('/ai/conversations', payload);
    return res.data.data;
  },

  async getConversation(conversationId: number): Promise<AiConversation> {
    const res = await apiClient.get<ApiResponse<AiConversation>>(`/ai/conversations/${conversationId}`);
    return res.data.data;
  },

  async deleteConversation(conversationId: number): Promise<void> {
    await apiClient.delete(`/ai/conversations/${conversationId}`);
  },

  async sendMessage(
    conversationId: number,
    message: string
  ): Promise<{ user_message: AiMessage; assistant_message: AiMessage }> {
    const res = await apiClient.post<ApiResponse<{ user_message: AiMessage; assistant_message: AiMessage }>>(
      `/ai/conversations/${conversationId}/messages`,
      { message }
    );
    return res.data.data;
  },

  async generatePracticeQuestion(topicId: number, difficulty: 'easy' | 'medium' | 'hard' = 'medium'): Promise<{
    topic_id: number;
    topic_title: string;
    difficulty: string;
    content: string;
  }> {
    const res = await apiClient.post<
      ApiResponse<{
        topic_id: number;
        topic_title: string;
        difficulty: string;
        content: string;
      }>
    >('/ai/practice', { topic_id: topicId, difficulty });
    return res.data.data;
  },

  async summarizeLesson(lessonId: number): Promise<{
    lesson_id: number;
    lesson_title: string;
    summary: string;
  }> {
    const res = await apiClient.post<
      ApiResponse<{
        lesson_id: number;
        lesson_title: string;
        summary: string;
      }>
    >(`/ai/lessons/${lessonId}/summarize`);
    return res.data.data;
  },
};
