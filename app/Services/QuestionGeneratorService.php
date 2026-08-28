<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class QuestionGeneratorService
{
    protected GroqService $groq;

    public ?string $lastError = null;

    public function __construct(GroqService $groq)
    {
        $this->groq = $groq;
    }

    public function isConfigured(): bool
    {
        return $this->groq->isConfigured();
    }

    public function generate(string $topic, string $difficulty, string $category = null, int $count = 1): ?array
    {
        $this->lastError = null;

        if (!$this->groq->isConfigured()) {
            $this->lastError = 'not_configured';
            return null;
        }

        if ($count < 1 || $count > 10) {
            $count = 1;
        }

        $difficultyLabel = match ($difficulty) {
            'mudah' => 'Mudah',
            'sedang' => 'Sedang',
            'sulit' => 'Sulit',
            default => 'Sedang',
        };

        $prompt = $this->buildPrompt($topic, $difficultyLabel, $category, $count);

        // openai/gpt-oss-120b punya limit TPM 8000 token (gratis). max_tokens + input prompt
        // harus tetap di bawah batas itu agar tidak error 413 "Request too large".
        $maxTokens = match (true) {
            $count <= 2  => 3500,
            $count <= 5  => 5000,
            default      => 6000,
        };
        $result = $this->groq->chatJson([
            ['role' => 'system', 'content' => 'Anda adalah asisten pembuat soal ujian yang ahli dalam pendidikan. Anda membuat soal pilihan ganda berkualitas tinggi dalam Bahasa Indonesia. Output harus JSON. Buat soal SESUAI topik yang diminta, JANGAN membuat soal di luar topik yang diberikan.'],
            ['role' => 'user', 'content' => $prompt],
        ], $maxTokens);

        if (!$result || !isset($result['questions']) || !is_array($result['questions'])) {
            $this->lastError = $this->groq->lastError ?? 'invalid_response';
            Log::warning('QuestionGeneratorService: invalid response', [
                'groq_error' => $this->groq->lastError,
                'result' => $result,
            ]);
            return null;
        }

        $questions = [];
        $failedCount = 0;
        foreach ($result['questions'] as $i => $q) {
            $normalized = $this->normalizeQuestion($q, $difficulty);
            if ($normalized !== null) {
                $questions[] = $normalized;
            } else {
                $failedCount++;
                Log::warning('QuestionGeneratorService: question ' . $i . ' failed normalization', [
                    'raw' => $q,
                ]);
            }
        }

        if (empty($questions)) {
            $this->lastError = 'validation_failed';
            Log::warning('QuestionGeneratorService: all ' . $failedCount . ' questions failed validation');
            return null;
        }

        return $questions;
    }

    protected function buildPrompt(string $topic, string $difficulty, ?string $category, int $count): string
    {
        $categoryHint = $category ? " (kategori: {$category})" : '';

        return <<<PROMPT
Buatlah {$count} soal pilihan ganda {$difficulty}{$categoryHint} dengan topik "{$topic}".

PENTING: 
- Rumus matematika WAJIB pakai delimiter $...$ (inline) atau $$...$$ (display)
- Kode program WAJIB dibungkus triple backtick dengan nama bahasa (```javascript...```)
- KONTEN SOAL harus SESUAI dengan topik yang diminta, JANGAN buat soal di luar topik

CONTOH format JSON (soal ini HANYA contoh struktur, isi soal harus sesuai topik):

{
  \"questions\": [
    {
      \"content\": \"Apa ibukota Indonesia?\",
      \"explanation\": \"Jakarta adalah ibukota Indonesia sejak 1945.\",
      \"options\": [
        {\"content\": \"Jakarta\", \"is_correct\": true},
        {\"content\": \"Bandung\", \"is_correct\": false},
        {\"content\": \"Surabaya\", \"is_correct\": false},
        {\"content\": \"Yogyakarta\", \"is_correct\": false}
      ]
    }
  ]
}

Ketentuan:
- Tepat {$count} soal dalam array "questions"
- Tiap soal: tepat 4 opsi pilihan (A, B, C, D)
- Tepat 1 opsi dengan is_correct: true (kunci jawaban) per soal
- Soal sesuai dengan tingkat {$difficulty}
- Gunakan Bahasa Indonesia yang baik dan benar
- Pembahasan menjelaskan mengapa jawaban benar
- Semua rumus matematika WAJIB menggunakan delimiter $..$ atau $$..$$
- Kode program (HTML, CSS, JavaScript, PHP, Python, dll) WAJIB dibungkus triple backtick dengan nama bahasa:
  ```html
  <div class="contoh">Teks</div>
  ```
- Di dalam string JSON, setiap baris baru ditulis sebagai \\n (SATU backslash + huruf n). JANGAN menulis \\\\n.
  Contoh yang benar:
  "Perhatikan kode Python berikut:\\n```python\\ndef f(x):\\n    return x * 2\\n```"
- Aturan double backslash HANYA untuk rumus matematika LaTeX, contoh: \\\\int (menjadi \\int setelah parsing JSON). JANGAN menerapkannya ke baris baru atau kode program.
- Contoh penulisan rumus: \$\\\\int x^2 dx\$, \$\\\\frac{dy}{dx}\$, \$\\\\lim_{x \\\\to 0} \\\\frac{\\\\sin x}{x}\$
PROMPT;
    }

    protected function normalizeQuestion(array $question, string $difficulty): ?array
    {
        $content = trim($this->fixEscapedNewlines($question['content'] ?? ''));
        $explanation = trim($this->fixEscapedNewlines($question['explanation'] ?? ''));
        $options = $question['options'] ?? [];

        if ($content === '' || count($options) !== 4) {
            Log::warning('QuestionGeneratorService: invalid question structure', $question);
            return null;
        }

        $normalizedOptions = [];
        $hasCorrect = false;

        foreach ($options as $option) {
            $optContent = trim($this->fixEscapedNewlines($option['content'] ?? ''));
            $isCorrect = !empty($option['is_correct']);
            if ($isCorrect) {
                $hasCorrect = true;
            }

            $normalizedOptions[] = [
                'content' => $optContent,
                'is_correct' => $isCorrect,
            ];
        }

        if (count($normalizedOptions) !== 4 || !$hasCorrect) {
            Log::warning('QuestionGeneratorService: invalid options', [
                'count' => count($normalizedOptions),
                'has_correct' => $hasCorrect,
            ]);
            return null;
        }

        return [
            'content' => $this->wrapLatex($content),
            'category' => $difficulty,
            'explanation' => $this->wrapLatex($explanation),
            'options' => array_map(fn($o) => [
                'content' => $this->wrapLatex($o['content']),
                'is_correct' => $o['is_correct'],
            ], $normalizedOptions),
        ];
    }

    private function fixEscapedNewlines(string $text): string
    {
        return str_replace('\\n', "\n", $text);
    }

    private function wrapLatex(string $text): string
    {
        if (preg_match('/\\\\\(|\\\\\[/', $text) || str_contains($text, '$')) {
            return $text;
        }

        $result = '';
        $len = strlen($text);
        $i = 0;
        while ($i < $len) {
            if ($text[$i] === '(') {
                $depth = 1;
                $j = $i + 1;
                while ($j < $len && $depth > 0) {
                    if ($text[$j] === '(') $depth++;
                    elseif ($text[$j] === ')') $depth--;
                    $j++;
                }
                $inner = substr($text, $i + 1, $j - $i - 2);
                if ($this->containsMath($inner)) {
                    $result .= '\(' . $inner . '\)';
                } else {
                    $result .= substr($text, $i, $j - $i);
                }
                $i = $j;
            } else {
                $result .= $text[$i];
                $i++;
            }
        }

        if ($result === $text && preg_match('/\\\\[a-zA-Z]+/', $text) && strlen($text) < 60 && preg_match('/^\\\\[a-zA-Z]+/', trim($text))) {
            return '$' . $text . '$';
        }

        return $result;
    }

    private function containsMath(string $s): bool
    {
        $s = trim($s);
        if ($s === '') return false;
        if (preg_match('/\\\\[a-zA-Z]+/', $s)) return true;
        if (preg_match('/\d+\s*[-+*\/^=≈]|[-+*\/^=≈]\s*\d+/', $s)) return true;
        return false;
    }
}
