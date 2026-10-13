<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Question;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\Topic;
use App\Models\TopicProgress;
use App\Models\User;
use Database\Seeders\CourseCurriculumSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentEngineTest extends TestCase
{
    use RefreshDatabase;

    protected User $student;
    protected string $token;
    protected Course $course;
    protected Topic $week1Topic;
    protected Test $week1Assessment;

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
        $this->week1Assessment = Test::where('topic_id', $this->week1Topic->id)->first();
    }

    public function test_test_detail_masks_correct_options(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/v1/tests/{$this->week1Assessment->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $options = $response->json('data.questions.0.options');
        $this->assertNotEmpty($options);

        // Ensure is_correct is NOT returned in any option payload
        foreach ($options as $opt) {
            $this->assertArrayNotHasKey('is_correct', $opt);
        }
    }

    public function test_student_can_start_an_attempt(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/v1/tests/{$this->week1Assessment->id}/attempts");

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.test_id', $this->week1Assessment->id);

        $this->assertDatabaseHas('test_attempts', [
            'user_id' => $this->student->id,
            'test_id' => $this->week1Assessment->id,
            'status' => 'in_progress',
        ]);
    }

    public function test_submitting_all_correct_answers_passes_test_and_unlocks_next_topic(): void
    {
        // First, mark all lessons in week 1 as completed
        foreach ($this->week1Topic->lessons as $lesson) {
            LessonProgress::create([
                'user_id' => $this->student->id,
                'lesson_id' => $lesson->id,
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        }

        // Start attempt
        $startRes = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/v1/tests/{$this->week1Assessment->id}/attempts");
        $attemptId = $startRes->json('data.id');

        // Gather correct options for each question
        $questions = $this->week1Assessment->questions()->with('options')->get();
        $answers = [];
        foreach ($questions as $q) {
            $correctOpt = $q->options->where('is_correct', true)->first();
            $answers[] = [
                'question_id' => $q->id,
                'selected_option_id' => $correctOpt->id,
            ];
        }

        // Submit attempt
        $submitRes = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/v1/attempts/{$attemptId}/submit", [
                'answers' => $answers,
            ]);

        $submitRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.attempt.passed', true)
            ->assertJsonPath('data.attempt.percentage', 100);

        // Verify Week 1 topic progress is marked completed
        $this->assertDatabaseHas('topic_progress', [
            'user_id' => $this->student->id,
            'topic_id' => $this->week1Topic->id,
            'status' => 'completed',
        ]);

        // Week 2 topic should now be unlocked and accessible
        $week2Topic = Topic::where('course_id', $this->course->id)->where('week_number', 2)->first();
        $week2Lesson = $week2Topic->lessons()->first();

        $lessonRes = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/v1/lessons/{$week2Lesson->id}");

        $lessonRes->assertStatus(200)
            ->assertJsonPath('data.is_accessible', true)
            ->assertJsonPath('data.status', 'available');
    }

    public function test_submitting_failing_answers_generates_recommendations(): void
    {
        // Start attempt
        $startRes = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/v1/tests/{$this->week1Assessment->id}/attempts");
        $attemptId = $startRes->json('data.id');

        // Submit incorrect answers
        $questions = $this->week1Assessment->questions()->with('options')->get();
        $answers = [];
        foreach ($questions as $q) {
            $wrongOpt = $q->options->where('is_correct', false)->first();
            $answers[] = [
                'question_id' => $q->id,
                'selected_option_id' => $wrongOpt?->id,
            ];
        }

        $submitRes = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/v1/attempts/{$attemptId}/submit", [
                'answers' => $answers,
            ]);

        $submitRes->assertStatus(200)
            ->assertJsonPath('data.attempt.passed', false);

        // Check recommendations generated in DB
        $this->assertDatabaseHas('learning_recommendations', [
            'user_id' => $this->student->id,
            'topic_id' => $this->week1Topic->id,
            'status' => 'active',
        ]);
    }
}
