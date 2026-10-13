<?php

namespace App\Services\Learning;

use App\Models\LearningRecommendation;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\TestAttempt;
use App\Models\Topic;
use App\Models\User;

class PersonalizationService
{
    /**
     * Generate recommendations based on a submitted attempt and incorrect questions.
     *
     * @param array<int> $incorrectQuestionIds
     */
    public function generateRecommendationsForAttempt(User $user, TestAttempt $attempt, array $incorrectQuestionIds): void
    {
        $test = $attempt->test;

        // 1. If score is high (> 85%), generate an enrichment recommendation
        if ($attempt->passed && $attempt->percentage >= 85.0) {
            LearningRecommendation::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'topic_id' => $test->topic_id,
                    'recommendation_type' => 'enrichment',
                    'status' => 'active',
                ],
                [
                    'reason' => "Outstanding performance ({$attempt->percentage}%) on {$test->title}! Explore advanced multi-step enrichment problems to solidify your mastery.",
                ]
            );
            return;
        }

        // 2. If attempt failed (< 70%), generate revision and practice recommendations
        if (!$attempt->passed) {
            $topic = $test->topic;
            $firstLesson = $topic ? $topic->lessons()->first() : null;

            LearningRecommendation::create([
                'user_id' => $user->id,
                'topic_id' => $test->topic_id,
                'lesson_id' => $firstLesson?->id,
                'recommendation_type' => 'revision',
                'reason' => "You achieved {$attempt->percentage}% on {$test->title} (passing mark is {$test->pass_mark}%). Review the core lesson concepts and worked examples before retaking the assessment.",
                'status' => 'active',
            ]);
        }

        // 3. Inspect specific incorrect questions to identify concept keywords
        if (!empty($incorrectQuestionIds)) {
            $questions = Question::whereIn('id', $incorrectQuestionIds)->get();

            foreach ($questions as $q) {
                $text = strtolower($q->question_text . ' ' . $q->explanation);

                // Check keywords for specific topic recommendations
                if (str_contains($text, 'hypotenuse') || str_contains($text, 'opposite') || str_contains($text, 'adjacent')) {
                    $w2Lesson = Lesson::where('slug', 'opposite-and-adjacent-sides-relative-to-reference-angle')->first();
                    if ($w2Lesson) {
                        $this->createConceptRecommendation(
                            $user,
                            $w2Lesson->topic_id,
                            $w2Lesson->id,
                            'remedial',
                            "You missed questions involving side identification relative to reference angles. We recommend reviewing '{$w2Lesson->title}'."
                        );
                    }
                } elseif (str_contains($text, 'pythagor') || str_contains($text, 'triple')) {
                    $w3Lesson = Lesson::where('slug', 'meaning-and-formula-of-pythagoras-theorem')->first();
                    if ($w3Lesson) {
                        $this->createConceptRecommendation(
                            $user,
                            $w3Lesson->topic_id,
                            $w3Lesson->id,
                            'remedial',
                            "Questions regarding right-triangle side calculations using a² + b² = c² showed difficulty. Revisit '{$w3Lesson->title}'."
                        );
                    }
                } elseif (str_contains($text, 'soh') || str_contains($text, 'ratio') || str_contains($text, 'tangent')) {
                    $w4Lesson = Lesson::where('slug', 'the-soh-cah-toa-mnemonic-and-ratio-selection')->first();
                    if ($w4Lesson) {
                        $this->createConceptRecommendation(
                            $user,
                            $w4Lesson->topic_id,
                            $w4Lesson->id,
                            'practice',
                            "Improve trigonometric ratio selection with SOH-CAH-TOA in '{$w4Lesson->title}'."
                        );
                    }
                } elseif (str_contains($text, 'inverse') || str_contains($text, 'arcsin') || str_contains($text, 'mode')) {
                    $w6Lesson = Lesson::where('slug', 'inverse-trigonometric-functions')->first();
                    if ($w6Lesson) {
                        $this->createConceptRecommendation(
                            $user,
                            $w6Lesson->topic_id,
                            $w6Lesson->id,
                            'remedial',
                            "Inverse trigonometric functions and calculator degree modes showed errors. Revisit '{$w6Lesson->title}'."
                        );
                    }
                } elseif (str_contains($text, 'elevation') || str_contains($text, 'depression')) {
                    $w7Lesson = Lesson::where('slug', 'understanding-elevation-and-depression')->first();
                    if ($w7Lesson) {
                        $this->createConceptRecommendation(
                            $user,
                            $w7Lesson->topic_id,
                            $w7Lesson->id,
                            'remedial',
                            "Ensure you measure angles of elevation and depression from the horizontal baseline in '{$w7Lesson->title}'."
                        );
                    }
                }
            }
        }
    }

    private function createConceptRecommendation(User $user, ?int $topicId, ?int $lessonId, string $type, string $reason): void
    {
        // Don't duplicate active recommendations for same lesson
        $exists = LearningRecommendation::where('user_id', $user->id)
            ->where('lesson_id', $lessonId)
            ->where('status', 'active')
            ->exists();

        if (!$exists) {
            LearningRecommendation::create([
                'user_id' => $user->id,
                'topic_id' => $topicId,
                'lesson_id' => $lessonId,
                'recommendation_type' => $type,
                'reason' => $reason,
                'status' => 'active',
            ]);
        }
    }

    /**
     * Get active recommendations for a student.
     */
    public function getRecommendations(User $user)
    {
        return LearningRecommendation::with(['topic:id,title,week_number', 'lesson:id,title,slug'])
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->latest()
            ->get();
    }

    /**
     * Dismiss a recommendation.
     */
    public function dismiss(User $user, LearningRecommendation $recommendation): bool
    {
        if ($recommendation->user_id !== $user->id) {
            abort(403, 'Unauthorized access to recommendation.');
        }

        return $recommendation->update(['status' => 'dismissed']);
    }
}
