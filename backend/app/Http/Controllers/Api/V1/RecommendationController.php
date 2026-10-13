<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\LearningRecommendationResource;
use App\Models\LearningRecommendation;
use App\Services\Learning\PersonalizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecommendationController extends ApiController
{
    public function __construct(
        protected PersonalizationService $personalizationService
    ) {}

    /**
     * GET /api/v1/recommendations
     * List all active learning recommendations for the authenticated student.
     */
    public function index(Request $request): JsonResponse
    {
        $recommendations = $this->personalizationService->getRecommendations($request->user());

        return $this->success(
            LearningRecommendationResource::collection($recommendations)
        );
    }

    /**
     * POST /api/v1/recommendations/{id}/dismiss
     * Dismiss a specific recommendation.
     */
    public function dismiss(Request $request, int $id): JsonResponse
    {
        $recommendation = LearningRecommendation::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $this->personalizationService->dismiss($request->user(), $recommendation);

        return $this->success(
            null,
            'Recommendation dismissed successfully.'
        );
    }
}
