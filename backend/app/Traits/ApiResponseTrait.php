<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

trait ApiResponseTrait
{
    public function successResponse(
        mixed $data = null,
        ?string $message = null,
        ?int $status = null
    ): JsonResponse {
        $status ??= $this->detectSuccessStatus();

        return response()->json(
            array_filter([
                'success' => true,
                'status'  => $status,
                'message' => $message,
                'data'    => $data,
            ], fn($v) => $v !== null),
            $status
        );
    }

    public function errorResponse(
        string $message,
        ?int $status = null,
        mixed $data = null
    ): JsonResponse {
        $status ??= $this->detectErrorStatus($message);

        return response()->json(
            array_filter([
                'success' => false,
                'status'  => $status,
                'message' => $message,
                'data'    => $data,
            ], fn($v) => $v !== null),
            $status
        );
    }

    public function tokenExpired(): JsonResponse
    {
        return $this->errorResponse('Token has expired', 401);
    }

    protected function detectSuccessStatus(): int
    {
        return match (request()->method()) {
            'POST' => 201,
            'PUT', 'PATCH' => 200,
            'DELETE' => 200,
            default => 200,
        };
    }

    protected function detectErrorStatus(string $message): int
    {
        $msg = strtolower($message);

        return match (true) {
            str_contains($msg, 'not found') => 404,
            str_contains($msg, 'unauthorized') => 401,
            str_contains($msg, 'forbidden') => 403,
            str_contains($msg, 'validation') => 422,
            str_contains($msg, 'server error') => 500,
            default => 400,
        };
    }

    public function handleException(\Throwable $e): JsonResponse
    {
        if ($e instanceof ValidationException) {
            return $this->errorResponse('Validation failed', 422, $e->errors());
        }

        if ($e instanceof HttpException) {
            return $this->errorResponse(
                $e->getMessage() ?: 'HTTP Error',
                $e->getStatusCode()
            );
        }

        return $this->errorResponse(
            app()->environment('production') ? 'Server error' : $e->getMessage(),
            500
        );
    }
}
