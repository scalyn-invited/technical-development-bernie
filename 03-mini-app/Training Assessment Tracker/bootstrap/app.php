<?php

use App\Exceptions\InvalidCredentialsException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Without this, an unauthenticated API request is redirected to the
        // `login` route, which does not exist, and the 401 surfaces as a 500.
        $middleware->redirectGuestsTo(
            fn ($request) => $request->is('api/*') ? null : route('login')
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API clients may omit an Accept header. Deciding on the path rather
        // than the header means the envelope is used even by a careless client.
        $exceptions->shouldRenderJsonWhen(
            fn ($request, Throwable $e) => $request->is('api/*') || $request->expectsJson()
        );

        // The Day 7 error envelope, carried forward unchanged:
        //   { error, code, message, details }
        // One shape for every failure path, so a client writes one error
        // handler rather than one per status code.
        $exceptions->renderable(function (Throwable $e, $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            $envelope = fn (string $code, string $message, array $details, int $status) => response()->json([
                'error' => true,
                'code' => $code,
                'message' => $message,
                'details' => $details,
            ], $status);

            if ($e instanceof AuthenticationException) {
                return $envelope('unauthenticated', 'Unauthenticated.', [], 401);
            }

            // Distinct from `unauthenticated`, which means "no usable token".
            // This means "the token request itself was rejected" — a difference
            // the frontend needs on Day 15 to tell an expired session from a
            // mistyped password.
            if ($e instanceof InvalidCredentialsException) {
                return $envelope('invalid_credentials', $e->getMessage(), [], 401);
            }

            if ($e instanceof AuthorizationException) {
                return $envelope('forbidden', 'This action is unauthorized.', [], 403);
            }

            if ($e instanceof ValidationException) {
                return $envelope('validation_failed', 'Validation failed.', $e->errors(), $e->status);
            }

            // A missing bound model must read as 404, never as a 500. Note that
            // an unauthorised read returns 403 and not 404: hiding the existence
            // of another member's plan is not a threat this tool defends
            // against, and a 404 there would make a real bug indistinguishable
            // from a permission denial.
            if ($e instanceof ModelNotFoundException) {
                return $envelope('not_found', 'Resource not found.', [], 404);
            }

            // The concurrency answer, and the one failure path that was a 500.
            //
            // Every `unique` validation rule is check-then-write: two requests
            // posting the same week number both pass validation, and the loser
            // reaches the database constraint. Laravel narrows that collision to
            // UniqueConstraintViolationException, so it arrives in the envelope
            // like everything else instead of as a stack trace.
            //
            // 409 and not 422, because the payload was not wrong — it lost a
            // race. A caller who genuinely sent a duplicate still gets 422 from
            // the Form Request; a caller who gets 409 should retry, not edit.
            if ($e instanceof UniqueConstraintViolationException) {
                return $envelope(
                    'conflict',
                    'That record already exists.',
                    [],
                    409
                );
            }

            if ($e instanceof HttpExceptionInterface) {
                return match ($e->getStatusCode()) {
                    401 => $envelope('unauthenticated', 'Unauthenticated.', [], 401),
                    403 => $envelope('forbidden', 'This action is unauthorized.', [], 403),
                    404 => $envelope('not_found', 'Resource not found.', [], 404),
                    405 => $envelope('method_not_allowed', 'Method not allowed.', [], 405),
                    429 => $envelope('too_many_requests', 'Too many requests.', [], 429),
                    default => null,
                };
            }

            return null;
        });
    })->create();
