<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class ApiException extends Exception
{
    protected string $errorCode;
    protected array $errors;
    protected array $meta;

    public function __construct(
        string $message = 'An error occurred',
        int $statusCode = 400,
        string $errorCode = 'ERROR',
        array $errors = [],
        array $meta = [],
        ?Exception $previous = null
    ) {
        parent::__construct($message, $statusCode, $previous);
        $this->errorCode = $errorCode;
        $this->errors = $errors;
        $this->meta = $meta;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getMeta(): array
    {
        return $this->meta;
    }

    public function getStatusCode(): int
    {
        return $this->getCode();
    }

    public function render(): JsonResponse
    {
        $response = [
            'success' => false,
            'error' => [
                'code' => $this->errorCode,
                'message' => $this->getMessage(),
            ],
        ];

        if (!empty($this->errors)) {
            $response['error']['details'] = $this->errors;
        }

        if (!empty($this->meta)) {
            $response['meta'] = $this->meta;
        }

        return response()->json($response, $this->getStatusCode());
    }

    // Factory methods for common errors

    public static function notFound(string $resource = 'Resource', ?string $message = null): self
    {
        return new self(
            message: $message ?? "{$resource} not found.",
            statusCode: 404,
            errorCode: 'NOT_FOUND'
        );
    }

    public static function unauthorized(?string $message = null): self
    {
        return new self(
            message: $message ?? 'Unauthorized access.',
            statusCode: 401,
            errorCode: 'UNAUTHORIZED'
        );
    }

    public static function forbidden(?string $message = null): self
    {
        return new self(
            message: $message ?? 'Access forbidden.',
            statusCode: 403,
            errorCode: 'FORBIDDEN'
        );
    }

    public static function badRequest(string $message, array $errors = []): self
    {
        return new self(
            message: $message,
            statusCode: 400,
            errorCode: 'BAD_REQUEST',
            errors: $errors
        );
    }

    public static function validation(string $message, array $errors = []): self
    {
        return new self(
            message: $message,
            statusCode: 422,
            errorCode: 'VALIDATION_ERROR',
            errors: $errors
        );
    }

    public static function conflict(string $message): self
    {
        return new self(
            message: $message,
            statusCode: 409,
            errorCode: 'CONFLICT'
        );
    }

    public static function serverError(?string $message = null): self
    {
        return new self(
            message: $message ?? 'Internal server error.',
            statusCode: 500,
            errorCode: 'SERVER_ERROR'
        );
    }

    public static function invalidDate(string $field, string $value): self
    {
        return new self(
            message: "Invalid date format for '{$field}': {$value}",
            statusCode: 400,
            errorCode: 'INVALID_DATE_FORMAT',
            errors: [$field => "The value '{$value}' is not a valid date."]
        );
    }

    public static function serviceUnavailable(?string $message = null): self
    {
        return new self(
            message: $message ?? 'Service temporarily unavailable.',
            statusCode: 503,
            errorCode: 'SERVICE_UNAVAILABLE'
        );
    }
}
