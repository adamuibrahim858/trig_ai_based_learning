<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\LearningRecommendation;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\Topic;
use App\Models\TopicProgress;
use App\Services\Learning\LessonProgressionService;
use App\Services\Certificate\CertificateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends ApiController
{
    public function __construct(
        protected LessonProgressionService $progressionService,
        protected CertificateService $certificateService
    ) {}

    /**
     * GET /api/v1/dashboard
     * Get aggregate dashboard overview for the authenticated student.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // 1. Enrolled courses
        $enrollments = CourseEnrollment::with('course')
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->get();

        $primaryCourse = $enrollments->first()?->course ?? Course::first();

        // 2. Metrics for the primary course
        $totalTopics = 0;
        $completedTopics = 0;
        $totalLessons = 0;
        $completedLessons = 0;
        $lockedLessons = 0;
        $currentWeek = 1;
        $nextLesson = null;
        $weakTopics = [];
        $finalExamEligibility = null;
        $certificateInfo = null;

        if ($primaryCourse) {
            // Load user progress into memory to prevent N+1 queries
            $userTopicProgress = TopicProgress::where('user_id', $user->id)
                ->whereIn('topic_id', Topic::where('course_id', $primaryCourse->id)->pluck('id'))
                ->get()
                ->keyBy('topic_id');

            $allCourseLessons = Lesson::whereIn('topic_id', Topic::where('course_id', $primaryCourse->id)->pluck('id'))->get();
            $userLessonProgress = LessonProgress::where('user_id', $user->id)
                ->whereIn('lesson_id', $allCourseLessons->pluck('id'))
                ->get()
                ->keyBy('lesson_id');

            $topics = Topic::with(['lessons' => fn($q) => $q->orderBy('order')])
                ->where('course_id', $primaryCourse->id)
                ->orderBy('week_number')
                ->get();

            $totalTopics = $topics->count();
            $completedTopics = $userTopicProgress->where('status', 'completed')->count();
            $totalLessons = $allCourseLessons->count();
            $completedLessons = $userLessonProgress->where('status', 'completed')->count();

            $accessibleLessonCount = 0;

            foreach ($topics as $topic) {
                $topicStatus = $this->progressionService->getTopicStatus($user, $topic);
                $isTopicAccessible = $topicStatus['is_accessible'];

                foreach ($topic->lessons as $lesson) {
                    $lessonStatus = $this->progressionService->getLessonStatus($user, $lesson);
                    if ($lessonStatus['is_accessible']) {
                        $accessibleLessonCount++;
                    }

                    $lp = $userLessonProgress->get($lesson->id);
                    if ((!$lp || $lp->status !== 'completed') && $isTopicAccessible && $nextLesson === null) {
                        $nextLesson = [
                            'id' => $lesson->id,
                            'title' => $lesson->title,
                            'slug' => $lesson->slug,
                            'week_number' => $topic->week_number,
                            'topic_title' => $topic->title,
                            'estimated_duration_minutes' => $lesson->estimated_minutes,
                        ];
                        $currentWeek = $topic->week_number;
                    }
                }
            }

            $lockedLessons = max(0, $totalLessons - $accessibleLessonCount);

            // Identify weak topics (assessments where latest attempt score < 70%)
            $assessmentTestIds = Test::whereIn('topic_id', $topics->pluck('id'))
                ->where('type', 'topic_assessment')
                ->pluck('id', 'topic_id');

            foreach ($topics as $topic) {
                if (isset($assessmentTestIds[$topic->id])) {
                    $testId = $assessmentTestIds[$topic->id];
                    $latestAttempt = TestAttempt::where('user_id', $user->id)
                        ->where('test_id', $testId)
                        ->where('status', 'submitted')
                        ->latest('submitted_at')
                        ->first();

                    if ($latestAttempt && !$latestAttempt->passed) {
                        $weakTopics[] = [
                            'topic_id' => $topic->id,
                            'week_number' => $topic->week_number,
                            'topic_title' => $topic->title,
                            'latest_percentage' => $latestAttempt->percentage,
                            'pass_mark' => 70.00,
                        ];
                    }
                }
            }

            // Final exam eligibility
            $finalExamEligibility = $this->progressionService->checkFinalExamEligibility($user, $primaryCourse);

            // Certificate status
            $existingCert = Certificate::where('user_id', $user->id)
                ->where('course_id', $primaryCourse->id)
                ->first();

            if ($existingCert) {
                $certificateInfo = [
                    'status' => 'issued',
                    'certificate_number' => $existingCert->certificate_number,
                    'final_score' => $existingCert->final_score,
                    'issued_at' => $existingCert->issued_at->toIso8601String(),
                ];
            } else {
                $eligibility = $this->certificateService->checkEligibility($user, $primaryCourse);
                $certificateInfo = [
                    'status' => $eligibility['eligible'] ? 'eligible' : 'ineligible',
                    'reason' => $eligibility['reason'],
                ];
            }
        }

        // 3. Recent test attempts
        $recentAttempts = TestAttempt::with('test:id,title,type,pass_mark')
            ->where('user_id', $user->id)
            ->where('status', 'submitted')
            ->latest('submitted_at')
            ->limit(5)
            ->get()
            ->map(fn($a) => [
                'id' => $a->id,
                'test_title' => $a->test?->title,
                'test_type' => $a->test?->type,
                'score' => $a->score,
                'total_points' => $a->total_marks,
                'percentage' => $a->percentage,
                'passed' => (bool) $a->passed,
                'submitted_at' => $a->submitted_at?->toIso8601String(),
            ]);

        // 4. Active recommendations
        $activeRecommendations = LearningRecommendation::with(['topic:id,title,week_number', 'lesson:id,title,slug'])
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->latest()
            ->limit(3)
            ->get()
            ->map(fn($r) => [
                'id' => $r->id,
                'type' => $r->recommendation_type,
                'reason' => $r->reason,
                'topic' => $r->topic ? [
                    'id' => $r->topic->id,
                    'title' => $r->topic->title,
                    'week_number' => $r->topic->week_number,
                ] : null,
                'lesson' => $r->lesson ? [
                    'id' => $r->lesson->id,
                    'title' => $r->lesson->title,
                    'slug' => $r->lesson->slug,
                ] : null,
            ]);

        $completionPercentage = $totalLessons > 0 ? round(($completedLessons / $totalLessons) * 100, 1) : 0;

        return $this->success([
            'student' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
            'course' => $primaryCourse ? [
                'id' => $primaryCourse->id,
                'title' => $primaryCourse->title,
                'slug' => $primaryCourse->slug,
                'is_enrolled' => $enrollments->isNotEmpty(),
            ] : null,
            'progress_summary' => [
                'total_topics' => $totalTopics,
                'completed_topics' => $completedTopics,
                'total_lessons' => $totalLessons,
                'completed_lessons' => $completedLessons,
                'locked_lessons' => $lockedLessons,
                'current_week' => $currentWeek,
                'completion_percentage' => $completionPercentage,
            ],
            'current_week' => $currentWeek,
            'next_recommended_lesson' => $nextLesson,
            'weak_topics' => $weakTopics,
            'recent_attempts' => $recentAttempts,
            'active_recommendations' => $activeRecommendations,
            'final_exam_eligibility' => $finalExamEligibility,
            'certificate' => $certificateInfo,
        ]);
    }
}
