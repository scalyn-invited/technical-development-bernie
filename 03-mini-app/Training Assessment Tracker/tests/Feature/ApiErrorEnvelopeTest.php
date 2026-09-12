<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ApiErrorEnvelopeTest extends TestCase
{
    public static function debugModes(): array
    {
        return ['debug off' => [false], 'debug on' => [true]];
    }

    #[DataProvider('debugModes')]
    public function test_unexpected_errors_are_safe_and_still_reported(bool $debug): void
    {
        config(['app.debug' => $debug]);
        $failure = new RuntimeException('PRIVATE-TOKEN sql-password internal-path');
        // Check actual reporting, without persisting the synthetic diagnostic.
        $this->mock(LoggerInterface::class, function ($mock) use ($failure) {
            $mock->shouldReceive('error')->once()->withArgs(
                fn ($message, $context) => $message === $failure->getMessage()
                    && ($context['exception'] ?? null) === $failure
            );
        });
        Route::get('/api/envelope-test/runtime', fn () => throw $failure);
        $this->get('/api/envelope-test/runtime', ['Accept' => 'text/html'])
            ->assertStatus(500)
            ->assertHeader('Content-Type', 'application/json')
            ->assertExactJson([
                'error' => true, 'code' => 'internal_error',
                'message' => 'An unexpected error occurred.', 'details' => [],
            ]);
    }

    #[DataProvider('debugModes')]
    public function test_database_errors_do_not_expose_sql_or_bindings(bool $debug): void
    {
        config(['app.debug' => $debug]);
        Route::get('/api/envelope-test/database', fn () => throw new QueryException(
            'sqlite', 'INSERT INTO private_table VALUES (?)', ['PRIVATE-BINDING'],
            new RuntimeException('PRIVATE-DATABASE-PATH')
        ));
        $this->getJson('/api/envelope-test/database')->assertStatus(500)->assertExactJson([
            'error' => true, 'code' => 'internal_error',
            'message' => 'An unexpected error occurred.', 'details' => [],
        ]);
    }

    public function test_http_status_and_headers_survive_without_private_message(): void
    {
        foreach ([400, 405, 429, 500, 503] as $status) {
            Route::get("/api/envelope-test/http-$status", fn () => throw new HttpException(
                $status, 'PRIVATE-HTTP-DETAIL', null,
                $status === 405 ? ['Allow' => 'POST'] : ['Retry-After' => '60']
            ));
            $response = $this->getJson("/api/envelope-test/http-$status")->assertStatus($status);
            $response->assertHeader($status === 405 ? 'Allow' : 'Retry-After', $status === 405 ? 'POST' : '60');
            $this->assertSame(['code', 'details', 'error', 'message'], collect(array_keys($response->json()))->sort()->values()->all());
            $response->assertDontSee('PRIVATE-HTTP-DETAIL');
        }
    }

    public function test_non_api_json_requests_also_receive_the_envelope(): void
    {
        Route::get('/envelope-test/json', fn () => throw new RuntimeException('PRIVATE'));
        $this->getJson('/envelope-test/json')->assertStatus(500)->assertJsonPath('code', 'internal_error');
    }

    public function test_normal_html_requests_keep_html_error_rendering(): void
    {
        config(['app.debug' => false]);
        Route::get('/envelope-test/html', fn () => throw new RuntimeException('PRIVATE'));
        $this->get('/envelope-test/html', ['Accept' => 'text/html'])->assertStatus(500)
            ->assertHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
