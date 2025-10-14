<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponseTrait
{
    /**
     * Success response
     */
    protected function successResponse($data = null, string $message = 'none', int $statusCode = 200): JsonResponse
    {
        $response = [
            'status' => 'success'
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        if ($message !== null) {
            $response['message'] = $message;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Error response
     */
    protected function errorResponse(string $error, int $statusCode = 400, $data = null, $additionals = null): JsonResponse
    {
        $response = [
            'status' => 'error',
            'error' => $error
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        if ($additionals !== null && is_array($additionals)) {
            $response = array_merge($response, $additionals);
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Success response with data
     */
    protected function successWithData($data, string $message = 'none', int $statusCode = 200): JsonResponse
    {
        return $this->successResponse($data, $message, $statusCode);
    }

    /**
     * Success response with message only
     */
    protected function successWithMessage(string $message, int $statusCode = 200): JsonResponse
    {
        return $this->successResponse(null, $message, $statusCode);
    }

    /**
     * Created response (201)
     */
    protected function createdResponse($data, string $message = 'none'): JsonResponse
    {
        return $this->successResponse($data, $message, 201);
    }

    /**
     * Validation error response (422)
     */
    protected function validationErrorResponse(string $error = 'Validation failed', $errors = null): JsonResponse
    {
        return $this->errorResponse($error, 422, $errors);
    }

    /**
     * Unauthorized response (401)
     */
    protected function unauthorizedResponse(string $error = 'Unauthorized'): JsonResponse
    {
        return $this->errorResponse($error, 401);
    }

    /**
     * Forbidden response (403)
     */
    protected function forbiddenResponse(string $error = 'Forbidden'): JsonResponse
    {
        return $this->errorResponse($error, 403);
    }

    /**
     * Not found response (404)
     */
    protected function notFoundResponse(string $error = 'Resource not found'): JsonResponse
    {
        return $this->errorResponse($error, 404);
    }

    /**
     * Server error response (500)
     */
    protected function serverErrorResponse(string $error = 'Internal server error'): JsonResponse
    {
        return $this->errorResponse($error, 500);
    }

    /**
     * Success response with headers
     */
    protected function successWithHeaders($data, array $headers, string $message = 'none', int $statusCode = 200): JsonResponse
    {
        $response = $this->successResponse($data, $message, $statusCode);

        foreach ($headers as $key => $value) {
            $response->header($key, $value);
        }

        return $response;
    }

    /**
     * Error response with headers
     */
    protected function errorWithHeaders(string $error, array $headers, int $statusCode = 400): JsonResponse
    {
        $response = $this->errorResponse($error, $statusCode);

        foreach ($headers as $key => $value) {
            $response->header($key, $value);
        }

        return $response;
    }
}
