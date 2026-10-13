<?php

namespace Tests\Feature;

use App\Models\AiConversation;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\LearningRecommendation;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\Topic;
use App\Models\User;
use Database\Seeders\CourseCurriculumSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityAndIDORTest extends TestCase
{
    use RefreshDatabase;

    protected User $studentA;
    protected string $tokenA;

    protected User $studentB;
    protected string $tokenB;

    protected Course $course;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CourseCurriculumSeeder::class);

        $this->studentA = User::factory()->create(['name' => 'Student Alice']);
        $this->tokenA = $this->studentA->createToken('token-a')->plainTextToken;

        $this->studentB = User::factory()->create(['name' => 'Student Bob']);
        $this->tokenB = $this->studentB->createToken('token-b')->plainTextToken;

        $this->course = Course::first();

        foreach ([$this->studentA, $this->studentB] as $user) {
            CourseEnrollment::create([
                'user_id' => $user->id,
                'course_id' => $this->course->id,
                'status' => 'active',
                'enrolled_at' => now(),
            ]);
        }
    }

    /**
     * IDOR: Student B cannot view Student A's test attempt questions.
     */
    public function test_student_b_cannot_view_student_a_test_attempt(): void
    {
        $test = Test::where('type', 'topic_assessment')->first();
        $attemptA = TestAttempt::create([
            'user_id' => $this->studentA->id,
            'test_id' => $test->id,
            'attempt_number' => 1,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->tokenB}")
            ->getJson("/api/v1/attempts/{$attemptA->id}");

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    /**
     * IDOR: Student B cannot submit answers to Student A's test attempt.
     */
    public function test_student_b_cannot_submit_student_a_test_attempt(): void
    {
        $test = Test::where('type', 'topic_assessment')->first();
        $q = $test->questions()->first();
        $attemptA = TestAttempt::create([
            'user_id' => $this->studentA->id,
            'test_id' => $test->id,
            'attempt_number' => 1,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->tokenB}")
            ->postJson("/api/v1/attempts/{$attemptA->id}/submit", [
                'answers' => [
                    [
                        'question_id' => $q->id,
                        'selected_option_id' => $q->options()->first()?->id,
                    ],
                ],
            ]);

        $response->assertStatus(403);
    }

    /**
     * IDOR: Student B cannot view Student A's graded attempt result or review.
     */
    public function test_student_b_cannot_view_student_a_attempt_result_or_review(): void
    {
        $test = Test::where('type', 'topic_assessment')->first();
        $attemptA = TestAttempt::create([
            'user_id' => $this->studentA->id,
            'test_id' => $test->id,
            'attempt_number' => 1,
            'status' => 'submitted',
            'score' => 80,
            'total_marks' => 100,
            'percentage' => 80.0,
            'passed' => true,
            'started_at' => now()->subHour(),
            'submitted_at' => now(),
        ]);

        // Result check
        $this->withHeader('Authorization', "Bearer {$this->tokenB}")
            ->getJson("/api/v1/attempts/{$attemptA->id}/result")
            ->assertStatus(403);

        // Review check
        $this->withHeader('Authorization', "Bearer {$this->tokenB}")
            ->getJson("/api/v1/attempts/{$attemptA->id}/review")
            ->assertStatus(403);
    }

    /**
     * IDOR: Student B cannot access, delete, or send messages in Student A's AI conversation.
     */
    public function test_student_b_cannot_access_or_message_in_student_a_ai_conversation(): void
    {
        $convA = AiConversation::create([
            'user_id' => $this->studentA->id,
            'title' => 'Alice Private Tutoring',
        ]);

        // View
        $this->withHeader('Authorization', "Bearer {$this->tokenB}")
            ->getJson("/api/v1/ai/conversations/{$convA->id}")
            ->assertStatus(404);

        // Send message
        $this->withHeader('Authorization', "Bearer {$this->tokenB}")
            ->postJson("/api/v1/ai/conversations/{$convA->id}/messages", [
                'message' => 'Sneaky message from Bob',
            ])
            ->assertStatus(404);

        // Delete
        $this->withHeader('Authorization', "Bearer {$this->tokenB}")
            ->deleteJson("/api/v1/ai/conversations/{$convA->id}")
            ->assertStatus(404);
    }

    /**
     * IDOR: Student B cannot download Student A's certificate.
     */
    public function test_student_b_cannot_download_student_a_certificate(): void
    {
        $certA = Certificate::create([
            'user_id' => $this->studentA->id,
            'course_id' => $this->course->id,
            'certificate_number' => 'TRIG-ALIC-0001',
            'final_score' => 95.0,
            'issued_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->tokenB}")
            ->get("/api/v1/certificates/{$certA->id}/download");

        $response->assertStatus(403);
    }

    /**
     * IDOR: Student B cannot dismiss Student A's recommendations.
     */
    public function test_student_b_cannot_dismiss_student_a_recommendation(): void
    {
        $recA = LearningRecommendation::create([
            'user_id' => $this->studentA->id,
            'topic_id' => $this->course->topics()->first()->id,
            'recommendation_type' => 'revision',
            'reason' => 'Private recommendation for Alice',
            'status' => 'active',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->tokenB}")
            ->postJson("/api/v1/recommendations/{$recA->id}/dismiss");

        $response->assertStatus(404);
    }
}
