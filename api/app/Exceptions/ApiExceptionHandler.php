<?php

namespace App\Exceptions;

use App\Traits\ApiResponseTrait;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

class ApiExceptionHandler
{
    use ApiResponseTrait;

    public static function handle(Throwable $exception, Request $request): ?JsonResponse
    {
        $handler = new static();

        // Handle specific exception types
        return match (true) {
            $exception instanceof ValidationException => $handler->handleValidationException($exception),
            $exception instanceof ModelNotFoundException => $handler->handleModelNotFoundException($exception),
            $exception instanceof NotFoundHttpException => $handler->handleNotFoundHttpException($exception),
            $exception instanceof AuthenticationException => $handler->handleAuthenticationException($exception),
            $exception instanceof AccessDeniedHttpException => $handler->handleAccessDeniedException($exception),
            $exception instanceof TooManyRequestsHttpException => $handler->handleTooManyRequestsException($exception),
            default => $handler->handleGenericException($exception, $request),
        };
    }

    protected function handleValidationException(ValidationException $exception): JsonResponse
    {
        return $this->errorResponse(
            $exception->getMessage(),
            Response::HTTP_UNPROCESSABLE_ENTITY,
            null,
            ['errors' => $exception->errors()]
        );
    }

    protected function handleModelNotFoundException(ModelNotFoundException $exception): JsonResponse
    {
        $model = class_basename($exception->getModel());

        return $this->notFoundResponse(
            "The requested {$model} was not found."
        );
    }

    protected function handleNotFoundHttpException(NotFoundHttpException $exception): JsonResponse
    {
        return $this->notFoundResponse(
            'The requested resource was not found.'
        );
    }

    protected function handleAuthenticationException(AuthenticationException $exception): JsonResponse
    {
        return $this->unauthorizedResponse(
            'Authentication required.'
        );
    }

    protected function handleAccessDeniedException(AccessDeniedHttpException $exception): JsonResponse
    {
        return $this->forbiddenResponse(
            $exception->getMessage() ?: 'Access denied.'
        );
    }

    protected function handleTooManyRequestsException(TooManyRequestsHttpException $exception): JsonResponse
    {
        return $this->errorResponse(
            'Too many requests. Please try again later.',
            Response::HTTP_TOO_MANY_REQUESTS
        );
    }

    protected function handleGenericException(Throwable $exception, Request $request): JsonResponse
    {
        Log::error('API Exception: ' . $exception->getMessage(), [
            'exception' => $exception,
            'request' => [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'user_id' => Auth::id(),
            ],
            'trace' => $exception->getTraceAsString(),
        ]);

        if (app()->environment('production')) {
            return $this->errorResponse(
                'An unexpected error occurred. Please try again later.',
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return $this->errorResponse(
            $exception->getMessage(),
            Response::HTTP_INTERNAL_SERVER_ERROR,
            null,
            [
                'errors' => [
                    'exception' => get_class($exception),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'trace' => collect($exception->getTrace())->take(10)->toArray(),
                ]
            ]
        );
    }
}
