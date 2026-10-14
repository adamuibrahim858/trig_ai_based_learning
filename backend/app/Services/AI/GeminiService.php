<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class GeminiService
{
    protected ?string $apiKey;
    protected string $model;
    protected string $baseUrl;

    /**
     * Models ordered by preference for fallback resilience.
     *
     * @var array<string>
     */
    protected array $fallbackModels = [
        'gemini-3.5-flash',
        'gemini-3.8-flash',
        'gemini-2.5-pro',
        'gemini-flash-latest',
    ];

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key') ?: env('GEMINI_API_KEY');
        $this->model = config('services.gemini.model', 'gemini-3.5-flash');
        $this->baseUrl = config('services.gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta');
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Generate content from Google Gemini with automated model fallback and resilient error handling.
     *
     * @param array<array{role: string, parts: array<array{text: string}>}> $contents
     * @param string|null $systemInstruction
     * @return array{text: string, raw: array}
     */
    public function generateContent(array $contents, ?string $systemInstruction = null): array
    {
        if (!$this->isConfigured()) {
            return [
                'text' => "AI Tutor Note: The Gemini API key is not configured in the backend (.env GEMINI_API_KEY). In the meantime, remember the core trigonometry rule: for any right-angled triangle, SOH-CAH-TOA governs the ratios, and a² + b² = c² relates the sides!",
                'raw' => ['mock' => true],
            ];
        }

        // Build list of models to try starting with the configured model
        $modelsToTry = array_unique(array_merge([$this->model], $this->fallbackModels));

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

        $lastError = 'Unknown error';

        foreach ($modelsToTry as $candidateModel) {
            $url = "{$this->baseUrl}/models/{$candidateModel}:generateContent?key={$this->apiKey}";

            try {
                $response = Http::timeout(25)
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                    ])
                    ->post($url, $payload);

                if ($response->successful()) {
                    $json = $response->json();
                    $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';

                    if (!empty(trim($text))) {
                        return [
                            'text' => trim($text),
                            'raw' => $json,
                        ];
                    }
                }

                $status = $response->status();
                $lastError = $response->json('error.message') ?? "HTTP {$status}";
                Log::warning("Gemini model {$candidateModel} returned status [{$status}]: {$lastError}. Trying fallback model if available...");

                // If 400 or 403 (bad API key), no point trying other models with same key
                if ($status === 400 && str_contains(strtolower($lastError), 'api key not valid')) {
                    break;
                }
            } catch (Throwable $e) {
                $lastError = $e->getMessage();
                Log::warning("Gemini connection issue on model {$candidateModel}: {$lastError}");
            }
        }

        // Graceful educational fallback if all models fail
        Log::error("All Gemini API models failed or upstream service unavailable. Last error: {$lastError}");

        return [
            'text' => "Hello! The AI Tutor service is momentarily experiencing high demand or network constraints. Let's keep your trigonometry momentum going:\n\n" .
                      "• **SOH-CAH-TOA**: $\\sin(\\theta) = \\frac{\\text{Opp}}{\\text{Hyp}}$, $\\cos(\\theta) = \\frac{\\text{Adj}}{\\text{Hyp}}$, $\\tan(\\theta) = \\frac{\\text{Opp}}{\\text{Adj}}$\n" .
                      "• **Pythagorean Theorem**: $a^2 + b^2 = c^2$\n" .
                      "• **Unit Circle**: Angles at $0^\\circ, 30^\\circ, 45^\\circ, 60^\\circ, 90^\\circ$ form the fundamental coordinates $(\\cos \\theta, \\sin \\theta)$.\n\n" .
                      "Please try sending your message again in a few moments!",
            'raw' => ['fallback' => true, 'last_error' => $lastError],
        ];
    }
}
