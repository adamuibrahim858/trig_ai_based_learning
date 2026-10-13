<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LearningRecommendationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'recommendation_type' => $this->recommendation_type,
            'reason' => $this->reason,
            'status' => $this->status,
            'topic' => $this->topic ? [
                'id' => $this->topic->id,
                'title' => $this->topic->title,
                'week_number' => $this->topic->week_number,
            ] : null,
            'lesson' => $this->lesson ? [
                'id' => $this->lesson->id,
                'title' => $this->lesson->title,
                'slug' => $this->lesson->slug,
            ] : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
