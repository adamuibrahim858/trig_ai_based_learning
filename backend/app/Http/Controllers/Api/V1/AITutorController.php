<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\AiConversationResource;
use App\Http\Resources\AiMessageResource;
use App\Models\AiConversation;
use App\Models\Lesson;
use App\Services\AI\AITutorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AITutorController extends ApiController
{
    public function __construct(
        protected AITutorService $tutorService
    ) {}

    /**
     * GET /api/v1/ai/conversations
     * List conversations for authenticated student.
     */
    public function index(Request $request): JsonResponse
    {
        $conversations = AiConversation::with('lesson:id,title')
            ->where('user_id', $request->user()->id)
            ->latest('updated_at')
            ->paginate(15);

        return $this->success(
            AiConversationResource::collection($conversations)->response()->getData(true)
        );
    }

    /**
     * POST /api/v1/ai/conversations
     * Start a new tutoring conversation.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lesson_id' => ['nullable', 'integer', 'exists:lessons,id'],
            'title' => ['nullable', 'string', 'max:120'],
        ]);

        $conversation = $this->tutorService->createConversation(
            $request->user(),
            $validated['lesson_id'] ?? null,
            $validated['title'] ?? null
        );

        return $this->success(
            new AiConversationResource($conversation),
            'AI conversation created successfully.',
            201
        );
    }

    /**
     * GET /api/v1/ai/conversations/{id}
     * Get conversation and its messages.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $conversation = AiConversation::with(['lesson:id,title', 'messages' => fn($q) => $q->oldest()])
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        return $this->success(
            new AiConversationResource($conversation)
        );
    }

    /**
     * DELETE /api/v1/ai/conversations/{id}
     * Delete a conversation.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $conversation = AiConversation::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $conversation->delete();

        return $this->success(null, 'Conversation deleted successfully.');
    }

    /**
     * POST /api/v1/ai/conversations/{id}/messages
     * Send a student query to the AI Tutor and receive a step-by-step guidance response.
     */
    public function sendMessage(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'min:2', 'max:2000'],
        ]);

        $conversation = AiConversation::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $result = $this->tutorService->sendMessage(
            $request->user(),
            $conversation,
            $validated['message']
        );

        return $this->success([
            'user_message' => new AiMessageResource($result['user_message']),
            'assistant_message' => new AiMessageResource($result['assistant_message']),
        ], 'Response generated successfully.');
    }

    /**
     * POST /api/v1/ai/practice
     * Request an AI-generated practice question with step-by-step solution.
     */
    public function practiceQuestion(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'topic_id' => ['required', 'integer', 'exists:topics,id'],
            'difficulty' => ['nullable', 'string', Rule::in(['easy', 'medium', 'hard'])],
        ]);

        $result = $this->tutorService->generatePracticeQuestion(
            $request->user(),
            $validated['topic_id'],
            $validated['difficulty'] ?? 'medium'
        );

        return $this->success($result, 'Practice question generated successfully.');
    }

    /**
     * POST /api/v1/ai/lessons/{lessonId}/summarize
     * Request an AI summary cheat-sheet for a lesson.
     */
    public function summarizeLesson(Request $request, int $lessonId): JsonResponse
    {
        $lesson = Lesson::findOrFail($lessonId);

        $summary = $this->tutorService->summarizeLesson($request->user(), $lesson);

        return $this->success([
            'lesson_id' => $lesson->id,
            'lesson_title' => $lesson->title,
            'summary' => $summary,
        ], 'Lesson summarized successfully.');
    }
}
