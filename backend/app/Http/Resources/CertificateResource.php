<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CertificateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'certificate_number' => $this->certificate_number,
            'course_id' => $this->course_id,
            'course_title' => $this->course?->title,
            'final_score' => $this->final_score,
            'issued_at' => $this->issued_at?->toIso8601String(),
            'verify_url' => url("/api/v1/certificates/verify/{$this->certificate_number}"),
            'download_url' => url("/api/v1/certificate"),
        ];
    }
}
