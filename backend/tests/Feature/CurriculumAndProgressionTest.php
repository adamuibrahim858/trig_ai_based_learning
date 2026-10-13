<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Lesson;
use App\Models\Topic;
use App\Models\User;
use Database\Seeders\CourseCurriculumSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurriculumAndProgressionTest extends TestCase
{
    use RefreshDatabase;

    protected User $student;
    protected string $token;
    protected Course $course;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CourseCurriculumSeeder::class);

        $this->student = User::factory()->create();
        $this->token = $this->student->createToken('test-token')->plainTextToken;
        $this->course = Course::first();
    }

    public function test_can_list_published_courses(): void
    {
        $response = $this->getJson('/api/v1/courses');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.slug', 'basic-trigonometry');
    }

    public function test_student_can_enroll_in_course(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/v1/courses/{$this->course->id}/enroll");

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('course_enrollments', [
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
            'status' => 'active',
        ]);
    }

    public function test_week_1_lesson_is_accessible_after_enrollment(): void
    {
        CourseEnrollment::create([
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        $week1Topic = Topic::where('course_id', $this->course->id)->where('week_number', 1)->first();
        $firstLesson = $week1Topic->lessons()->orderBy('order')->first();

        // Access lesson
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/v1/lessons/{$firstLesson->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $firstLesson->id)
            ->assertJsonPath('data.is_accessible', true);
    }

    public function test_student_can_start_and_complete_a_lesson(): void
    {
        CourseEnrollment::create([
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        $week1Topic = Topic::where('course_id', $this->course->id)->where('week_number', 1)->first();
        $firstLesson = $week1Topic->lessons()->orderBy('order')->first();

        // Start lesson
        $startRes = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/v1/lessons/{$firstLesson->id}/start");

        $startRes->assertStatus(200)
            ->assertJsonPath('data.status', 'in_progress');

        // Complete lesson
        $completeRes = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/v1/lessons/{$firstLesson->id}/complete");

        $completeRes->assertStatus(200)
            ->assertJsonPath('data.status', 'completed');

        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $this->student->id,
            'lesson_id' => $firstLesson->id,
            'status' => 'completed',
        ]);
    }

    public function test_week_2_lesson_is_locked_before_week_1_assessment_passed(): void
    {
        CourseEnrollment::create([
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        $week2Topic = Topic::where('course_id', $this->course->id)->where('week_number', 2)->first();
        $week2Lesson = $week2Topic->lessons()->orderBy('order')->first();

        // Attempting to view Week 2 lesson should mask content and indicate locked
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/v1/lessons/{$week2Lesson->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.is_accessible', false)
            ->assertJsonPath('data.status', 'locked')
            ->assertJsonPath('data.content', null);

        // Attempting to start Week 2 lesson must be rejected with 403 Forbidden
        $startResponse = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/v1/lessons/{$week2Lesson->id}/start");

        $startResponse->assertStatus(403);
    }
}
