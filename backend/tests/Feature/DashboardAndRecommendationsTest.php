<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\LearningRecommendation;
use App\Models\LessonProgress;
use App\Models\Topic;
use App\Models\User;
use Database\Seeders\CourseCurriculumSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardAndRecommendationsTest extends TestCase
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

        CourseEnrollment::create([
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);
    }

    public function test_student_can_fetch_dashboard_data(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v1/dashboard');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.student.email', $this->student->email)
            ->assertJsonPath('data.course.slug', 'basic-trigonometry')
            ->assertJsonStructure([
                'data' => [
                    'student',
                    'course',
                    'progress_summary' => [
                        'total_topics',
                        'completed_topics',
                        'total_lessons',
                        'completed_lessons',
                        'completion_percentage',
                    ],
                    'next_recommended_lesson',
                    'recent_attempts',
                    'active_recommendations',
                    'certificate',
                ],
            ]);
    }

    public function test_student_can_list_and_dismiss_recommendations(): void
    {
        $topic = Topic::where('course_id', $this->course->id)->first();
        $rec = LearningRecommendation::create([
            'user_id' => $this->student->id,
            'topic_id' => $topic->id,
            'recommendation_type' => 'revision',
            'reason' => 'Review right-angle triangle definitions.',
            'status' => 'active',
        ]);

        // List recommendations
        $listRes = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v1/recommendations');

        $listRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.id', $rec->id)
            ->assertJsonPath('data.0.status', 'active');

        // Dismiss recommendation
        $dismissRes = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/v1/recommendations/{$rec->id}/dismiss");

        $dismissRes->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('learning_recommendations', [
            'id' => $rec->id,
            'status' => 'dismissed',
        ]);

        // List should now return empty active list
        $emptyListRes = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v1/recommendations');

        $emptyListRes->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }
}
