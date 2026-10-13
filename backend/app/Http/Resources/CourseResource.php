<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isEnrolled = false;
        if ($user) {
            $isEnrolled = $this->enrollments()->where('user_id', $user->id)->where('status', 'active')->exists();
        }

        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'learning_objectives' => $this->learning_objectives,
            'status' => $this->status,
            'is_enrolled' => $isEnrolled,
            'topics_count' => $this->topics()->count(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
