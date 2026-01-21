<?php

use App\Exceptions\ApiException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Handle API exceptions with consistent JSON format
        $exceptions->render(function (ApiException $e, Request $request) {
            return $e->render();
        });

        // Handle validation exceptions
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => 'The given data was invalid.',
                        'details' => $e->errors(),
                    ],
                ], 422);
            }
        });

        // Handle authentication exceptions
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'UNAUTHENTICATED',
                        'message' => 'Unauthenticated.',
                    ],
                ], 401);
            }
        });

        // Handle model not found exceptions
        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                $model = class_basename($e->getModel());
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'NOT_FOUND',
                        'message' => "{$model} not found.",
                    ],
                ], 404);
            }
        });

        // Handle route not found exceptions
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'NOT_FOUND',
                        'message' => 'The requested resource was not found.',
                    ],
                ], 404);
            }
        });

        // Handle method not allowed exceptions
        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'METHOD_NOT_ALLOWED',
                        'message' => 'The HTTP method is not allowed for this route.',
                    ],
                ], 405);
            }
        });

        // Handle generic HTTP exceptions
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'HTTP_ERROR',
                        'message' => $e->getMessage() ?: 'An HTTP error occurred.',
                    ],
                ], $e->getStatusCode());
            }
        });

        // Handle all other exceptions (fallback for API requests)
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                $isDebug = config('app.debug');

                $response = [
                    'success' => false,
                    'error' => [
                        'code' => 'SERVER_ERROR',
                        'message' => $isDebug ? $e->getMessage() : 'An unexpected error occurred.',
                    ],
                ];

                if ($isDebug) {
                    $response['debug'] = [
                        'exception' => get_class($e),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => collect($e->getTrace())->take(5)->toArray(),
                    ];
                }

                return response()->json($response, 500);
            }
        });
    })->create();
