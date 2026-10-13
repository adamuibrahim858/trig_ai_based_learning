<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\LessonProgress;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\TopicProgress;
use App\Models\User;
use Database\Seeders\CourseCurriculumSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificateTest extends TestCase
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

    public function test_student_ineligible_for_certificate_before_completing_course(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v1/certificates/eligibility');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.eligible', false);

        // Attempting to issue certificate must be rejected with 403 Forbidden
        $issueResponse = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/certificates');

        $issueResponse->assertStatus(403);
    }

    public function test_student_can_be_issued_certificate_upon_completing_all_requirements(): void
    {
        // 1. Mark all 8 topics completed (lessons completed + weekly assessments passed)
        foreach ($this->course->topics as $topic) {
            foreach ($topic->lessons as $lesson) {
                LessonProgress::create([
                    'user_id' => $this->student->id,
                    'lesson_id' => $lesson->id,
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);
            }

            $topicTest = Test::where('topic_id', $topic->id)->first();
            if ($topicTest) {
                TestAttempt::create([
                    'user_id' => $this->student->id,
                    'test_id' => $topicTest->id,
                    'score' => 80,
                    'total_points' => 100,
                    'percentage' => 80.0,
                    'passed' => true,
                    'status' => 'submitted',
                    'submitted_at' => now(),
                ]);
            }

            TopicProgress::create([
                'user_id' => $this->student->id,
                'topic_id' => $topic->id,
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        }

        // 2. Pass final exam with 85%
        $finalExam = Test::where('course_id', $this->course->id)->where('type', 'final_exam')->first();
        TestAttempt::create([
            'user_id' => $this->student->id,
            'test_id' => $finalExam->id,
            'score' => 85,
            'total_points' => 100,
            'percentage' => 85.0,
            'passed' => true,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        // 3. Check eligibility
        $eligibilityRes = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v1/certificates/eligibility');

        $eligibilityRes->assertStatus(200)
            ->assertJsonPath('data.eligible', true)
            ->assertJsonPath('data.final_score', 85);

        // 4. Issue certificate
        $issueRes = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/certificates');

        $issueRes->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.final_score', 85);

        $certNumber = $issueRes->json('data.certificate_number');
        $this->assertNotEmpty($certNumber);

        // 5. Test public verification (NO auth header required)
        $verifyRes = $this->getJson("/api/v1/certificates/verify/{$certNumber}");

        $verifyRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.student_name', $this->student->name)
            ->assertJsonPath('data.course_title', $this->course->title);

        // 6. Test PDF download
        $certId = $issueRes->json('data.id');
        $downloadRes = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->get("/api/v1/certificates/{$certId}/download");

        $downloadRes->assertStatus(200);
        $downloadRes->assertHeader('content-type', 'application/pdf');
    }

    public function test_public_verification_returns_404_for_invalid_certificate(): void
    {
        $response = $this->getJson('/api/v1/certificates/verify/INVALID-CERT-9999');

        $response->assertStatus(404)
            ->assertJsonPath('success', false);
    }
}
