<?php

namespace Tests\Unit;

use App\Services\CodeRunnerService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CodeRunnerServiceTest extends TestCase
{
    private function service(): CodeRunnerService
    {
        return new CodeRunnerService;
    }

    public function test_runs_a_supported_language_and_returns_output(): void
    {
        Http::fake(['wandbox.org/*' => Http::response([
            'status' => '0',
            'program_message' => "hi\n",
        ])]);

        $result = $this->service()->run('python', 'print("hi")', 'Alice');

        $this->assertTrue($result['ok']);
        $this->assertSame("hi\n", $result['output']);
        $this->assertNull($result['error']);

        Http::assertSent(fn ($request) => $request['compiler'] === 'cpython-3.12.7'
            && $request['stdin'] === 'Alice');
    }

    public function test_flags_when_the_program_is_waiting_for_stdin(): void
    {
        Http::fake(['wandbox.org/*' => Http::response([
            'status' => '1',
            'program_output' => 'Masukan Sisi: ',
            'program_error' => "Traceback (most recent call last):\n  File \"/home/wandbox/prog.py\", line 1, in <module>\n    sisi = int(input(\"Masukan Sisi: \"))\nEOFError: EOF when reading a line",
            'program_message' => "Traceback (most recent call last):\nEOFError: EOF when reading a line\nMasukan Sisi: ",
        ])]);

        $result = $this->service()->run('python', 'sisi = int(input("Masukan Sisi: "))');

        $this->assertTrue($result['needs_input']);
        $this->assertStringNotContainsString('EOFError', $result['output']);
        $this->assertSame('Masukan Sisi:', $result['output']);
        $this->assertStringContainsString('EOFError', $result['stderr']);
    }

    public function test_does_not_flag_a_normal_run_as_waiting_for_input(): void
    {
        Http::fake(['wandbox.org/*' => Http::response([
            'status' => '0',
            'program_output' => "25\n",
            'program_message' => "25\n",
        ])]);

        $result = $this->service()->run('python', 'print(25)', '5');

        $this->assertFalse($result['needs_input']);
        $this->assertSame("25\n", $result['output']);
    }

    public function test_returns_compile_output_when_status_is_non_zero(): void
    {
        Http::fake(['wandbox.org/*' => Http::response([
            'status' => '1',
            'compiler_message' => 'syntax error',
            'program_message' => '',
        ])]);

        $result = $this->service()->run('cpp', 'int main() {}');

        $this->assertFalse($result['ok']);
        $this->assertSame('syntax error', $result['compile_output']);
    }

    public function test_unsupported_language_is_rejected_without_an_http_call(): void
    {
        Http::fake();

        $service = $this->service();
        $result = $service->run('cobol', 'DISPLAY "hi".');

        $this->assertFalse($result['ok']);
        $this->assertSame('unsupported_language', $service->lastError);
        Http::assertNothingSent();
    }

    public function test_network_failure_is_reported(): void
    {
        Http::fake(['wandbox.org/*' => fn () => throw new ConnectionException('timeout')]);

        $service = $this->service();
        $result = $service->run('python', 'print(1)');

        $this->assertFalse($result['ok']);
        $this->assertSame('network_error', $service->lastError);
        $this->assertNotNull($result['error']);
    }

    public function test_sandbox_http_error_is_reported(): void
    {
        Http::fake(['wandbox.org/*' => Http::response('nope', 503)]);

        $service = $this->service();
        $result = $service->run('python', 'print(1)');

        $this->assertFalse($result['ok']);
        $this->assertSame('sandbox_error', $service->lastError);
    }
}
