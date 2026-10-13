# AI-Powered Trigonometry Learning System — REST API Backend

A production-ready, security-hardened **Laravel REST API** for an adaptive, AI-driven Trigonometry learning platform. The system delivers an **8-week structured curriculum**, enforces a strict **server-side lesson progression engine**, provides automated **70% pass-mark assessment grading**, delivers **Google Gemini AI tutoring**, generates **personalized revision recommendations**, and issues **verifiable PDF certificates**.

---

## Table of Contents
1. [Key Features](#key-features)
2. [Technology Stack](#technology-stack)
3. [System Architecture](#system-architecture)
4. [Quickstart & Installation](#quickstart--installation)
5. [Demo Credentials](#demo-credentials)
6. [Curriculum Structure (8 Weeks)](#curriculum-structure-8-weeks)
7. [Progression Engine & Business Rules](#progression-engine--business-rules)
8. [AI Tutor (Google Gemini Integration)](#ai-tutor-google-gemini-integration)
9. [API Endpoint Reference](#api-endpoint-reference)
10. [Automated Testing](#automated-testing)
11. [Postman Collection](#postman-collection)

---

## Key Features

- **Token-Based Authentication (Laravel Sanctum)**: Stateless Bearer token authentication designed specifically for separate SPA/mobile frontends (e.g. React + TypeScript).
- **Strict Role-Based Access & Mass-Assignment Protection**: Role elevation is blocked on registration and profile update endpoints.
- **8-Week Structured Curriculum Engine**: 1 Comprehensive Course, 8 Weekly Topics, 16 In-depth Lessons with LaTeX/Markdown content, 8 Topic Assessments, and 1 Comprehensive Final Exam (96 Questions, 338 Options).
- **Server-Side Progression & Unlocking**: Students cannot bypass prerequisites; lesson content for locked weeks is automatically masked on the server.
- **Automated Grading Engine (70% Pass Rule)**: Question answers and `is_correct` flags are never exposed to clients prior to submission. Grading occurs strictly on the server with detailed per-question reviews upon submission.
- **Personalized Learning Recommendations**: Failure triggers rule-based concept diagnostic analysis, mapping incorrect questions to exact lesson review modules and practice sets.
- **Context-Aware AI Tutor (Google Gemini)**: Real-time mathematical guidance, step-by-step scaffolding, custom practice question generation, and lesson cheat-sheet summarization with graceful keyless fallbacks.
- **Verifiable PDF Certificates**: Automated eligibility verification, unique certificate generation (`TRIG-XXXX-YYYY`), styled PDF rendering via DomPDF, and public tokenless verification.

---

## Technology Stack

- **Framework**: Laravel 13.x (PHP 8.3+)
- **Database**: SQLite (default zero-configuration) or MySQL / PostgreSQL
- **Authentication**: Laravel Sanctum (Personal Access Tokens)
- **PDF Generation**: `barryvdh/laravel-dompdf` (DomPDF 3.x)
- **AI Engine**: Google Gemini API (`gemini-1.5-flash`)
- **Testing**: PHPUnit 11 with SQLite in-memory database

---

## System Architecture

```
                    ┌─────────────────────────┐
                    │  React Frontend Client  │
                    └────────────┬────────────┘
                                 │ HTTP (Bearer Token)
                                 ▼
┌────────────────────────────────────────────────────────────────────────┐
│                        Laravel API (/api/v1)                           │
│                                                                        │
│  ┌──────────────────┐  ┌──────────────────┐  ┌──────────────────────┐  │
│  │  AuthController  │  │ DashboardCntrlr  │  │  ProgressController  │  │
│  └──────────────────┘  └──────────────────┘  └──────────────────────┘  │
│  ┌──────────────────┐  ┌──────────────────┐  ┌──────────────────────┐  │
│  │ LessonProgression│  │ AssessmentService│  │PersonalizationService│  │
│  └────────┬─────────┘  └────────┬─────────┘  └──────────┬───────────┘  │
│           │                     │                       │              │
│  ┌────────▼─────────────────────▼───────────────────────▼───────────┐  │
│  │                    AITutorService & Gemini                       │  │
│  └──────────────────────────────────┬───────────────────────────────┘  │
└─────────────────────────────────────┼──────────────────────────────────┘
                                      │
                         ┌────────────▼────────────┐
                         │    Google Gemini API    │
                         └─────────────────────────┘
```

---

## Quickstart & Installation

### 1. Prerequisites
- PHP >= 8.2 with `php-sqlite3`, `php-mbstring`, `php-xml`, `php-curl`
- Composer 2.x

### 2. Clone and Setup Environment
```bash
cd backend

# Install dependencies
composer install

# Create .env from template
cp .env.example .env

# Generate application key
php artisan key:generate
```

### 3. Configure Gemini AI (Optional for live AI calls)
Edit `.env`:
```env
GEMINI_API_KEY=your_actual_gemini_api_key_here
GEMINI_MODEL=gemini-1.5-flash
```
> *Note: If `GEMINI_API_KEY` is not provided, the AI Tutor gracefully falls back to structured educational responses rather than throwing 500 errors.*

### 4. Database Setup & Seeding
```bash
# Run all 17 migrations and populate full 8-week curriculum + demo users
php artisan migrate:fresh --seed
```

### 5. Start the Development Server
```bash
php artisan serve
# Server runs at http://127.0.0.1:8000
```

---

## Demo Credentials

The database seeder pre-populates the following accounts:

| Role | Email | Password | Permissions |
|---|---|---|---|
| **Student** | `student@example.com` | `password123` | Full student curriculum access, assessment attempts, AI tutor |
| **Admin** | `admin@example.com` | `admin12345` | Administrative reporting & certificate verification |

---

## Curriculum Structure (8 Weeks)

The platform provides a complete high-school/college introductory Trigonometry curriculum:

- **Week 1: Foundations of Trigonometry**
  - Lesson 1.1: Right-Angled Triangles & the Pythagorean Theorem
  - Lesson 1.2: The Core Ratios: Sine, Cosine, and Tangent
  - *Assessment*: Week 1 Topic Test (10 Questions, Pass mark: 70%)
- **Week 2: Reciprocal Ratios & Special Angles**
  - Lesson 2.1: Cosecant, Secant, and Cotangent
  - Lesson 2.2: Special Angle Ratios ($30^\circ$, $45^\circ$, $60^\circ$)
  - *Assessment*: Week 2 Topic Test (10 Questions)
- **Week 3: The Unit Circle & Radians**
  - Lesson 3.1: Radians and Arc Length
  - Lesson 3.2: The Unit Circle & Coordinate Definitions
  - *Assessment*: Week 3 Topic Test (10 Questions)
- **Week 4: Trigonometric Functions & Graphs**
  - Lesson 4.1: Graphing Sine and Cosine Waves
  - Lesson 4.2: Graphing Tangent and Transformations
  - *Assessment*: Week 4 Topic Test (10 Questions)
- **Week 5: Trigonometric Identities**
  - Lesson 5.1: Pythagorean & Quotient Identities
  - Lesson 5.2: Angle Addition and Double-Angle Formulas
  - *Assessment*: Week 5 Topic Test (10 Questions)
- **Week 6: Solving Trigonometric Equations**
  - Lesson 6.1: Linear Trigonometric Equations
  - Lesson 6.2: Quadratic-Type Trigonometric Equations
  - *Assessment*: Week 6 Topic Test (10 Questions)
- **Week 7: The Laws of Sines and Cosines**
  - Lesson 7.1: The Law of Sines & The Ambiguous Case
  - Lesson 7.2: The Law of Cosines & Area of Triangles
  - *Assessment*: Week 7 Topic Test (10 Questions)
- **Week 8: Applications & Advanced Problem Solving**
  - Lesson 8.1: Angles of Elevation, Depression & Navigation
  - Lesson 8.2: Real-World Modeling & Vectors
  - *Assessment*: Week 8 Topic Test (10 Questions)
- **Comprehensive Final Examination**: 16 Multi-Topic Questions covering all 8 weeks (Pass mark: 70%).

---

## Progression Engine & Business Rules

1. **Course Enrollment**: Enrolling in the course unlocks **Week 1** immediately.
2. **Sequential Unlocking**: Week $N$ ($N > 1$) is **strictly locked** until Week $N-1$ is completed.
3. **Topic Completion Rule**: A topic is completed only when:
   - All lessons in that topic are marked `completed`.
   - The topic's assessment is passed with a score **$\ge 70\%$**.
4. **Final Exam Eligibility**:
   - The student must be actively enrolled.
   - All 8 topics must be completed.
5. **Certificate Issuance**:
   - Final Examination must be passed with a score **$\ge 70\%$**.
   - Generates a certificate code formatted as `TRIG-XXXX-YYYY`.
   - Downloadable as a styled landscape PDF.

---

## AI Tutor (Google Gemini Integration)

All AI requests are proxied securely through the Laravel backend. Features:

- **Mathematical Scaffolding**: Instructed to provide pedagogical step-by-step guidance rather than raw answers.
- **Test Integrity Protection**: The prompt instruction forbids the AI from solving active test/exam questions.
- **Lesson Context Injection**: When chatting inside a lesson, the tutor automatically receives the lesson's title, summary, and content excerpt.
- **Custom Practice Questions**: Generates fresh problems with hints and derivations for any unlocked topic (`POST /api/v1/ai/practice`).
- **Lesson Summaries**: Compiles high-yield revision cheat-sheets (`POST /api/v1/ai/lessons/{id}/summarize`).

---

## API Endpoint Reference

All endpoints are versioned under `/api/v1/`.

### Public Endpoints
| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/health` | API health check and version metadata |
| `POST` | `/auth/register` | Register a new student account (`name`, `email`, `password`) |
| `POST` | `/auth/login` | Authenticate and retrieve Sanctum Bearer token |
| `GET` | `/courses` | List published courses |
| `GET` | `/courses/{course}` | Get course overview and syllabus |
| `GET` | `/courses/{course}/topics` | List topics with completion rules |
| `GET` | `/certificates/verify/{code}` | Public verification of certificate authenticity |

### Authenticated Endpoints (`Authorization: Bearer <token>`)

#### Student Session & Profile
| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/auth/logout` | Revoke current access token |
| `GET` | `/auth/user` | Get current authenticated user details |
| `GET` | `/profile` | Get student profile |
| `PUT` | `/profile` | Update profile information (`name`, `email`) |
| `PUT` | `/profile/password` | Update account password |

#### Central Student Dashboard
| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/dashboard` | Aggregate dashboard (progress, next lesson, attempts, recommendations, certificate) |

#### Course & Lesson Progression
| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/courses/{course}/enroll` | Enroll in course |
| `GET` | `/topics/{topic}` | Get topic details and lesson list |
| `GET` | `/lessons` | List lessons |
| `GET` | `/lessons/{lesson}` | Get lesson content (content masked if locked) |
| `POST` | `/lessons/{lesson}/start` | Mark lesson in-progress |
| `POST` | `/lessons/{lesson}/complete` | Mark lesson completed & check topic unlock |
| `GET` | `/lessons/{lesson}/progress` | Get user's progress on specific lesson |

#### Assessments & Tests
| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/tests` | List published tests |
| `GET` | `/tests/{test}` | Get test questions (answers masked) |
| `POST` | `/tests/{test}/attempts` | Start an attempt |
| `GET` | `/tests/{test}/attempts` | List user's attempts on this test |
| `GET` | `/attempts/{attempt}` | Get active attempt state |
| `POST` | `/attempts/{attempt}/submit` | Submit answers, grade attempt, evaluate unlocks |
| `GET` | `/attempts/{attempt}/result` | Get score and pass/fail summary |
| `GET` | `/attempts/{attempt}/review` | Get detailed review with explanations |

#### Progress Tracking & Recommendations
| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/progress` | Comprehensive student learning progress |
| `GET` | `/progress/topics` | Status of all 8 weekly topics |
| `GET` | `/progress/lessons` | Status of individual lessons |
| `GET` | `/recommendations` | Active diagnostic recommendations |
| `POST` | `/recommendations/{id}/dismiss` | Dismiss a recommendation |

#### Final Exam & Certificates
| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/final-exam/eligibility` | Check prerequisites (all 8 weeks completed) |
| `GET` | `/final-exam` | Get final exam details |
| `POST` | `/final-exam/attempts` | Start final exam attempt |
| `POST` | `/final-exam/attempts/{id}/submit`| Submit final exam attempt |
| `GET` | `/certificates/eligibility` | Check if student passed final exam with $\ge 70\%$ |
| `POST` | `/certificates` | Issue course completion certificate |
| `GET` | `/certificates` | List issued certificates |
| `GET` | `/certificates/{id}/download` | Download official PDF certificate |

#### AI Tutor (Rate limited: 30 req/min)
| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/ai/conversations` | List AI tutoring conversations |
| `POST` | `/ai/conversations` | Start new conversation (`lesson_id`, `title`) |
| `GET` | `/ai/conversations/{id}` | Get conversation history |
| `DELETE`| `/ai/conversations/{id}` | Delete conversation |
| `POST` | `/ai/conversations/{id}/messages`| Send query and receive tutor response |
| `POST` | `/ai/practice` | Generate custom practice question (`topic_id`, `difficulty`) |
| `POST` | `/ai/lessons/{id}/summarize` | Generate bite-sized study cheat-sheet |

---

## Automated Testing

The backend includes a comprehensive automated test suite (27 Feature tests, 138 assertions) testing the complete lifecycle:

- Authentication, token issuance, logout, and mass-assignment protection
- Course enrollment and 8-week sequential progression rules
- Content masking for locked lessons and 403 authorization guards
- Server-side assessment grading, 70% threshold, and topic completion triggers
- Diagnostic recommendation engine
- AI Tutor conversation persistence with Gemini mocking
- Final exam eligibility, certificate issuance, PDF rendering, and public verification

### Running the Tests
```bash
php artisan test
```

Expected output:
```
PASS  Tests\Feature\ExampleTest
PASS  Tests\Feature\AuthTest
PASS  Tests\Feature\CurriculumAndProgressionTest
PASS  Tests\Feature\AssessmentEngineTest
PASS  Tests\Feature\AITutorTest
PASS  Tests\Feature\CertificateTest
PASS  Tests\Feature\DashboardAndRecommendationsTest

Tests:    27 passed (138 assertions)
Duration: 8.20s
```

---

## Postman Collection

A complete Postman Collection is located at:
```
backend/trigonometry_learning_api.postman_collection.json
```

### How to Import & Use:
1. Open **Postman** -> Click **Import** -> Select `trigonometry_learning_api.postman_collection.json`.
2. The collection defines two variables:
   - `baseUrl`: default `http://127.0.0.1:8000/api/v1`
   - `token`: automatically updated upon executing **Register** or **Login** via Postman test script.
3. Execute any request in the collection directly!
