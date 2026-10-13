<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'course_id' => $this->course_id,
            'topic_id' => $this->topic_id,
            'lesson_id' => $this->lesson_id,
            'title' => $this->title,
            'description' => $this->description,
            'type' => $this->type,
            'pass_mark' => $this->pass_mark,
            'total_marks' => $this->total_marks,
            'duration_minutes' => $this->duration_minutes,
            'attempts_allowed' => $this->attempts_allowed,
            'questions_count' => $this->questions()->count(),
            'questions' => QuestionResource::collection($this->whenLoaded('questions')),
        ];
    }
}
