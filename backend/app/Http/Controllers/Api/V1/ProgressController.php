<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\LessonProgress;
use App\Models\TopicProgress;
use App\Models\TestAttempt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProgressController extends ApiController
{
    /**
     * GET /api/v1/progress
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $lessonProgress = LessonProgress::with('lesson:id,title,topic_id')
            ->where('user_id', $user->id)
            ->get();

        $topicProgress = TopicProgress::with('topic:id,title,week_number')
            ->where('user_id', $user->id)
            ->get();

        $recentAttempts = TestAttempt::with('test:id,title,type')
            ->where('user_id', $user->id)
            ->where('status', 'submitted')
            ->latest('submitted_at')
            ->limit(10)
            ->get();

        $totalLessons = \App\Models\Lesson::where('is_published', true)->count();
        $completedLessons = $lessonProgress->where('status', 'completed')->count();

        return $this->success([
            'overall' => [
                'total_lessons' => $totalLessons,
                'completed_lessons' => $completedLessons,
                'completion_percentage' => $totalLessons > 0
                    ? round(($completedLessons / $totalLessons) * 100, 1)
                    : 0,
            ],
            'lessons' => $lessonProgress->map(fn($lp) => [
                'lesson_id' => $lp->lesson_id,
                'lesson_title' => $lp->lesson?->title,
                'status' => $lp->status,
                'started_at' => $lp->started_at?->toIso8601String(),
                'completed_at' => $lp->completed_at?->toIso8601String(),
            ]),
            'topics' => $topicProgress->map(fn($tp) => [
                'topic_id' => $tp->topic_id,
                'topic_title' => $tp->topic?->title,
                'week_number' => $tp->topic?->week_number,
                'status' => $tp->status,
                'completed_at' => $tp->completed_at?->toIso8601String(),
            ]),
            'recent_attempts' => $recentAttempts->map(fn($a) => [
                'id' => $a->id,
                'test_title' => $a->test?->title,
                'test_type' => $a->test?->type,
                'percentage' => $a->percentage,
                'passed' => (bool) $a->passed,
                'submitted_at' => $a->submitted_at?->toIso8601String(),
            ]),
        ]);
    }

    /**
     * GET /api/v1/progress/topics
     */
    public function topics(Request $request): JsonResponse
    {
        $user = $request->user();

        $progress = TopicProgress::with('topic:id,title,week_number,course_id')
            ->where('user_id', $user->id)
            ->get();

        return $this->success($progress->map(fn($tp) => [
            'topic_id' => $tp->topic_id,
            'topic_title' => $tp->topic?->title,
            'week_number' => $tp->topic?->week_number,
            'status' => $tp->status,
            'completed_at' => $tp->completed_at?->toIso8601String(),
        ]));
    }

    /**
     * GET /api/v1/progress/lessons
     */
    public function lessons(Request $request): JsonResponse
    {
        $user = $request->user();

        $progress = LessonProgress::with('lesson:id,title,slug,topic_id')
            ->where('user_id', $user->id)
            ->get();

        return $this->success($progress->map(fn($lp) => [
            'lesson_id' => $lp->lesson_id,
            'lesson_title' => $lp->lesson?->title,
            'status' => $lp->status,
            'started_at' => $lp->started_at?->toIso8601String(),
            'completed_at' => $lp->completed_at?->toIso8601String(),
        ]));
    }
}
