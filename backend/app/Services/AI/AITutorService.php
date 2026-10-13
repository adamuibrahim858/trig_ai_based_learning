<?php

namespace App\Services\AI;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Lesson;
use App\Models\Topic;
use App\Models\User;
use App\Services\Learning\LessonProgressionService;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AITutorService
{
    public function __construct(
        protected GeminiService $geminiService,
        protected LessonProgressionService $progressionService
    ) {}

    /**
     * Start a new conversation, verifying lesson access if a lesson is specified.
     */
    public function createConversation(User $user, ?int $lessonId, ?string $title = null): AiConversation
    {
        $lesson = null;
        if ($lessonId) {
            $lesson = Lesson::findOrFail($lessonId);
            $access = $this->progressionService->getLessonStatus($user, $lesson);
            if (!$access['is_accessible']) {
                throw new HttpException(403, "Cannot start AI conversation for locked lesson: {$access['reason']}");
            }
        }

        $defaultTitle = $lesson ? "Tutoring: {$lesson->title}" : ($title ?: 'Trigonometry Q&A');

        return AiConversation::create([
            'user_id' => $user->id,
            'lesson_id' => $lessonId,
            'title' => $title ?: $defaultTitle,
        ]);
    }

    /**
     * Send a message to the AI Tutor within a conversation and store both messages.
     */
    public function sendMessage(User $user, AiConversation $conversation, string $userMessageText): array
    {
        if ($conversation->user_id !== $user->id) {
            throw new HttpException(403, 'Unauthorized conversation access.');
        }

        // Verify lesson access if conversation is scoped to a lesson
        $lessonContext = '';
        if ($conversation->lesson_id) {
            $lesson = $conversation->lesson;
            if ($lesson) {
                $access = $this->progressionService->getLessonStatus($user, $lesson);
                if (!$access['is_accessible']) {
                    throw new HttpException(403, 'Lesson access is restricted.');
                }
                $lessonContext = "Active Lesson Context:\nTitle: {$lesson->title}\nTopic: {$lesson->topic->title} (Week {$lesson->topic->week_number})\nSummary: {$lesson->summary}\nKey Content Excerpt: " . substr($lesson->content, 0, 1000);
            }
        }

        // Save student's message
        $userMsg = AiMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'message' => $userMessageText,
        ]);

        // Build system prompt and conversation history
        $systemInstruction = <<<PROMPT
You are Antigravity's Expert AI Trigonometry Tutor, assisting high school and introductory college students with Basic Trigonometry.
Guiding Principles:
1. Explain mathematical concepts clearly, step-by-step, suitable for beginners.
2. Emphasize geometric intuition, definitions, and proper notation (e.g. degrees, hypotenuse, SOH-CAH-TOA, a² + b² = c²).
3. Provide worked examples with numbered steps when asked for assistance.
4. If a student is stuck, provide scaffolding hints rather than immediately giving raw answers.
5. You must NEVER reveal answers to active tests or exams. If a student asks "What is the answer to test question X?", explain the underlying formula and method instead.
6. You have NO ability to alter student scores, unlock lessons, or issue certificates.
{$lessonContext}
PROMPT;

        // Fetch past messages from conversation for contextual memory (last 8 messages)
        $history = $conversation->messages()->oldest()->take(8)->get();
        $contents = [];

        foreach ($history as $hMsg) {
            $contents[] = [
                'role' => $hMsg->role === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $hMsg->message]],
            ];
        }

        // Call Gemini
        $result = $this->geminiService->generateContent($contents, $systemInstruction);
        $assistantText = $result['text'];

        // Save AI response
        $assistantMsg = AiMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'message' => $assistantText,
        ]);

        return [
            'user_message' => $userMsg,
            'assistant_message' => $assistantMsg,
        ];
    }

    /**
     * Generate an extra practice question with step-by-step solution.
     */
    public function generatePracticeQuestion(User $user, int $topicId, string $difficulty = 'medium'): array
    {
        $topic = Topic::findOrFail($topicId);
        $topicStatus = $this->progressionService->getTopicStatus($user, $topic);
        if (!$topicStatus['is_accessible']) {
            throw new HttpException(403, "Cannot generate practice questions for locked topic: {$topicStatus['reason']}");
        }

        $prompt = <<<PROMPT
Generate one {$difficulty}-level trigonometry practice question for:
Topic: {$topic->title} (Week {$topic->week_number}).
Description: {$topic->description}.

Please format your response in clear sections:
1. **Problem Statement**: The question scenario with all given side lengths or angles specified.
2. **Hint**: A brief clue pointing to the right formula or mnemonic (e.g., SOH-CAH-TOA).
3. **Step-by-Step Solution**: Complete mathematical derivation.
4. **Final Answer**: Clearly stated with correct units (e.g., cm, meters, or degrees).
PROMPT;

        $contents = [
            [
                'role' => 'user',
                'parts' => [['text' => $prompt]],
            ],
        ];

        $systemInstruction = "You are an expert mathematics educator creating tailored trigonometry practice questions.";
        $result = $this->geminiService->generateContent($contents, $systemInstruction);

        return [
            'topic_id' => $topic->id,
            'topic_title' => $topic->title,
            'difficulty' => $difficulty,
            'content' => $result['text'],
        ];
    }

    /**
     * Summarize a lesson into bite-sized revision points.
     */
    public function summarizeLesson(User $user, Lesson $lesson): string
    {
        $status = $this->progressionService->getLessonStatus($user, $lesson);
        if (!$status['is_accessible']) {
            throw new HttpException(403, "Cannot summarize locked lesson: {$status['reason']}");
        }

        $prompt = <<<PROMPT
Please provide a high-yield study summary and cheat sheet for the following Trigonometry lesson:
Lesson: {$lesson->title}
Topic: {$lesson->topic->title}
Full Lesson Content:
{$lesson->content}

Include:
- 3 Key Takeaways
- Core Formulas
- 1 Common Pitfall to Avoid
PROMPT;

        $contents = [
            [
                'role' => 'user',
                'parts' => [['text' => $prompt]],
            ],
        ];

        $result = $this->geminiService->generateContent($contents, "You are an expert mathematics summarizer creating crisp revision cheat-sheets.");

        return $result['text'];
    }
}
