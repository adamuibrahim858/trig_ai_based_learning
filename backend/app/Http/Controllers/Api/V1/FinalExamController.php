<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\SubmitAttemptRequest;
use App\Http\Resources\TestAttemptResource;
use App\Http\Resources\TestResource;
use App\Models\Course;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Services\Assessment\AssessmentService;
use App\Services\Learning\LessonProgressionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinalExamController extends ApiController
{
    public function __construct(
        protected LessonProgressionService $progressionService,
        protected AssessmentService $assessmentService
    ) {}

    /**
     * Helper to get course.
     */
    protected function getCourse(Request $request): Course
    {
        if ($courseId = $request->query('course_id')) {
            return Course::findOrFail($courseId);
        }

        return Course::firstOrFail();
    }

    /**
     * GET /api/v1/final-exam/eligibility
     * Check if student meets prerequisites (all 8 weeks completed) to unlock the final exam.
     */
    public function eligibility(Request $request): JsonResponse
    {
        $course = $this->getCourse($request);
        $eligibility = $this->progressionService->checkFinalExamEligibility($request->user(), $course);

        return $this->success($eligibility);
    }

    /**
     * GET /api/v1/final-exam
     * Retrieve the final exam details and questions (correct answers masked).
     */
    public function show(Request $request): JsonResponse
    {
        $course = $this->getCourse($request);
        $test = Test::where('course_id', $course->id)
            ->where('type', 'final_exam')
            ->firstOrFail();

        $access = $this->assessmentService->canAccessTest($request->user(), $test);
        if (!$access['allowed']) {
            return $this->error($access['reason'], null, 403);
        }

        $test->load('questions.options');

        $bestAttempt = TestAttempt::where('user_id', $request->user()->id)
            ->where('test_id', $test->id)
            ->where('status', 'submitted')
            ->orderByDesc('percentage')
            ->first();

        return $this->success([
            'test' => new TestResource($test),
            'best_score' => $bestAttempt ? [
                'percentage' => $bestAttempt->percentage,
                'passed' => (bool) $bestAttempt->passed,
                'submitted_at' => $bestAttempt->submitted_at?->toIso8601String(),
            ] : null,
        ]);
    }

    /**
     * POST /api/v1/final-exam/attempts
     * Start a final exam attempt.
     */
    public function startAttempt(Request $request): JsonResponse
    {
        $course = $this->getCourse($request);
        $test = Test::where('course_id', $course->id)
            ->where('type', 'final_exam')
            ->firstOrFail();

        $attempt = $this->assessmentService->startAttempt($request->user(), $test);

        return $this->success(
            new TestAttemptResource($attempt),
            'Final exam attempt started.',
            201
        );
    }

    /**
     * POST /api/v1/final-exam/attempts/{attemptId}/submit
     * Submit answers for final exam attempt.
     */
    public function submitAttempt(SubmitAttemptRequest $request, int $attemptId): JsonResponse
    {
        $attempt = TestAttempt::where('user_id', $request->user()->id)
            ->findOrFail($attemptId);

        $graded = $this->assessmentService->submitAttempt($request->user(), $attempt, $request->input('answers', []));

        return $this->success([
            'attempt' => new TestAttemptResource($graded),
            'review' => $this->assessmentService->getReviewData($request->user(), $graded),
        ], 'Final exam attempt submitted and evaluated successfully.');
    }
}
