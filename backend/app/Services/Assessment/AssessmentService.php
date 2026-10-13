<?php

namespace App\Services\Assessment;

use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Test;
use App\Models\TestAnswer;
use App\Models\TestAttempt;
use App\Models\User;
use App\Services\Learning\LessonProgressionService;
use App\Services\Learning\PersonalizationService;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AssessmentService
{
    public function __construct(
        protected LessonProgressionService $progressionService,
        protected PersonalizationService $personalizationService
    ) {}

    /**
     * Verify whether a user is allowed to access and attempt this test.
     */
    public function canAccessTest(User $user, Test $test): array
    {
        if (!$test->is_published) {
            return ['allowed' => false, 'reason' => 'This assessment is currently unpublished.'];
        }

        // Final exam eligibility check
        if ($test->type === 'final_exam') {
            $course = $test->course;
            if (!$course) {
                return ['allowed' => false, 'reason' => 'Course not associated with final examination.'];
            }
            $eligibility = $this->progressionService->checkFinalExamEligibility($user, $course);
            if (!$eligibility['eligible']) {
                return ['allowed' => false, 'reason' => $eligibility['reason']];
            }
        }

        // Topic assessment check
        if ($test->type === 'topic_assessment' && $test->topic) {
            $topicStatus = $this->progressionService->getTopicStatus($user, $test->topic);
            if (!$topicStatus['is_accessible']) {
                return ['allowed' => false, 'reason' => $topicStatus['reason']];
            }
        }

        // Lesson practice check
        if ($test->type === 'lesson_practice' && $test->lesson) {
            $lessonStatus = $this->progressionService->getLessonStatus($user, $test->lesson);
            if (!$lessonStatus['is_accessible']) {
                return ['allowed' => false, 'reason' => $lessonStatus['reason']];
            }
        }

        // Check attempt limit
        if ($test->attempts_allowed !== null) {
            $finishedCount = TestAttempt::where('user_id', $user->id)
                ->where('test_id', $test->id)
                ->whereIn('status', ['submitted', 'abandoned'])
                ->count();

            if ($finishedCount >= $test->attempts_allowed) {
                return [
                    'allowed' => false,
                    'reason' => "You have reached the maximum allowed attempts ({$test->attempts_allowed}) for this assessment.",
                ];
            }
        }

        return ['allowed' => true, 'reason' => null];
    }

    /**
     * Start a new test attempt.
     */
    public function startAttempt(User $user, Test $test): TestAttempt
    {
        $access = $this->canAccessTest($user, $test);
        if (!$access['allowed']) {
            throw new HttpException(403, $access['reason']);
        }

        return DB::transaction(function () use ($user, $test) {
            // Check if there is already an active in_progress attempt
            $existingActive = TestAttempt::where('user_id', $user->id)
                ->where('test_id', $test->id)
                ->where('status', 'in_progress')
                ->latest()
                ->first();

            if ($existingActive) {
                // If test has duration_minutes, check if existingActive has expired
                if ($test->duration_minutes && $existingActive->started_at) {
                    $expirationTime = $existingActive->started_at->copy()->addMinutes($test->duration_minutes + 5);
                    if (now()->greaterThan($expirationTime)) {
                        $existingActive->update([
                            'status' => 'abandoned',
                            'submitted_at' => now(),
                        ]);
                    } else {
                        return $existingActive;
                    }
                } else {
                    return $existingActive;
                }
            }

            $attemptCount = TestAttempt::where('user_id', $user->id)
                ->where('test_id', $test->id)
                ->count();

            return TestAttempt::create([
                'user_id' => $user->id,
                'test_id' => $test->id,
                'attempt_number' => $attemptCount + 1,
                'status' => 'in_progress',
                'score' => 0.00,
                'total_marks' => $test->total_marks,
                'percentage' => 0.00,
                'passed' => false,
                'started_at' => now(),
            ]);
        });
    }

    /**
     * Submit an attempt with submitted answers.
     *
     * @param array<int, array{question_id: int, selected_option_id?: ?int, answer_text?: ?string}> $answers
     */
    public function submitAttempt(User $user, TestAttempt $attempt, array $answers): TestAttempt
    {
        if ($attempt->user_id !== $user->id) {
            throw new HttpException(403, 'Unauthorized attempt access.');
        }

        if ($attempt->status !== 'in_progress') {
            throw new HttpException(409, 'This attempt has already been submitted or completed.');
        }

        $test = $attempt->test;

        // Enforce time limit if configured (with 5 min grace period for network latency)
        if ($test->duration_minutes && $attempt->started_at) {
            $expirationTime = $attempt->started_at->copy()->addMinutes($test->duration_minutes + 5);
            if (now()->greaterThan($expirationTime)) {
                $attempt->update([
                    'status' => 'abandoned',
                    'submitted_at' => now(),
                ]);
                throw new HttpException(422, 'The time limit for this assessment has expired. Your attempt has been marked as abandoned.');
            }
        }

        $testQuestions = $test->questions()->with('options')->get()->keyBy('id');

        // Validate that all submitted question IDs belong to this test
        foreach ($answers as $ans) {
            if (isset($ans['question_id']) && !isset($testQuestions[$ans['question_id']])) {
                throw new HttpException(422, "Question ID {$ans['question_id']} does not belong to this assessment.");
            }
        }

        return DB::transaction(function () use ($attempt, $test, $testQuestions, $answers, $user) {
            $score = 0.00;
            $totalPossibleMarks = 0.00;
            $incorrectQuestionIds = [];

            // Index submitted answers by question_id
            $submittedMap = [];
            foreach ($answers as $ans) {
                if (isset($ans['question_id'])) {
                    $submittedMap[$ans['question_id']] = $ans;
                }
            }

            foreach ($testQuestions as $qId => $question) {
                $totalPossibleMarks += (float) $question->marks;
                $submitted = $submittedMap[$qId] ?? null;

                $selectedOptionId = $submitted['selected_option_id'] ?? null;
                $answerText = isset($submitted['answer_text']) ? trim((string)$submitted['answer_text']) : null;
                $isCorrect = false;
                $marksAwarded = 0.00;

                if ($question->question_type === 'multiple_choice' || $question->question_type === 'true_false') {
                    if ($selectedOptionId) {
                        $option = $question->options->firstWhere('id', $selectedOptionId);
                        if ($option && $option->is_correct) {
                            $isCorrect = true;
                            $marksAwarded = (float) $question->marks;
                        }
                    }
                } elseif ($question->question_type === 'numerical') {
                    // Check against correct option or metadata
                    $correctOption = $question->options->firstWhere('is_correct', true);
                    $meta = $question->metadata;

                    $targetVal = null;
                    $tolerance = 0.1;

                    if ($meta && isset($meta['correct_value'])) {
                        $targetVal = (float) $meta['correct_value'];
                        $tolerance = (float) ($meta['tolerance'] ?? 0.1);
                    } elseif ($correctOption) {
                        $targetVal = (float) $correctOption->option_text;
                    }

                    if ($targetVal !== null && $answerText !== null && is_numeric($answerText)) {
                        $studentVal = (float) $answerText;
                        if (abs($studentVal - $targetVal) <= $tolerance) {
                            $isCorrect = true;
                            $marksAwarded = (float) $question->marks;
                        }
                    } elseif ($selectedOptionId) {
                        $opt = $question->options->firstWhere('id', $selectedOptionId);
                        if ($opt && $opt->is_correct) {
                            $isCorrect = true;
                            $marksAwarded = (float) $question->marks;
                        }
                    }
                }

                if ($isCorrect) {
                    $score += $marksAwarded;
                } else {
                    $incorrectQuestionIds[] = $question->id;
                }

                // Record answer
                TestAnswer::updateOrCreate(
                    [
                        'attempt_id' => $attempt->id,
                        'question_id' => $question->id,
                    ],
                    [
                        'selected_option_id' => $selectedOptionId,
                        'answer_text' => $answerText,
                        'is_correct' => $isCorrect,
                        'marks_awarded' => $marksAwarded,
                    ]
                );
            }

            // Calculate percentage
            $percentage = $totalPossibleMarks > 0
                ? round(($score / $totalPossibleMarks) * 100, 2)
                : 0.00;

            $passed = $percentage >= $test->pass_mark;

            $attempt->update([
                'status' => 'submitted',
                'score' => $score,
                'total_marks' => $totalPossibleMarks,
                'percentage' => $percentage,
                'passed' => $passed,
                'submitted_at' => now(),
            ]);

            // If passed and is topic assessment, mark topic progress
            if ($passed && $test->type === 'topic_assessment' && $test->topic) {
                $this->progressionService->evaluateTopicCompletion($user, $test->topic);
            }

            // Generate personalized recommendations based on attempt
            $this->personalizationService->generateRecommendationsForAttempt($user, $attempt, $incorrectQuestionIds);

            return $attempt->fresh(['answers.question', 'test']);
        });
    }

    /**
     * Get review payload with question explanations and correct options after submission.
     */
    public function getReviewData(User $user, TestAttempt $attempt): array
    {
        if ($attempt->user_id !== $user->id) {
            throw new HttpException(403, 'Unauthorized attempt access.');
        }

        if ($attempt->status === 'in_progress') {
            throw new HttpException(400, 'Attempt has not yet been submitted. Review is only permitted after submission.');
        }

        $attempt->load(['test', 'answers.question.options', 'answers.selectedOption']);

        $results = [];
        foreach ($attempt->answers as $ans) {
            $question = $ans->question;
            $options = $question->options->map(fn(QuestionOption $opt) => [
                'id' => $opt->id,
                'option_text' => $opt->option_text,
                'is_correct' => (bool)$opt->is_correct,
            ]);

            $results[] = [
                'question_id' => $question->id,
                'question_text' => $question->question_text,
                'question_type' => $question->question_type,
                'marks' => $question->marks,
                'marks_awarded' => $ans->marks_awarded,
                'is_correct' => (bool)$ans->is_correct,
                'selected_option_id' => $ans->selected_option_id,
                'answer_text' => $ans->answer_text,
                'explanation' => $question->explanation,
                'options' => $options,
            ];
        }

        return [
            'attempt_id' => $attempt->id,
            'test_id' => $attempt->test_id,
            'test_title' => $attempt->test->title,
            'score' => $attempt->score,
            'total_marks' => $attempt->total_marks,
            'percentage' => $attempt->percentage,
            'pass_mark' => $attempt->test->pass_mark,
            'passed' => (bool)$attempt->passed,
            'submitted_at' => $attempt->submitted_at,
            'questions' => $results,
        ];
    }
}
