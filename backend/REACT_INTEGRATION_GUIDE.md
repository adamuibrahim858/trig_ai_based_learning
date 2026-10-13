# React & TypeScript Integration Guide

This guide provides practical integration patterns, Axios configurations, TypeScript interfaces, and concrete code examples for integrating the **AI-Powered Trigonometry Learning System API** (`/api/v1`) with the React + Vite frontend.

---

## 1. Axios Client Configuration

Create an API client configured with Bearer token authentication and consistent error handling:

```typescript
// src/services/apiClient.ts
import axios, { AxiosError } from 'axios';

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000/api/v1';

export const apiClient = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  },
});

// Request interceptor: inject Sanctum Bearer token
apiClient.interceptors.request.use((config) => {
  const token = localStorage.getItem('auth_token');
  if (token && config.headers) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// Response interceptor: handle 401 Unauthenticated globally
apiClient.interceptors.response.use(
  (response) => response,
  (error: AxiosError) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('auth_token');
      // Redirect to login or trigger auth state clear
    }
    return Promise.reject(error);
  }
);
```

---

## 2. Practical Examples for Core Workflows

### Example 1: Registering a Student
```typescript
interface RegisterPayload {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
}

export async function registerStudent(payload: RegisterPayload) {
  const response = await apiClient.post('/auth/register', payload);
  const { user, token } = response.data.data;
  localStorage.setItem('auth_token', token);
  return user;
}
```

### Example 2: Logging In & Storing Token
```typescript
export async function loginStudent(email: string, password: string) {
  const response = await apiClient.post('/auth/login', { email, password });
  const { user, token } = response.data.data;
  localStorage.setItem('auth_token', token);
  return user;
}
```

### Example 3: Retrieving the Dashboard
```typescript
export async function fetchDashboard() {
  const response = await apiClient.get('/dashboard');
  // Returns: student, course, progress_summary, current_week,
  // next_recommended_lesson, weak_topics, recent_attempts, active_recommendations, certificate
  return response.data.data;
}
```

### Example 4: Listing Topics & Weekly Progression Status
```typescript
export async function fetchCourseTopics(courseId: number = 1) {
  const response = await apiClient.get(`/courses/${courseId}/topics`);
  // Each topic includes: id, week_number, title, is_accessible, status ('locked' | 'available' | 'completed')
  return response.data.data;
}
```

### Example 5: Retrieving an Authorized Lesson
```typescript
export async function fetchLesson(lessonId: number) {
  const response = await apiClient.get(`/lessons/${lessonId}`);
  const lesson = response.data.data;
  // If lesson.is_accessible is false, lesson.content is null and lesson.lock_reason explains why.
  return lesson;
}
```

### Example 6: Starting an Assessment Attempt
```typescript
export async function startAssessmentAttempt(testId: number) {
  const response = await apiClient.post(`/tests/${testId}/attempts`);
  return response.data.data; // TestAttempt object with status: 'in_progress'
}
```

### Example 7: Submitting Answers
```typescript
interface AnswerSubmission {
  question_id: number;
  selected_option_id?: number | null;
  answer_text?: string | null;
}

export async function submitAssessmentAttempt(attemptId: number, answers: AnswerSubmission[]) {
  const response = await apiClient.post(`/attempts/${attemptId}/submit`, { answers });
  // Returns: attempt, score, total_marks, percentage, passed, pass_mark
  return response.data.data;
}
```

### Example 8: Retrieving Graded Results and Reviews
```typescript
export async function fetchAttemptReview(attemptId: number) {
  const response = await apiClient.get(`/attempts/${attemptId}/review`);
  // Returns: full questions list with explanations, options, and is_correct flags
  return response.data.data;
}
```

### Example 9: Sending a Question to the AI Tutor
```typescript
export async function sendTutorMessage(conversationId: number, message: string) {
  const response = await apiClient.post(`/ai/conversations/${conversationId}/messages`, {
    message,
  });
  // Returns: { user_message, assistant_message }
  return response.data.data;
}
```

### Example 10: Retrieving and Dismissing Recommendations
```typescript
export async function fetchRecommendations() {
  const response = await apiClient.get('/recommendations');
  return response.data.data;
}

export async function dismissRecommendation(recommendationId: number) {
  await apiClient.post(`/recommendations/${recommendationId}/dismiss`);
}
```

### Example 11: Checking Final Examination Eligibility
```typescript
export async function checkFinalExamEligibility() {
  const response = await apiClient.get('/final-exam/eligibility');
  // Returns: { eligible: boolean, reason: string | null, topics_completed: number, total_topics: number }
  return response.data.data;
}
```

### Example 12: Downloading and Verifying Certificates
```typescript
// 1. Check Eligibility & Issue
export async function issueCertificate() {
  const response = await apiClient.post('/certificates');
  return response.data.data;
}

// 2. Download Certificate as PDF blob
export async function downloadCertificatePdf(certificateId: number) {
  const response = await apiClient.get(`/certificates/${certificateId}/download`, {
    responseType: 'blob',
  });
  
  const blob = new Blob([response.data], { type: 'application/pdf' });
  const downloadUrl = window.URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = downloadUrl;
  link.setAttribute('download', `Certificate-${certificateId}.pdf`);
  document.body.appendChild(link);
  link.click();
  link.remove();
}

// 3. Public Verification (No token required)
export async function verifyCertificate(certificateNumber: string) {
  const response = await apiClient.get(`/certificates/verify/${certificateNumber}`);
  // Returns: { valid: true, student_name, course_title, final_score, issued_at }
  return response.data.data;
}
```
