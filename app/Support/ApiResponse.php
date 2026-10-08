<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    public static function success(mixed $data = null, string $message = 'OK', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $status);
    }

    public static function error(string $message, string $code, int $status, ?array $errors = null, array $extra = []): JsonResponse
    {
        $body = ['success' => false, 'message' => $message, 'code' => $code];
        if ($errors !== null) {
            $body['errors'] = $errors;
        }

        return response()->json($body + $extra, $status);
    }
}
