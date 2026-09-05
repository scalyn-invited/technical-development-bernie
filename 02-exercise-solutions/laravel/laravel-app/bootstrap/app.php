<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);

        // Guests on API routes must surface as AuthenticationException (401), not a
        // redirect to the nonexistent `login` route, which would throw and become a 500.
        $middleware->redirectGuestsTo(
            fn ($request) => $request->is('api/*') ? null : route('login')
        );

        $middleware->api(append: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API clients may omit an Accept header; without this the unauthenticated
        // handler redirects to the nonexistent `login` route and 401s become 500s.
        $exceptions->shouldRenderJsonWhen(
            fn ($request, Throwable $e) => $request->is('api/*') || $request->expectsJson()
        );

        $exceptions->renderable(function (Throwable $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                if ($e instanceof \Illuminate\Auth\AuthenticationException) {
                    return response()->json([
                        'error' => true,
                        'code' => 'unauthenticated',
                        'message' => 'Unauthenticated',
                        'details' => [],
                    ], 401);
                }

                if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                    return response()->json([
                        'error' => true,
                        'code' => 'forbidden',
                        'message' => 'This action is unauthorized.',
                        'details' => [],
                    ], 403);
                }

                if ($e instanceof \Illuminate\Validation\ValidationException) {
                    return response()->json([
                        'error' => true,
                        'code' => 'validation_failed',
                        'message' => 'Validation failed',
                        'details' => $e->errors(),
                    ], $e->status);
                }

                if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException) {
                    if ($e->getStatusCode() === 403) {
                        return response()->json([
                            'error' => true,
                            'code' => 'forbidden',
                            'message' => 'This action is unauthorized.',
                            'details' => [],
                        ], 403);
                    }
                    if ($e->getStatusCode() === 401) {
                        return response()->json([
                            'error' => true,
                            'code' => 'unauthenticated',
                            'message' => 'Unauthenticated',
                            'details' => [],
                        ], 401);
                    }

                    if ($e->getStatusCode() === 404) {
                        return response()->json([
                            'error' => true,
                            'code' => 'not_found',
                            'message' => 'Resource not found.',
                            'details' => [],
                        ], 404);
                    }
                }
            }
        });
    })->create();
