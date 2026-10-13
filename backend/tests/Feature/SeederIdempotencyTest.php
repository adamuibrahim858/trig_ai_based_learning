<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\Test as CourseTest;
use App\Models\Topic;
use Database\Seeders\CourseCurriculumSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeederIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_is_fully_idempotent(): void
    {
        // 1. Run seeder first time
        $this->seed(CourseCurriculumSeeder::class);

        $initialCourses = Course::count();
        $initialTopics = Topic::count();
        $initialLessons = Lesson::count();
        $initialTests = CourseTest::count();
        $initialQuestions = Question::count();

        $this->assertEquals(1, $initialCourses);
        $this->assertEquals(8, $initialTopics);
        $this->assertEquals(16, $initialLessons);
        $this->assertEquals(9, $initialTests); // 8 topic assessments + 1 final exam
        $this->assertGreaterThanOrEqual(96, $initialQuestions);

        // 2. Run seeder a second time
        $this->seed(CourseCurriculumSeeder::class);

        // 3. Verify exact counts are unchanged (no duplicates created)
        $this->assertEquals($initialCourses, Course::count(), 'Courses count must remain identical after re-running seeder.');
        $this->assertEquals($initialTopics, Topic::count(), 'Topics count must remain identical after re-running seeder.');
        $this->assertEquals($initialLessons, Lesson::count(), 'Lessons count must remain identical after re-running seeder.');
        $this->assertEquals($initialTests, CourseTest::count(), 'Tests count must remain identical after re-running seeder.');
        $this->assertEquals($initialQuestions, Question::count(), 'Questions count must remain identical after re-running seeder.');
    }
}
