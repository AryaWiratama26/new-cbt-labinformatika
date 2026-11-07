<?php

namespace Tests\Unit;

use App\Models\Question;
use App\Services\AiCodeGrader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\InteractsWithExamFixtures;
use Tests\TestCase;

class AiCodeGraderTest extends TestCase
{
    use InteractsWithExamFixtures;
    use RefreshDatabase;

    private function grader(): AiCodeGrader
    {
        return app(AiCodeGrader::class);
    }

    private function groqOk(mixed $content): void
    {
        Http::fake([
            'api.groq.com/*' => Http::response(['choices' => [['message' => ['content' => $content]]]]),
        ]);
    }

    private function pythonQuestion(): Question
    {
        [, $module] = $this->makeCourseWithModule();

        return $this->makeCodingQuestion($module, 'python');
    }

    public function test_grades_valid_ai_response(): void
    {
        $this->groqOk('{"score": 85, "feedback": "Logika benar, kurang edge case."}');

        $result = $this->grader()->grade($this->pythonQuestion(), 'print(1)');

        $this->assertSame(85.0, $result['score']);
        $this->assertSame('Logika benar, kurang edge case.', $result['feedback']);
        $this->assertNull($result['error']);

        Http::assertSent(fn ($request) => $request['temperature'] === 0.1
            && $request['response_format'] === ['type' => 'json_object']);
    }

    public function test_clamps_score_above_hundred(): void
    {
        $this->groqOk('{"score": 150, "feedback": "x"}');

        $this->assertSame(100.0, $this->grader()->grade($this->pythonQuestion(), 'code')['score']);
    }

    public function test_clamps_score_below_zero(): void
    {
        $this->groqOk('{"score": -20, "feedback": "x"}');

        $this->assertSame(0.0, $this->grader()->grade($this->pythonQuestion(), 'code')['score']);
    }

    public function test_rejects_non_numeric_score(): void
    {
        $this->groqOk('{"score": "bagus", "feedback": "x"}');

        $result = $this->grader()->grade($this->pythonQuestion(), 'code');

        $this->assertNull($result['score']);
        $this->assertSame('invalid_response', $result['error']);
    }

    public function test_handles_malformed_json(): void
    {
        $this->groqOk('bukan json');

        $result = $this->grader()->grade($this->pythonQuestion(), 'code');

        $this->assertNull($result['score']);
        $this->assertSame('parse_error', $result['error']);
    }

    public function test_maps_rate_limit(): void
    {
        Http::fake(['api.groq.com/*' => Http::response('limit', 429)]);

        $result = $this->grader()->grade($this->pythonQuestion(), 'code');

        $this->assertNull($result['score']);
        $this->assertSame('rate_limit', $result['error']);
    }

    public function test_rejects_empty_code_without_http_call(): void
    {
        Http::fake();

        $result = $this->grader()->grade($this->pythonQuestion(), '   ');

        $this->assertNull($result['score']);
        $this->assertSame('empty_code', $result['error']);
        Http::assertNothingSent();
    }

    public function test_reports_unconfigured_api_key(): void
    {
        config(['services.groq.api_key' => null]);
        Http::fake();

        $result = $this->grader()->grade($this->pythonQuestion(), 'code');

        $this->assertNull($result['score']);
        $this->assertSame('not_configured', $result['error']);
        Http::assertNothingSent();
    }
}
