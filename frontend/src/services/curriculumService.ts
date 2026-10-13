import { apiClient } from './api';
import type { ApiResponse, Course, Topic, Lesson } from '../types/api';

export const curriculumService = {
  async getCourses(): Promise<Course[]> {
    const res = await apiClient.get<ApiResponse<Course[]>>('/courses');
    return res.data.data;
  },

  async getCourse(courseId: number): Promise<Course> {
    const res = await apiClient.get<ApiResponse<Course>>(`/courses/${courseId}`);
    return res.data.data;
  },

  async enrollInCourse(courseId: number): Promise<{ enrollment_id: number; course_id: number; status: string }> {
    const res = await apiClient.post<ApiResponse<{ enrollment_id: number; course_id: number; status: string }>>(
      `/courses/${courseId}/enroll`
    );
    return res.data.data;
  },

  async getCourseTopics(courseId: number = 1): Promise<Topic[]> {
    const res = await apiClient.get<ApiResponse<Topic[]>>(`/courses/${courseId}/topics`);
    return res.data.data;
  },

  async getTopic(topicId: number): Promise<Topic> {
    const res = await apiClient.get<ApiResponse<Topic>>(`/topics/${topicId}`);
    return res.data.data;
  },

  async getLessons(): Promise<Lesson[]> {
    const res = await apiClient.get<ApiResponse<Lesson[]>>('/lessons');
    return res.data.data;
  },

  async getLesson(lessonId: number): Promise<Lesson> {
    const res = await apiClient.get<ApiResponse<Lesson>>(`/lessons/${lessonId}`);
    return res.data.data;
  },

  async startLesson(lessonId: number): Promise<{ lesson_id: number; status: string; started_at: string }> {
    const res = await apiClient.post<ApiResponse<{ lesson_id: number; status: string; started_at: string }>>(
      `/lessons/${lessonId}/start`
    );
    return res.data.data;
  },

  async completeLesson(lessonId: number): Promise<{ lesson_id: number; status: string; completed_at: string }> {
    const res = await apiClient.post<ApiResponse<{ lesson_id: number; status: string; completed_at: string }>>(
      `/lessons/${lessonId}/complete`
    );
    return res.data.data;
  },

  async getLessonProgress(lessonId: number): Promise<{
    lesson_id: number;
    status: string;
    is_accessible: boolean;
    lock_reason?: string | null;
    started_at?: string | null;
    completed_at?: string | null;
  }> {
    const res = await apiClient.get<
      ApiResponse<{
        lesson_id: number;
        status: string;
        is_accessible: boolean;
        lock_reason?: string | null;
        started_at?: string | null;
        completed_at?: string | null;
      }>
    >(`/lessons/${lessonId}/progress`);
    return res.data.data;
  },
};
