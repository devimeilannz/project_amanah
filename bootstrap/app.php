<?php

use App\Exceptions\ApiException;
use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $r, Throwable $e) => $r->is('api/*') || $r->expectsJson());

        $exceptions->render(function (ApiException $e, Request $r) {
            return ApiResponse::error($e->getMessage(), $e->errorCode, $e->status, null, $e->extra);
        });

        $exceptions->render(function (ValidationException $e, Request $r) {
            return ApiResponse::error('Data yang dikirim tidak valid.', 'VALIDATION_ERROR', 422, $e->errors());
        });

        $exceptions->render(function (AuthenticationException $e, Request $r) {
            return ApiResponse::error('Tidak terautentikasi. Silakan login terlebih dahulu.', 'UNAUTHENTICATED', 401);
        });

        $exceptions->render(function (ThrottleRequestsException $e, Request $r) {
            return ApiResponse::error('Terlalu banyak permintaan. Silakan coba lagi nanti.', 'TOO_MANY_REQUESTS', 429)
                ->withHeaders($e->getHeaders());
        });

        $exceptions->render(function (ModelNotFoundException|NotFoundHttpException $e, Request $r) {
            return ApiResponse::error('Sumber daya tidak ditemukan.', 'NOT_FOUND', 404);
        });

        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $r) {
            return ApiResponse::error('Metode HTTP tidak diizinkan untuk endpoint ini.', 'METHOD_NOT_ALLOWED', 405);
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $r) {
            return ApiResponse::error($e->getMessage() ?: 'Terjadi kesalahan.', 'HTTP_ERROR', $e->getStatusCode());
        });

        // Fallback: jangan bocorkan detail internal di production.
        $exceptions->render(function (Throwable $e, Request $r) {
            if (! $r->is('api/*')) {
                return null;
            }
            $msg = config('app.debug') ? $e->getMessage() : 'Terjadi kesalahan pada server.';

            return ApiResponse::error($msg, 'SERVER_ERROR', 500);
        });
    })->create();
