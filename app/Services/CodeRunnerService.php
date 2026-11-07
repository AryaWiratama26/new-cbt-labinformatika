<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CodeRunnerService
{
    protected string $baseUrl;

    protected int $timeout;

    /**
     * @var array<string, string>
     */
    protected array $compilers;

    public ?string $lastError = null;

    private const MAX_OUTPUT_LENGTH = 10000;

    public function __construct()
    {
        $this->baseUrl = (string) config('services.wandbox.base_url', 'https://wandbox.org/api');
        $this->timeout = (int) config('services.wandbox.timeout', 20);
        $this->compilers = (array) config('services.wandbox.compilers', []);
    }

    public function isSupported(string $language): bool
    {
        return array_key_exists($language, $this->compilers);
    }

    /**
     * Run the given source code through the Wandbox compiler sandbox.
     *
     * @return array{ok: bool, compile_output: string, output: string, stdout: string, stderr: string, needs_input: bool, error: ?string}
     */
    public function run(string $language, string $code, string $stdin = '', ?int $timeout = null): array
    {
        $this->lastError = null;

        if (! $this->isSupported($language)) {
            $this->lastError = 'unsupported_language';

            return [
                'ok' => false,
                'compile_output' => '',
                'output' => '',
                'stdout' => '',
                'stderr' => '',
                'needs_input' => false,
                'error' => 'Language is not supported for execution.',
            ];
        }

        try {
            $response = Http::timeout($timeout ?? $this->timeout)
                ->acceptJson()
                ->asJson()
                ->post($this->baseUrl.'/compile.json', [
                    'compiler' => $this->compilers[$language],
                    'code' => $code,
                    'stdin' => $stdin,
                ]);

            if (! $response->successful()) {
                $this->lastError = 'sandbox_error';
                Log::error('CodeRunnerService: sandbox error', [
                    'status' => $response->status(),
                    'language' => $language,
                ]);

                return [
                    'ok' => false,
                    'compile_output' => '',
                    'output' => '',
                    'stdout' => '',
                    'stderr' => '',
                    'needs_input' => false,
                    'error' => 'Code sandbox is unavailable right now. Please try again.',
                ];
            }

            $data = $response->json() ?? [];

            $compileOutput = $this->truncate(
                $data['compiler_message'] ?? trim(($data['compiler_output'] ?? '').($data['compiler_error'] ?? ''))
            );

            $stdout = (string) ($data['program_output'] ?? '');
            $stderr = (string) ($data['program_error'] ?? '');
            $programMessage = (string) ($data['program_message'] ?? '');

            if ($programMessage === '') {
                $programMessage = $stdout.$stderr;
            }

            if ($stdout === '' && $stderr === '') {
                $stdout = $programMessage;
            }

            $needsInput = $this->isWaitingForInput($stderr !== '' ? $stderr : $programMessage);

            $programOutput = $this->truncate($needsInput ? trim($stdout) : $programMessage);

            $status = (string) ($data['status'] ?? '1');

            return [
                'ok' => $status === '0',
                'compile_output' => $compileOutput,
                'output' => $programOutput,
                'stdout' => $this->truncate($stdout),
                'stderr' => $this->truncate($stderr),
                'needs_input' => $needsInput,
                'error' => null,
            ];
        } catch (\Exception $e) {
            $this->lastError = 'network_error';
            Log::error('CodeRunnerService: request failed', [
                'message' => $e->getMessage(),
                'language' => $language,
            ]);

            return [
                'ok' => false,
                'compile_output' => '',
                'output' => '',
                'stdout' => '',
                'stderr' => '',
                'needs_input' => false,
                'error' => 'Could not reach the code sandbox. Please try again.',
            ];
        }
    }

    /**
     * Detect the common "program is blocked waiting for stdin" states so the UI
     * can prompt the student instead of showing a raw stack trace.
     */
    private function isWaitingForInput(string $text): bool
    {
        if ($text === '') {
            return false;
        }

        return (bool) preg_match(
            '/(EOFError|EOF when reading|end of file|EndOfFileException|EOFException|NoSuchElementException|No line found)/i',
            $text
        );
    }

    private function truncate(string $text): string
    {
        if (mb_strlen($text) <= self::MAX_OUTPUT_LENGTH) {
            return $text;
        }

        return mb_substr($text, 0, self::MAX_OUTPUT_LENGTH)."\n... (output truncated)";
    }
}
