<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\CourseResource;
use App\Models\Course;
use App\Models\CourseEnrollment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourseController extends ApiController
{
    /**
     * GET /api/v1/courses
     */
    public function index(Request $request): JsonResponse
    {
        $courses = Course::where('status', 'published')->get();

        return $this->success(CourseResource::collection($courses));
    }

    /**
     * GET /api/v1/courses/{course}
     */
    public function show(Request $request, Course $course): JsonResponse
    {
        return $this->success(new CourseResource($course));
    }

    /**
     * POST /api/v1/courses/{course}/enroll
     */
    public function enroll(Request $request, Course $course): JsonResponse
    {
        $user = $request->user();

        $existing = CourseEnrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->first();

        if ($existing) {
            if ($existing->status === 'active') {
                return $this->error('You are already enrolled in this course.', null, 409);
            }
            // Re-activate a dropped enrollment
            $existing->update(['status' => 'active', 'enrolled_at' => now()]);

            return $this->success([
                'enrollment_id' => $existing->id,
                'course_id' => $course->id,
                'status' => 'active',
            ], 'Re-enrolled in course successfully.');
        }

        $enrollment = CourseEnrollment::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'enrolled_at' => now(),
            'status' => 'active',
        ]);

        return $this->success([
            'enrollment_id' => $enrollment->id,
            'course_id' => $course->id,
            'status' => 'active',
        ], 'Enrolled in course successfully.', 201);
    }
}
