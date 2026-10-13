<?php

namespace App\Http\Resources;

use App\Services\Learning\LessonProgressionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LessonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $status = 'locked';
        $isAccessible = false;
        $reason = null;

        if ($user) {
            $service = app(LessonProgressionService::class);
            $check = $service->getLessonStatus($user, $this->resource);
            $status = $check['status'];
            $isAccessible = $check['is_accessible'];
            $reason = $check['reason'];
        }

        return [
            'id' => $this->id,
            'topic_id' => $this->topic_id,
            'title' => $this->title,
            'slug' => $this->slug,
            'summary' => $this->summary,
            // Only expose content if accessible
            'content' => $isAccessible ? $this->content : null,
            'learning_objectives' => $this->learning_objectives,
            'estimated_minutes' => $this->estimated_minutes,
            'order' => $this->order,
            'status' => $status,
            'is_accessible' => $isAccessible,
            'lock_reason' => $reason,
            'resources' => $this->resources->map(fn($r) => [
                'id' => $r->id,
                'type' => $r->type,
                'title' => $r->title,
                'description' => $r->description,
                'content_url' => $r->content_url,
            ]),
        ];
    }
}
