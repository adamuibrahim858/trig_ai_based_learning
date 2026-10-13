<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\TopicResource;
use App\Models\Course;
use App\Models\Topic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TopicController extends ApiController
{
    /**
     * GET /api/v1/courses/{course}/topics
     */
    public function index(Request $request, Course $course): JsonResponse
    {
        $topics = $course->topics()->with('lessons')->orderBy('week_number')->get();

        return $this->success(TopicResource::collection($topics));
    }

    /**
     * GET /api/v1/topics/{topic}
     */
    public function show(Request $request, Topic $topic): JsonResponse
    {
        $topic->load('lessons.resources');

        return $this->success(new TopicResource($topic));
    }
}
