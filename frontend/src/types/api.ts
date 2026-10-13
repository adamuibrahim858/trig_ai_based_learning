// Common API Response Wrapper
export interface ApiResponse<T> {
  success: boolean;
  message?: string;
  data: T;
  errors?: Record<string, string[]>;
}

// User & Authentication
export interface User {
  id: number;
  name: string;
  email: string;
  role: 'student' | 'admin';
  created_at?: string;
}

export interface AuthResponse {
  user: User;
  token: string;
}

// Course & Curriculum
export interface Course {
  id: number;
  title: string;
  slug: string;
  description: string;
  learning_objectives?: string[];
  status: string;
}

export interface LessonResource {
  id: number;
  type: string;
  title: string;
  description?: string;
  content_url: string;
}

export interface Lesson {
  id: number;
  topic_id: number;
  title: string;
  slug: string;
  summary: string;
  content?: string | null;
  learning_objectives?: string[];
  estimated_minutes?: number;
  order: number;
  status: 'locked' | 'available' | 'in_progress' | 'completed';
  is_accessible: boolean;
  lock_reason?: string | null;
  resources?: LessonResource[];
  topic?: {
    id: number;
    title: string;
    week_number: number;
  };
}

export interface Topic {
  id: number;
  course_id: number;
  week_number: number;
  title: string;
  slug: string;
  description: string;
  learning_objectives?: string[];
  order: number;
  status: 'locked' | 'available' | 'in_progress' | 'completed';
  is_accessible: boolean;
  lock_reason?: string | null;
  assessment_id?: number | null;
  lessons?: Lesson[];
}

// Assessments & Tests
export interface QuestionOption {
  id: number;
  option_text: string;
  is_correct?: boolean; // Only present in review mode
}

export interface Question {
  id: number;
  question_text: string;
  question_type: 'multiple_choice' | 'true_false' | 'numerical';
  marks: number;
  explanation?: string;
  options: QuestionOption[];
}

export interface Test {
  id: number;
  course_id?: number;
  topic_id?: number;
  lesson_id?: number;
  title: string;
  description: string;
  type: 'topic_assessment' | 'lesson_practice' | 'final_exam';
  pass_mark: number;
  total_marks: number;
  duration_minutes?: number;
  attempts_allowed?: number;
  is_published: boolean;
  questions?: Question[];
}

export interface TestAttempt {
  id: number;
  test_id: number;
  user_id: number;
  attempt_number: number;
  status: 'in_progress' | 'submitted' | 'abandoned';
  score: number;
  total_marks: number;
  percentage: number;
  passed: boolean;
  started_at: string;
  submitted_at?: string | null;
  test?: {
    id: number;
    title: string;
    type: string;
    pass_mark: number;
    duration_minutes?: number;
  };
}

export interface SubmitAnswerPayload {
  question_id: number;
  selected_option_id?: number | null;
  answer_text?: string | null;
}

export interface ReviewQuestionItem {
  question_id: number;
  question_text: string;
  question_type: string;
  marks: number;
  marks_awarded: number;
  is_correct: boolean;
  selected_option_id?: number | null;
  answer_text?: string | null;
  explanation?: string;
  options: {
    id: number;
    option_text: string;
    is_correct: boolean;
  }[];
}

export interface ReviewData {
  attempt_id: number;
  test_id: number;
  test_title: string;
  score: number;
  total_marks: number;
  percentage: number;
  pass_mark: number;
  passed: boolean;
  submitted_at?: string;
  questions: ReviewQuestionItem[];
}

// Learning Recommendations
export interface LearningRecommendation {
  id: number;
  recommendation_type: 'revision' | 'remedial' | 'practice' | 'enrichment';
  reason: string;
  status: 'active' | 'dismissed';
  topic?: {
    id: number;
    title: string;
    week_number: number;
  } | null;
  lesson?: {
    id: number;
    title: string;
    slug: string;
  } | null;
  created_at?: string;
}

// AI Tutor
export interface AiMessage {
  id: number;
  role: 'user' | 'assistant';
  message: string;
  created_at?: string;
}

export interface AiConversation {
  id: number;
  lesson_id?: number | null;
  lesson_title?: string | null;
  title: string;
  messages_count?: number;
  messages?: AiMessage[];
  created_at?: string;
  updated_at?: string;
}

// Certificates
export interface Certificate {
  id: number;
  certificate_number: string;
  course_id: number;
  course_title?: string;
  final_score: number;
  issued_at: string;
  verify_url?: string;
  download_url?: string;
}

export interface CertificateEligibility {
  eligible: boolean;
  reason?: string | null;
  final_score?: number | null;
}

export interface PublicVerificationResult {
  valid: boolean;
  certificate_number: string;
  student_name: string;
  course_title: string;
  final_score: number;
  issued_at: string;
}

// Dashboard
export interface DashboardData {
  student: User;
  course: {
    id: number;
    title: string;
    slug: string;
    is_enrolled: boolean;
  } | null;
  progress_summary: {
    total_topics: number;
    completed_topics: number;
    total_lessons: number;
    completed_lessons: number;
    locked_lessons: number;
    current_week: number;
    completion_percentage: number;
  };
  current_week: number;
  next_recommended_lesson: {
    id: number;
    title: string;
    slug: string;
    week_number: number;
    topic_title: string;
    estimated_duration_minutes?: number;
  } | null;
  weak_topics: {
    topic_id: number;
    week_number: number;
    topic_title: string;
    latest_percentage: number;
    pass_mark: number;
  }[];
  recent_attempts: {
    id: number;
    test_title: string;
    test_type: string;
    score: number;
    total_points: number;
    percentage: number;
    passed: boolean;
    submitted_at: string;
  }[];
  active_recommendations: {
    id: number;
    type: string;
    reason: string;
    topic?: { id: number; title: string; week_number: number } | null;
    lesson?: { id: number; title: string; slug: string } | null;
  }[];
  final_exam_eligibility?: {
    eligible: boolean;
    reason?: string | null;
    topics_completed?: number;
    total_topics?: number;
  } | null;
  certificate?: {
    status: 'issued' | 'eligible' | 'ineligible';
    certificate_number?: string;
    final_score?: number;
    issued_at?: string;
    reason?: string | null;
  } | null;
}
