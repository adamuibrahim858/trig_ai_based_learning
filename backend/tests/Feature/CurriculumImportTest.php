<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\Test as CourseTest;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CurriculumImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_curriculum_import_command_is_fully_idempotent(): void
    {
        // 1. Run curriculum import command
        Artisan::call('curriculum:import');

        $initialCourses = Course::count();
        $initialTopics = Topic::count();
        $initialLessons = Lesson::count();
        $initialTests = CourseTest::count();
        $initialQuestions = Question::count();

        $this->assertEquals(1, $initialCourses);
        $this->assertEquals(8, $initialTopics);
        $this->assertEquals(38, $initialLessons);
        $this->assertEquals(9, $initialTests); // 8 weekly topic assessments + 1 final exam
        $this->assertEquals(74, $initialQuestions); // 58 weekly + 16 final exam

        // 2. Re-run import command a second time
        Artisan::call('curriculum:import');

        // 3. Verify counts are strictly identical (no duplicates created)
        $this->assertEquals($initialCourses, Course::count(), 'Courses count must be unchanged.');
        $this->assertEquals($initialTopics, Topic::count(), 'Topics count must be unchanged.');
        $this->assertEquals($initialLessons, Lesson::count(), 'Lessons count must be unchanged.');
        $this->assertEquals($initialTests, CourseTest::count(), 'Tests count must be unchanged.');
        $this->assertEquals($initialQuestions, Question::count(), 'Questions count must be unchanged.');
    }

    public function test_all_eight_weeks_have_correct_lesson_order_and_content(): void
    {
        Artisan::call('curriculum:import');

        for ($w = 1; $w <= 8; $w++) {
            $topic = Topic::where('week_number', $w)->first();
            $this->assertNotNull($topic, "Week {$w} must exist.");
            $this->assertNotEmpty($topic->description, "Week {$w} must have a description.");
            $this->assertNotEmpty($topic->learning_objectives, "Week {$w} must have learning objectives.");

            $lessons = $topic->lessons()->orderBy('order')->get();
            $this->assertGreaterThanOrEqual(3, $lessons->count(), "Week {$w} must contain at least 3 lessons.");

            // Verify lessons are in sequential order and contain valid markdown content
            foreach ($lessons as $idx => $lesson) {
                $this->assertEquals($idx + 1, $lesson->order, "Lesson {$lesson->slug} order must be sequential.");
                $this->assertNotEmpty($lesson->title, "Lesson must have a title.");
                $this->assertNotEmpty($lesson->summary, "Lesson must have a summary.");
                $this->assertNotEmpty($lesson->content, "Lesson content must not be empty.");
            }
        }
    }

    public function test_learner_can_access_imported_curriculum_through_api(): void
    {
        Artisan::call('curriculum:import');

        $student = User::factory()->create();
        $token = $student->createToken('test-token')->plainTextToken;
        $course = Course::first();

        // Enroll student
        CourseEnrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        // Fetch curriculum topics (TopicController::show for topic 1 with user)
        $topic = Topic::where('course_id', $course->id)->where('week_number', 1)->first();

        $res = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/topics/{$topic->id}");

        $res->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.week_number', 1)
            ->assertJsonPath('data.is_accessible', true);

        // First lesson of Week 1 must be accessible and return full content
        $firstLesson = $topic->lessons()->orderBy('order')->first();
        $lessonRes = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/lessons/{$firstLesson->id}");

        $lessonRes->assertStatus(200)
            ->assertJsonPath('data.is_accessible', true)
            ->assertJsonPath('data.title', 'Lesson 1.1: What is trigonometry?');

        $this->assertNotEmpty($lessonRes->json('data.content'));
    }

    public function test_assessment_answer_keys_are_masked_before_submission(): void
    {
        Artisan::call('curriculum:import');

        $student = User::factory()->create();
        $token = $student->createToken('test-token')->plainTextToken;
        $course = Course::first();

        CourseEnrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        $week1Assessment = CourseTest::where('type', 'topic_assessment')
            ->whereHas('topic', fn($q) => $q->where('week_number', 1))
            ->first();

        $this->assertNotNull($week1Assessment);

        // Start attempt: POST /api/v1/tests/{test}/attempts
        $startRes = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/tests/{$week1Assessment->id}/attempts");

        $startRes->assertStatus(201);
        $attemptId = $startRes->json('data.id');

        // Get attempt questions: GET /api/v1/attempts/{attempt}
        $attemptRes = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/attempts/{$attemptId}");

        $attemptRes->assertStatus(200);
        $questions = $attemptRes->json('data.questions');
        $this->assertNotEmpty($questions);

        foreach ($questions as $q) {
            $this->assertArrayNotHasKey('explanation', $q, 'Explanations must be masked prior to submission.');
            if (!empty($q['options'])) {
                foreach ($q['options'] as $opt) {
                    $this->assertArrayNotHasKey('is_correct', $opt, 'Option correctness must be masked prior to submission.');
                }
            }
        }
    }
}
