<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GeminiService
{
    protected ?string $apiKey;
    protected string $model;
    protected string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key') ?: env('GEMINI_API_KEY');
        $this->model = config('services.gemini.model', 'gemini-1.5-flash');
        $this->baseUrl = config('services.gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta');
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Generate content from Google Gemini.
     *
     * @param array<array{role: string, parts: array<array{text: string}>}> $contents
     * @param string|null $systemInstruction
     * @return array{text: string, raw: array}
     */
    public function generateContent(array $contents, ?string $systemInstruction = null): array
    {
        if (!$this->isConfigured()) {
            // When API key is not configured, return an educational fallback instead of crashing the app
            return [
                'text' => "AI Tutor Note: The Gemini API key is currently not configured in the backend (.env GEMINI_API_KEY). Please add your key to enable live AI responses. In the meantime, remember the core rule: for any right-angled triangle, SOH-CAH-TOA governs the ratios, and a² + b² = c² relates the sides!",
                'raw' => ['mock' => true],
            ];
        }

        $url = "{$this->baseUrl}/models/{$this->model}:generateContent?key={$this->apiKey}";

        $payload = [
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => 0.4,
                'topP' => 0.95,
                'maxOutputTokens' => 1500,
            ],
        ];

        if ($systemInstruction) {
            $payload['systemInstruction'] = [
                'parts' => [
                    ['text' => $systemInstruction],
                ],
            ];
        }

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->post($url, $payload);

            if ($response->failed()) {
                $status = $response->status();
                $errorBody = $response->json('error.message') ?? 'Unknown Gemini API error';

                // Securely log without revealing API key
                Log::error("Gemini API Error [{$status}]: {$errorBody}");

                if ($status === 429) {
                    throw new RuntimeException("AI Tutor rate limit exceeded. Please wait a moment before trying again.");
                }

                if ($status === 400 || $status === 403) {
                    throw new RuntimeException("Gemini authentication or parameter error: {$errorBody}");
                }

                throw new RuntimeException("The AI Tutor service encountered an error ({$status}). Please try again shortly.");
            }

            $json = $response->json();
            $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';

            return [
                'text' => trim($text),
                'raw' => $json,
            ];
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error("Gemini API Connection Timeout or Network Failure: " . $e->getMessage());
            throw new RuntimeException("Unable to contact the AI Tutor service at this time. Please check your internet connection.");
        }
    }
}
