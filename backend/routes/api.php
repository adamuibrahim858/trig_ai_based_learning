<?php

use App\Http\Controllers\Api\V1\AITutorController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CertificateController;
use App\Http\Controllers\Api\V1\CourseController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\FinalExamController;
use App\Http\Controllers\Api\V1\LessonController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ProgressController;
use App\Http\Controllers\Api\V1\RecommendationController;
use App\Http\Controllers\Api\V1\TestController;
use App\Http\Controllers\Api\V1\TopicController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - V1
|--------------------------------------------------------------------------
|
| All endpoints are versioned under /api/v1/
|
*/

Route::prefix('v1')->group(function () {

    // -------------------------------------------------------------
    // System Health Check
    // -------------------------------------------------------------
    Route::get('/health', function () {
        return response()->json([
            'status' => 'healthy',
            'system' => 'AI-Powered Trigonometry Learning API',
            'version' => '1.0.0',
            'timestamp' => now()->toIso8601String(),
        ]);
    });

    // -------------------------------------------------------------
    // Public Authentication & Course Discovery
    // -------------------------------------------------------------
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/register', [AuthController::class, 'register']);

    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::get('/courses', [CourseController::class, 'index']);
    Route::get('/courses/{course}', [CourseController::class, 'show']);

    // Public Certificate Verification (anyone can verify a certificate by code)
    Route::get('/certificates/verify/{certificateNumber}', [CertificateController::class, 'verify']);

    // -------------------------------------------------------------
    // Authenticated Student Endpoints (Sanctum)
    // -------------------------------------------------------------
    Route::middleware('auth:sanctum')->group(function () {

        // Session & Current User
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/logout', [AuthController::class, 'logout']);

        Route::get('/auth/user', [AuthController::class, 'user']);
        Route::get('/user', [AuthController::class, 'user']);

        // Student Profile Management
        Route::get('/profile', [ProfileController::class, 'show']);
        Route::put('/profile', [ProfileController::class, 'update']);
        Route::put('/profile/password', [ProfileController::class, 'updatePassword']);

        // Central Student Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index']);

        // Course Enrollment
        Route::post('/courses/{course}/enroll', [CourseController::class, 'enroll']);

        // Course Topics (authenticated for status tracking)
        Route::get('/courses/{course}/topics', [TopicController::class, 'index']);

        // Curriculum: Topics & Lessons
        Route::get('/topics/{topic}', [TopicController::class, 'show']);

        Route::get('/lessons', [LessonController::class, 'index']);
        Route::get('/lessons/{lesson}', [LessonController::class, 'show']);
        Route::post('/lessons/{lesson}/start', [LessonController::class, 'start']);
        Route::post('/lessons/{lesson}/complete', [LessonController::class, 'complete']);
        Route::get('/lessons/{lesson}/progress', [LessonController::class, 'progress']);

        // Assessment Engine & Test Attempts
        Route::get('/tests', [TestController::class, 'index']);
        Route::get('/tests/{test}', [TestController::class, 'show']);
        Route::post('/tests/{test}/attempts', [TestController::class, 'startAttempt']);
        Route::get('/tests/{test}/attempts', [TestController::class, 'testAttempts']);

        Route::get('/attempts/{attempt}', [TestController::class, 'showAttempt']);
        Route::post('/attempts/{attempt}/submit', [TestController::class, 'submitAttempt']);
        Route::get('/attempts/{attempt}/result', [TestController::class, 'result']);
        Route::get('/attempts/{attempt}/review', [TestController::class, 'review']);

        // Progress Tracking
        Route::get('/progress', [ProgressController::class, 'index']);
        Route::get('/progress/topics', [ProgressController::class, 'topics']);
        Route::get('/progress/lessons', [ProgressController::class, 'lessons']);

        // Personalized Learning Recommendations
        Route::get('/recommendations', [RecommendationController::class, 'index']);
        Route::post('/recommendations/{id}/dismiss', [RecommendationController::class, 'dismiss']);

        // Comprehensive Final Examination
        Route::get('/final-exam/eligibility', [FinalExamController::class, 'eligibility']);
        Route::get('/final-exam', [FinalExamController::class, 'show']);
        Route::post('/final-exam/attempts', [FinalExamController::class, 'startAttempt']);
        Route::post('/final-exam/attempts/{attemptId}/submit', [FinalExamController::class, 'submitAttempt']);

        // Course Certificates
        Route::get('/certificates/eligibility', [CertificateController::class, 'eligibility']);
        Route::get('/certificates', [CertificateController::class, 'index']);
        Route::post('/certificates', [CertificateController::class, 'issue']);
        Route::get('/certificates/{certificate}/download', [CertificateController::class, 'download']);

        // AI Tutor Services (Protected + Rate limited)
        Route::middleware('throttle:30,1')->prefix('ai')->group(function () {
            Route::get('/conversations', [AITutorController::class, 'index']);
            Route::post('/conversations', [AITutorController::class, 'store']);
            Route::get('/conversations/{id}', [AITutorController::class, 'show']);
            Route::delete('/conversations/{id}', [AITutorController::class, 'destroy']);
            Route::post('/conversations/{id}/messages', [AITutorController::class, 'sendMessage']);
            Route::post('/practice', [AITutorController::class, 'practiceQuestion']);
            Route::post('/lessons/{lessonId}/summarize', [AITutorController::class, 'summarizeLesson']);
        });
    });
});
