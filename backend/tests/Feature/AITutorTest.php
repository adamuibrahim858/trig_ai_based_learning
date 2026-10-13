<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Lesson;
use App\Models\Topic;
use App\Models\User;
use App\Services\AI\GeminiService;
use Database\Seeders\CourseCurriculumSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AITutorTest extends TestCase
{
    use RefreshDatabase;

    protected User $student;
    protected string $token;
    protected Course $course;
    protected Topic $week1Topic;
    protected Lesson $week1Lesson;

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

        $this->week1Topic = Topic::where('course_id', $this->course->id)->where('week_number', 1)->first();
        $this->week1Lesson = $this->week1Topic->lessons()->first();
    }

    public function test_student_can_create_conversation_for_accessible_lesson(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/ai/conversations', [
                'lesson_id' => $this->week1Lesson->id,
                'title' => 'Trigonometry Ratios Help',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'Trigonometry Ratios Help');

        $this->assertDatabaseHas('ai_conversations', [
            'user_id' => $this->student->id,
            'lesson_id' => $this->week1Lesson->id,
        ]);
    }

    public function test_student_cannot_create_conversation_for_locked_lesson(): void
    {
        $week2Topic = Topic::where('course_id', $this->course->id)->where('week_number', 2)->first();
        $week2Lesson = $week2Topic->lessons()->first();

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/ai/conversations', [
                'lesson_id' => $week2Lesson->id,
            ]);

        $response->assertStatus(403);
    }

    public function test_student_can_send_message_and_receive_ai_response(): void
    {
        // Mock GeminiService
        $geminiMock = Mockery::mock(GeminiService::class);
        $geminiMock->shouldReceive('generateContent')
            ->once()
            ->andReturn([
                'text' => 'Remember SOH-CAH-TOA: Sine is Opposite over Hypotenuse!',
                'raw' => [],
            ]);
        $this->app->instance(GeminiService::class, $geminiMock);

        // Create conversation
        $conv = $this->student->aiConversations()->create([
            'lesson_id' => $this->week1Lesson->id,
            'title' => 'Test SOH',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/v1/ai/conversations/{$conv->id}/messages", [
                'message' => 'What is the sine ratio?',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user_message.message', 'What is the sine ratio?')
            ->assertJsonPath('data.assistant_message.message', 'Remember SOH-CAH-TOA: Sine is Opposite over Hypotenuse!');

        $this->assertDatabaseHas('ai_messages', [
            'conversation_id' => $conv->id,
            'role' => 'user',
            'message' => 'What is the sine ratio?',
        ]);

        $this->assertDatabaseHas('ai_messages', [
            'conversation_id' => $conv->id,
            'role' => 'assistant',
            'message' => 'Remember SOH-CAH-TOA: Sine is Opposite over Hypotenuse!',
        ]);
    }

    public function test_student_can_generate_practice_question_for_accessible_topic(): void
    {
        $geminiMock = Mockery::mock(GeminiService::class);
        $geminiMock->shouldReceive('generateContent')
            ->once()
            ->andReturn([
                'text' => "Problem: A right triangle has legs 3 and 4. Find hypotenuse.\nHint: Use Pythagoras.\nSolution: 3²+4²=25, sqrt(25)=5.\nAnswer: 5",
                'raw' => [],
            ]);
        $this->app->instance(GeminiService::class, $geminiMock);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/ai/practice', [
                'topic_id' => $this->week1Topic->id,
                'difficulty' => 'easy',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.topic_id', $this->week1Topic->id)
            ->assertJsonPath('data.difficulty', 'easy');
    }

    public function test_student_can_request_lesson_summary(): void
    {
        $geminiMock = Mockery::mock(GeminiService::class);
        $geminiMock->shouldReceive('generateContent')
            ->once()
            ->andReturn([
                'text' => "1. Key Takeaway: SOH-CAH-TOA\n2. Formula: sin(θ) = opp/hyp\n3. Pitfall: Mixing adjacent and hypotenuse",
                'raw' => [],
            ]);
        $this->app->instance(GeminiService::class, $geminiMock);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/v1/ai/lessons/{$this->week1Lesson->id}/summarize");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.lesson_id', $this->week1Lesson->id);
    }
}
