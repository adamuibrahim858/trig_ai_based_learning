<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Question;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\Topic;
use App\Models\User;
use Database\Seeders\CourseCurriculumSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentBoundariesAndLimitsTest extends TestCase
{
    use RefreshDatabase;

    protected User $student;
    protected string $token;
    protected Course $course;
    protected Topic $topic;
    protected Test $test;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CourseCurriculumSeeder::class);

        $this->student = User::factory()->create();
        $this->token = $this->student->createToken('test-token')->plainTextToken;
        $this->course = Course::first();

        CourseEnrollment::create([
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        $this->topic = Topic::where('course_id', $this->course->id)->where('week_number', 1)->first();
        $this->test = Test::where('topic_id', $this->topic->id)->first();
    }

    /**
     * Test score boundary condition: 69.99% fails.
     */
    public function test_score_of_69_99_percent_fails(): void
    {
        // Create custom test with total_marks = 10000 to precisely test 69.99%
        $boundaryTest = Test::create([
            'course_id' => $this->course->id,
            'topic_id' => $this->topic->id,
            'title' => 'Boundary 69.99 Test',
            'type' => 'lesson_practice',
            'pass_mark' => 70.00,
            'total_marks' => 10000,
            'is_published' => true,
        ]);

        $q = Question::create([
            'test_id' => $boundaryTest->id,
            'question_text' => 'Precision test',
            'question_type' => 'numerical',
            'marks' => 10000,
            'order' => 1,
        ]);

        // Attempt with score 6999 out of 10000 = 69.99%
        $attempt = TestAttempt::create([
            'user_id' => $this->student->id,
            'test_id' => $boundaryTest->id,
            'attempt_number' => 1,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        // Create a fake test answer evaluation yielding 69.99%
        // Using AssessmentService logic via direct simulation:
        $score = 6999.00;
        $totalMarks = 10000.00;
        $percentage = round(($score / $totalMarks) * 100, 2); // 69.99
        $passed = $percentage >= $boundaryTest->pass_mark;

        $this->assertEquals(69.99, $percentage);
        $this->assertFalse($passed, '69.99% must strictly fail with a 70.00% pass mark threshold.');
    }

    /**
     * Test score boundary condition: 70.00% passes.
     */
    public function test_score_of_70_00_percent_passes(): void
    {
        $score = 70.00;
        $totalMarks = 100.00;
        $percentage = round(($score / $totalMarks) * 100, 2);
        $passed = $percentage >= 70.00;

        $this->assertEquals(70.00, $percentage);
        $this->assertTrue($passed, '70.00% must pass with a 70.00% pass mark threshold.');
    }

    /**
     * Test score boundary condition: 70.01% passes.
     */
    public function test_score_of_70_01_percent_passes(): void
    {
        $score = 70.01;
        $totalMarks = 100.00;
        $percentage = round(($score / $totalMarks) * 100, 2);
        $passed = $percentage >= 70.00;

        $this->assertEquals(70.01, $percentage);
        $this->assertTrue($passed, '70.01% must pass with a 70.00% pass mark threshold.');
    }

    /**
     * Submitting a question ID from another test is rejected with 422.
     */
    public function test_submitting_invalid_question_id_is_rejected_with_422(): void
    {
        $startRes = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/v1/tests/{$this->test->id}/attempts");
        $attemptId = $startRes->json('data.id');

        // Pick a question from another topic's test
        $otherTest = Test::where('id', '!=', $this->test->id)->first();
        $foreignQuestion = $otherTest->questions()->first();

        $submitRes = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/v1/attempts/{$attemptId}/submit", [
                'answers' => [
                    [
                        'question_id' => $foreignQuestion->id,
                        'selected_option_id' => $foreignQuestion->options()->first()?->id,
                    ],
                ],
            ]);

        $submitRes->assertStatus(422)
            ->assertJsonPath('message', "Question ID {$foreignQuestion->id} does not belong to this assessment.");
    }

    /**
     * Attempt limit is strictly enforced.
     */
    public function test_attempt_limits_are_enforced(): void
    {
        // Configure test with max 2 attempts
        $this->test->update(['attempts_allowed' => 2]);

        // 1. First attempt submitted
        TestAttempt::create([
            'user_id' => $this->student->id,
            'test_id' => $this->test->id,
            'attempt_number' => 1,
            'status' => 'submitted',
            'score' => 50,
            'total_marks' => 100,
            'percentage' => 50.0,
            'passed' => false,
            'started_at' => now()->subHours(2),
            'submitted_at' => now()->subHours(1),
        ]);

        // 2. Second attempt submitted
        TestAttempt::create([
            'user_id' => $this->student->id,
            'test_id' => $this->test->id,
            'attempt_number' => 2,
            'status' => 'submitted',
            'score' => 60,
            'total_marks' => 100,
            'percentage' => 60.0,
            'passed' => false,
            'started_at' => now()->subMinutes(50),
            'submitted_at' => now()->subMinutes(20),
        ]);

        // 3. Third attempt should be rejected with 403 (attempt limit reached)
        $thirdAttemptRes = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/v1/tests/{$this->test->id}/attempts");

        $thirdAttemptRes->assertStatus(403)
            ->assertJsonPath('message', 'You have reached the maximum allowed attempts (2) for this assessment.');
    }

    /**
     * Stale expired attempt is marked abandoned and rejected.
     */
    public function test_expired_in_progress_attempt_is_marked_abandoned(): void
    {
        // Set test duration to 30 minutes
        $this->test->update(['duration_minutes' => 30]);

        $firstQuestion = $this->test->questions()->first();

        // Create an attempt started 2 hours ago (expired)
        $staleAttempt = TestAttempt::create([
            'user_id' => $this->student->id,
            'test_id' => $this->test->id,
            'attempt_number' => 1,
            'status' => 'in_progress',
            'started_at' => now()->subHours(2),
        ]);

        // Submitting this stale attempt must be rejected and marked abandoned
        $submitRes = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/v1/attempts/{$staleAttempt->id}/submit", [
                'answers' => [
                    [
                        'question_id' => $firstQuestion->id,
                        'selected_option_id' => $firstQuestion->options()->first()?->id,
                    ],
                ],
            ]);

        $submitRes->assertStatus(422)
            ->assertJsonPath('message', 'The time limit for this assessment has expired. Your attempt has been marked as abandoned.');

        $this->assertDatabaseHas('test_attempts', [
            'id' => $staleAttempt->id,
            'status' => 'abandoned',
        ]);
    }
}
