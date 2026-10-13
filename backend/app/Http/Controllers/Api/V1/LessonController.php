<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\LessonResource;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Services\Learning\LessonProgressionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LessonController extends ApiController
{
    public function __construct(
        protected LessonProgressionService $progressionService
    ) {}

    /**
     * GET /api/v1/lessons
     */
    public function index(Request $request): JsonResponse
    {
        $lessons = Lesson::with('resources', 'topic')
            ->where('is_published', true)
            ->orderBy('topic_id')
            ->orderBy('order')
            ->get();

        return $this->success(LessonResource::collection($lessons));
    }

    /**
     * GET /api/v1/lessons/{lesson}
     */
    public function show(Request $request, Lesson $lesson): JsonResponse
    {
        $lesson->load('resources', 'topic');

        return $this->success(new LessonResource($lesson));
    }

    /**
     * POST /api/v1/lessons/{lesson}/start
     */
    public function start(Request $request, Lesson $lesson): JsonResponse
    {
        $user = $request->user();
        $progress = $this->progressionService->startLesson($user, $lesson);

        return $this->success([
            'lesson_id' => $lesson->id,
            'status' => $progress->status,
            'started_at' => $progress->started_at?->toIso8601String(),
        ], 'Lesson started.');
    }

    /**
     * POST /api/v1/lessons/{lesson}/complete
     */
    public function complete(Request $request, Lesson $lesson): JsonResponse
    {
        $user = $request->user();
        $progress = $this->progressionService->completeLesson($user, $lesson);

        return $this->success([
            'lesson_id' => $lesson->id,
            'status' => $progress->status,
            'completed_at' => $progress->completed_at?->toIso8601String(),
        ], 'Lesson completed.');
    }

    /**
     * GET /api/v1/lessons/{lesson}/progress
     */
    public function progress(Request $request, Lesson $lesson): JsonResponse
    {
        $user = $request->user();
        $status = $this->progressionService->getLessonStatus($user, $lesson);

        $progress = LessonProgress::where('user_id', $user->id)
            ->where('lesson_id', $lesson->id)
            ->first();

        return $this->success([
            'lesson_id' => $lesson->id,
            'status' => $status['status'],
            'is_accessible' => $status['is_accessible'],
            'lock_reason' => $status['reason'],
            'started_at' => $progress?->started_at?->toIso8601String(),
            'completed_at' => $progress?->completed_at?->toIso8601String(),
        ]);
    }
}
