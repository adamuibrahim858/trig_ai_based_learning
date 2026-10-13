<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\SubmitAttemptRequest;
use App\Http\Resources\QuestionResource;
use App\Http\Resources\TestAttemptResource;
use App\Http\Resources\TestResource;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Services\Assessment\AssessmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TestController extends ApiController
{
    public function __construct(
        protected AssessmentService $assessmentService
    ) {}

    /**
     * GET /api/v1/tests
     */
    public function index(Request $request): JsonResponse
    {
        $tests = Test::where('is_published', true)
            ->orderBy('topic_id')
            ->paginate(20);

        return $this->success([
            'tests' => TestResource::collection($tests->items()),
            'pagination' => [
                'current_page' => $tests->currentPage(),
                'last_page' => $tests->lastPage(),
                'per_page' => $tests->perPage(),
                'total' => $tests->total(),
            ],
        ]);
    }

    /**
     * GET /api/v1/tests/{test}
     * Returns test with questions (answers hidden via QuestionResource).
     */
    public function show(Request $request, Test $test): JsonResponse
    {
        $user = $request->user();
        $access = $this->assessmentService->canAccessTest($user, $test);

        if (!$access['allowed']) {
            return $this->error($access['reason'], null, 403);
        }

        $test->load('questions.options');

        return $this->success(new TestResource($test));
    }

    /**
     * POST /api/v1/tests/{test}/attempts — Start a new attempt.
     */
    public function startAttempt(Request $request, Test $test): JsonResponse
    {
        $user = $request->user();
        $attempt = $this->assessmentService->startAttempt($user, $test);

        return $this->success(new TestAttemptResource($attempt), 'Test attempt started.', 201);
    }

    /**
     * GET /api/v1/attempts/{attempt} — Get an in-progress attempt's questions.
     */
    public function showAttempt(Request $request, TestAttempt $attempt): JsonResponse
    {
        $user = $request->user();
        if ($attempt->user_id !== $user->id) {
            return $this->error('Unauthorized attempt access.', null, 403);
        }

        $attempt->load('test.questions.options');

        return $this->success([
            'attempt' => new TestAttemptResource($attempt),
            'questions' => QuestionResource::collection($attempt->test->questions),
        ]);
    }

    /**
     * POST /api/v1/attempts/{attempt}/submit — Submit answers for grading.
     */
    public function submitAttempt(SubmitAttemptRequest $request, TestAttempt $attempt): JsonResponse
    {
        $user = $request->user();
        $gradedAttempt = $this->assessmentService->submitAttempt($user, $attempt, $request->answers);

        return $this->success([
            'attempt' => new TestAttemptResource($gradedAttempt),
            'score' => $gradedAttempt->score,
            'total_marks' => $gradedAttempt->total_marks,
            'percentage' => $gradedAttempt->percentage,
            'passed' => (bool) $gradedAttempt->passed,
            'pass_mark' => $gradedAttempt->test->pass_mark,
        ], $gradedAttempt->passed ? 'Congratulations! You passed.' : 'You did not pass. Review your mistakes and try again.');
    }

    /**
     * GET /api/v1/attempts/{attempt}/result — Get graded result summary.
     */
    public function result(Request $request, TestAttempt $attempt): JsonResponse
    {
        $user = $request->user();
        if ($attempt->user_id !== $user->id) {
            return $this->error('Unauthorized attempt access.', null, 403);
        }
        if ($attempt->status === 'in_progress') {
            return $this->error('Attempt has not been submitted yet.', null, 400);
        }

        return $this->success(new TestAttemptResource($attempt));
    }

    /**
     * GET /api/v1/attempts/{attempt}/review — Full review with explanations and correct answers.
     */
    public function review(Request $request, TestAttempt $attempt): JsonResponse
    {
        $user = $request->user();
        $reviewData = $this->assessmentService->getReviewData($user, $attempt);

        return $this->success($reviewData);
    }

    /**
     * GET /api/v1/tests/{test}/attempts — Get all attempts by this user for a test.
     */
    public function testAttempts(Request $request, Test $test): JsonResponse
    {
        $user = $request->user();
        $attempts = TestAttempt::where('user_id', $user->id)
            ->where('test_id', $test->id)
            ->orderByDesc('attempt_number')
            ->get();

        return $this->success(TestAttemptResource::collection($attempts));
    }
}
