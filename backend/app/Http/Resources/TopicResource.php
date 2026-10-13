<?php

namespace App\Http\Resources;

use App\Services\Learning\LessonProgressionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TopicResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $status = 'locked';
        $isAccessible = false;
        $reason = null;

        if ($user) {
            $service = app(LessonProgressionService::class);
            $check = $service->getTopicStatus($user, $this->resource);
            $status = $check['status'];
            $isAccessible = $check['is_accessible'];
            $reason = $check['reason'];
        }

        $assessment = $this->assessment;

        return [
            'id' => $this->id,
            'course_id' => $this->course_id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'week_number' => $this->week_number,
            'learning_objectives' => $this->learning_objectives,
            'order' => $this->order,
            'status' => $status,
            'is_accessible' => $isAccessible,
            'lock_reason' => $reason,
            'assessment_id' => $assessment?->id,
            'lessons' => LessonResource::collection($this->whenLoaded('lessons')),
        ];
    }
}
