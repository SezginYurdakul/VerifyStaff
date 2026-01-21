<?php

namespace Tests\Unit\Exceptions;

use App\Exceptions\ApiException;
use PHPUnit\Framework\TestCase;

class ApiExceptionTest extends TestCase
{
    public function test_constructor_sets_properties(): void
    {
        $exception = new ApiException(
            message: 'Test error',
            statusCode: 400,
            errorCode: 'TEST_ERROR',
            errors: ['field' => 'error message'],
            meta: ['key' => 'value']
        );

        $this->assertEquals('Test error', $exception->getMessage());
        $this->assertEquals(400, $exception->getStatusCode());
        $this->assertEquals('TEST_ERROR', $exception->getErrorCode());
        $this->assertEquals(['field' => 'error message'], $exception->getErrors());
        $this->assertEquals(['key' => 'value'], $exception->getMeta());
    }

    public function test_default_values(): void
    {
        $exception = new ApiException();

        $this->assertEquals('An error occurred', $exception->getMessage());
        $this->assertEquals(400, $exception->getStatusCode());
        $this->assertEquals('ERROR', $exception->getErrorCode());
        $this->assertEquals([], $exception->getErrors());
        $this->assertEquals([], $exception->getMeta());
    }

    public function test_not_found_factory(): void
    {
        $exception = ApiException::notFound('User');

        $this->assertEquals('User not found.', $exception->getMessage());
        $this->assertEquals(404, $exception->getStatusCode());
        $this->assertEquals('NOT_FOUND', $exception->getErrorCode());
    }

    public function test_not_found_factory_with_custom_message(): void
    {
        $exception = ApiException::notFound('User', 'Custom not found message');

        $this->assertEquals('Custom not found message', $exception->getMessage());
        $this->assertEquals(404, $exception->getStatusCode());
    }

    public function test_unauthorized_factory(): void
    {
        $exception = ApiException::unauthorized();

        $this->assertEquals('Unauthorized access.', $exception->getMessage());
        $this->assertEquals(401, $exception->getStatusCode());
        $this->assertEquals('UNAUTHORIZED', $exception->getErrorCode());
    }

    public function test_unauthorized_factory_with_custom_message(): void
    {
        $exception = ApiException::unauthorized('Invalid token');

        $this->assertEquals('Invalid token', $exception->getMessage());
        $this->assertEquals(401, $exception->getStatusCode());
    }

    public function test_forbidden_factory(): void
    {
        $exception = ApiException::forbidden();

        $this->assertEquals('Access forbidden.', $exception->getMessage());
        $this->assertEquals(403, $exception->getStatusCode());
        $this->assertEquals('FORBIDDEN', $exception->getErrorCode());
    }

    public function test_bad_request_factory(): void
    {
        $errors = ['email' => 'Invalid email format'];
        $exception = ApiException::badRequest('Invalid request', $errors);

        $this->assertEquals('Invalid request', $exception->getMessage());
        $this->assertEquals(400, $exception->getStatusCode());
        $this->assertEquals('BAD_REQUEST', $exception->getErrorCode());
        $this->assertEquals($errors, $exception->getErrors());
    }

    public function test_validation_factory(): void
    {
        $errors = ['name' => 'Name is required'];
        $exception = ApiException::validation('Validation failed', $errors);

        $this->assertEquals('Validation failed', $exception->getMessage());
        $this->assertEquals(422, $exception->getStatusCode());
        $this->assertEquals('VALIDATION_ERROR', $exception->getErrorCode());
        $this->assertEquals($errors, $exception->getErrors());
    }

    public function test_conflict_factory(): void
    {
        $exception = ApiException::conflict('Resource already exists');

        $this->assertEquals('Resource already exists', $exception->getMessage());
        $this->assertEquals(409, $exception->getStatusCode());
        $this->assertEquals('CONFLICT', $exception->getErrorCode());
    }

    public function test_server_error_factory(): void
    {
        $exception = ApiException::serverError();

        $this->assertEquals('Internal server error.', $exception->getMessage());
        $this->assertEquals(500, $exception->getStatusCode());
        $this->assertEquals('SERVER_ERROR', $exception->getErrorCode());
    }

    public function test_server_error_factory_with_custom_message(): void
    {
        $exception = ApiException::serverError('Database connection failed');

        $this->assertEquals('Database connection failed', $exception->getMessage());
        $this->assertEquals(500, $exception->getStatusCode());
    }

    public function test_invalid_date_factory(): void
    {
        $exception = ApiException::invalidDate('start_date', 'not-a-date');

        $this->assertEquals("Invalid date format for 'start_date': not-a-date", $exception->getMessage());
        $this->assertEquals(400, $exception->getStatusCode());
        $this->assertEquals('INVALID_DATE_FORMAT', $exception->getErrorCode());
        $this->assertArrayHasKey('start_date', $exception->getErrors());
    }

    public function test_service_unavailable_factory(): void
    {
        $exception = ApiException::serviceUnavailable();

        $this->assertEquals('Service temporarily unavailable.', $exception->getMessage());
        $this->assertEquals(503, $exception->getStatusCode());
        $this->assertEquals('SERVICE_UNAVAILABLE', $exception->getErrorCode());
    }

    public function test_service_unavailable_factory_with_custom_message(): void
    {
        $exception = ApiException::serviceUnavailable('Maintenance in progress');

        $this->assertEquals('Maintenance in progress', $exception->getMessage());
        $this->assertEquals(503, $exception->getStatusCode());
    }
}
