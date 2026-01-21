<?php

namespace Tests\Unit\Events;

use App\Events\TotpVerified;
use App\Models\User;
use PHPUnit\Framework\TestCase;

class TotpVerifiedTest extends TestCase
{
    public function test_event_stores_worker(): void
    {
        $worker = new User();
        $worker->id = 1;
        $worker->name = 'Test Worker';

        $event = new TotpVerified($worker, true);

        $this->assertSame($worker, $event->worker);
    }

    public function test_event_stores_success_status(): void
    {
        $worker = new User();

        $successEvent = new TotpVerified($worker, true);
        $failEvent = new TotpVerified($worker, false);

        $this->assertTrue($successEvent->success);
        $this->assertFalse($failEvent->success);
    }

    public function test_event_stores_verified_by_user(): void
    {
        $worker = new User();
        $worker->id = 1;

        $verifier = new User();
        $verifier->id = 2;
        $verifier->name = 'Representative';

        $event = new TotpVerified($worker, true, $verifier);

        $this->assertSame($verifier, $event->verifiedBy);
    }

    public function test_event_allows_null_verified_by(): void
    {
        $worker = new User();

        $event = new TotpVerified($worker, true);

        $this->assertNull($event->verifiedBy);
    }
}
