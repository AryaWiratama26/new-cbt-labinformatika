<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GroqService
{
    protected string $apiKey;
    protected string $baseUrl;
    protected string $model;

    public ?string $lastError = null;

    public function __construct()
    {
        $this->apiKey = config('services.groq.api_key', '');
        $this->baseUrl = config('services.groq.base_url', 'https://api.groq.com/openai/v1');
        $this->model = config('services.groq.model', 'openai/gpt-oss-120b');
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    public function chatJson(array $messages, int $maxTokens = 8192): ?array
    {
        $this->lastError = null;

        if (!$this->isConfigured()) {
            $this->lastError = 'not_configured';
            Log::warning('GroqService: API key not configured');
            return null;
        }

        try {
            $response = Http::timeout(60)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post($this->baseUrl . '/chat/completions', [
                    'model' => $this->model,
                    'messages' => $messages,
                    'temperature' => 0.7,
                    'max_tokens' => $maxTokens,
                    'response_format' => ['type' => 'json_object'],
                ]);

            if (!$response->successful()) {
                $status = $response->status();
                $body = $response->body();
                Log::error('GroqService: API error', ['status' => $status, 'body' => $body]);

                if ($status === 429) {
                    $this->lastError = 'rate_limit';
                } elseif ($status === 401) {
                    $this->lastError = 'unauthorized';
                } else {
                    $this->lastError = 'api_error';
                }
                return null;
            }

            $data = $response->json();
            $content = $data['choices'][0]['message']['content'] ?? null;

            if (!$content) {
                $this->lastError = 'empty_response';
                Log::error('GroqService: empty response content');
                return null;
            }

            $parsed = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->lastError = 'parse_error';
                Log::error('GroqService: failed to parse JSON response', [
                    'error' => json_last_error_msg(),
                ]);
                return null;
            }

            return $parsed;
        } catch (\Exception $e) {
            $this->lastError = 'network_error';
            Log::error('GroqService: request failed', [
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
