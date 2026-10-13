<?php

namespace App\Services\Learning;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\Topic;
use App\Models\TopicProgress;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LessonProgressionService
{
    /**
     * Determine accessibility and status of a topic for a user.
     *
     * @return array{status: string, is_accessible: bool, reason: ?string}
     */
    public function getTopicStatus(User $user, Topic $topic): array
    {
        // 1. Must be enrolled in the course
        $enrolled = CourseEnrollment::where('user_id', $user->id)
            ->where('course_id', $topic->course_id)
            ->where('status', 'active')
            ->exists();

        if (!$enrolled) {
            return [
                'status' => 'locked',
                'is_accessible' => false,
                'reason' => 'You must enroll in this course to access this topic.',
            ];
        }

        // 2. Week 1 is always accessible to enrolled students
        if ($topic->week_number === 1) {
            $currentProgress = TopicProgress::where('user_id', $user->id)
                ->where('topic_id', $topic->id)
                ->first();

            $status = $currentProgress?->status ?? 'available';
            return [
                'status' => $status,
                'is_accessible' => true,
                'reason' => null,
            ];
        }

        // 3. For Week N > 1, check if Week N-1 is passed
        $previousTopic = Topic::where('course_id', $topic->course_id)
            ->where('week_number', $topic->week_number - 1)
            ->first();

        if (!$previousTopic) {
            return [
                'status' => 'available',
                'is_accessible' => true,
                'reason' => null,
            ];
        }

        $prevAssessment = Test::where('topic_id', $previousTopic->id)
            ->where('type', 'topic_assessment')
            ->first();

        if ($prevAssessment) {
            $hasPassedPrev = TestAttempt::where('user_id', $user->id)
                ->where('test_id', $prevAssessment->id)
                ->where('passed', true)
                ->exists();

            if (!$hasPassedPrev) {
                return [
                    'status' => 'locked',
                    'is_accessible' => false,
                    'reason' => "You must achieve at least {$prevAssessment->pass_mark}% on the Week {$previousTopic->week_number} assessment to unlock this topic.",
                ];
            }
        }

        $currentProgress = TopicProgress::where('user_id', $user->id)
            ->where('topic_id', $topic->id)
            ->first();

        $status = $currentProgress?->status ?? 'available';

        return [
            'status' => $status,
            'is_accessible' => true,
            'reason' => null,
        ];
    }

    /**
     * Determine accessibility and status of a lesson for a user.
     *
     * @return array{status: string, is_accessible: bool, reason: ?string}
     */
    public function getLessonStatus(User $user, Lesson $lesson): array
    {
        $topic = $lesson->topic;
        $topicStatus = $this->getTopicStatus($user, $topic);

        if (!$topicStatus['is_accessible']) {
            return [
                'status' => 'locked',
                'is_accessible' => false,
                'reason' => $topicStatus['reason'],
            ];
        }

        // Retrieve existing lesson progress
        $progress = LessonProgress::where('user_id', $user->id)
            ->where('lesson_id', $lesson->id)
            ->first();

        if ($progress && $progress->status === 'completed') {
            return [
                'status' => 'completed',
                'is_accessible' => true,
                'reason' => null,
            ];
        }

        // First lesson in the topic is accessible if topic is accessible
        if ($lesson->order === 1) {
            return [
                'status' => $progress?->status ?? 'available',
                'is_accessible' => true,
                'reason' => null,
            ];
        }

        // For Lesson M > 1, check if Lesson M-1 in the same topic is completed
        $previousLesson = Lesson::where('topic_id', $topic->id)
            ->where('order', $lesson->order - 1)
            ->first();

        if ($previousLesson) {
            $prevCompleted = LessonProgress::where('user_id', $user->id)
                ->where('lesson_id', $previousLesson->id)
                ->where('status', 'completed')
                ->exists();

            if (!$prevCompleted) {
                return [
                    'status' => 'locked',
                    'is_accessible' => false,
                    'reason' => "You must complete the previous lesson ('{$previousLesson->title}') before starting this lesson.",
                ];
            }
        }

        return [
            'status' => $progress?->status ?? 'available',
            'is_accessible' => true,
            'reason' => null,
        ];
    }

    /**
     * Mark a lesson as started.
     */
    public function startLesson(User $user, Lesson $lesson): LessonProgress
    {
        $status = $this->getLessonStatus($user, $lesson);
        if (!$status['is_accessible']) {
            abort(403, $status['reason'] ?? 'This lesson is currently locked.');
        }

        return DB::transaction(function () use ($user, $lesson) {
            $progress = LessonProgress::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'lesson_id' => $lesson->id,
                ],
                [
                    'status' => 'in_progress',
                    'started_at' => now(),
                ]
            );

            if ($progress->status === 'available') {
                $progress->update([
                    'status' => 'in_progress',
                    'started_at' => $progress->started_at ?? now(),
                ]);
            }

            // Update topic progress to in_progress if currently locked/available
            TopicProgress::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'topic_id' => $lesson->topic_id,
                ],
                [
                    'status' => 'in_progress',
                ]
            );

            return $progress;
        });
    }

    /**
     * Mark a lesson as completed and evaluate topic completion.
     */
    public function completeLesson(User $user, Lesson $lesson): LessonProgress
    {
        $status = $this->getLessonStatus($user, $lesson);
        if (!$status['is_accessible']) {
            abort(403, $status['reason'] ?? 'This lesson is currently locked.');
        }

        return DB::transaction(function () use ($user, $lesson) {
            $existing = LessonProgress::where('user_id', $user->id)
                ->where('lesson_id', $lesson->id)
                ->first();

            $progress = LessonProgress::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'lesson_id' => $lesson->id,
                ],
                [
                    'status' => 'completed',
                    'started_at' => $existing?->started_at ?? now(),
                    'completed_at' => now(),
                ]
            );

            $this->evaluateTopicCompletion($user, $lesson->topic);

            return $progress->fresh();
        });
    }

    /**
     * Evaluate if a topic is completed.
     * A topic is completed if all its lessons are completed AND its topic assessment is passed (if present).
     */
    public function evaluateTopicCompletion(User $user, Topic $topic): bool
    {
        $totalLessons = $topic->lessons()->count();
        $completedLessons = LessonProgress::where('user_id', $user->id)
            ->whereIn('lesson_id', $topic->lessons()->pluck('id'))
            ->where('status', 'completed')
            ->count();

        if ($completedLessons < $totalLessons) {
            return false;
        }

        $assessment = Test::where('topic_id', $topic->id)
            ->where('type', 'topic_assessment')
            ->first();

        if ($assessment) {
            $passed = TestAttempt::where('user_id', $user->id)
                ->where('test_id', $assessment->id)
                ->where('passed', true)
                ->exists();

            if (!$passed) {
                return false;
            }
        }

        TopicProgress::updateOrCreate(
            [
                'user_id' => $user->id,
                'topic_id' => $topic->id,
            ],
            [
                'status' => 'completed',
                'completed_at' => now(),
            ]
        );

        return true;
    }

    /**
     * Check if a student is eligible for the final examination.
     *
     * @return array{eligible: bool, reason: ?string, topics_completed: int, total_topics: int}
     */
    public function checkFinalExamEligibility(User $user, Course $course): array
    {
        $isEnrolled = CourseEnrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->where('status', 'active')
            ->exists();

        if (!$isEnrolled) {
            return [
                'eligible' => false,
                'reason' => 'You must be enrolled in the course to take the final examination.',
                'topics_completed' => 0,
                'total_topics' => $course->topics()->count(),
            ];
        }

        $topics = $course->topics()->orderBy('week_number')->get();
        $totalTopics = $topics->count();
        $completedTopics = 0;

        foreach ($topics as $topic) {
            $isComplete = $this->evaluateTopicCompletion($user, $topic);
            if ($isComplete) {
                $completedTopics++;
            }
        }

        if ($completedTopics < $totalTopics) {
            return [
                'eligible' => false,
                'reason' => "You must complete all lessons and pass all 8 weekly assessments before taking the final exam. (Completed: {$completedTopics}/{$totalTopics} topics)",
                'topics_completed' => $completedTopics,
                'total_topics' => $totalTopics,
            ];
        }

        return [
            'eligible' => true,
            'reason' => null,
            'topics_completed' => $completedTopics,
            'total_topics' => $totalTopics,
        ];
    }
}
