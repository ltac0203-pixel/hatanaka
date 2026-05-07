<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\FincodeApiException;
use App\Services\Fincode\FincodeOperationLogger;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class FincodeOperationLoggerTest extends TestCase
{
    public function test_rethrow_with_log_emits_error_with_fixed_keys_and_rethrows(): void
    {
        $exception = new FincodeApiException('boom', 502, ['error' => 'bad gateway']);

        Log::shouldReceive('error')
            ->once()
            ->with('Failed to xxx on Fincode', [
                'user_id' => 99,
                'exception_class' => FincodeApiException::class,
                'status_code' => 502,
            ]);

        try {
            FincodeOperationLogger::rethrowWithLog(
                'Failed to xxx on Fincode',
                ['user_id' => 99],
                $exception,
            );
            $this->fail('Expected FincodeApiException to be re-thrown.');
        } catch (FincodeApiException $e) {
            $this->assertSame($exception, $e);
        }
    }

    public function test_rethrow_with_log_caller_keys_cannot_override_fixed_keys(): void
    {
        $exception = new FincodeApiException('boom', 400);

        Log::shouldReceive('error')
            ->once()
            ->with('Failed to xxx on Fincode', [
                'exception_class' => FincodeApiException::class,
                'status_code' => 400,
            ]);

        try {
            FincodeOperationLogger::rethrowWithLog(
                'Failed to xxx on Fincode',
                ['exception_class' => 'caller-supplied', 'status_code' => 999],
                $exception,
            );
            $this->fail('Expected FincodeApiException to be re-thrown.');
        } catch (FincodeApiException) {
            // expected
        }
    }
}
