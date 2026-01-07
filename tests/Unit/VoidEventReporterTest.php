<?php

declare(strict_types=1);

namespace Tests\Unit;

use Oscillas\Laraprom\Reporters\VoidEventReporter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class VoidEventReporterTest extends TestCase
{

    #[Test]
    public function does_nothing(): void
    {
        $reporter = new VoidEventReporter();

        $reporter->putEvent(
            'Test Event Title',
            1700000000000,
            ['Environment' => 'production', 'Service' => 'api'],
            'This is a test event description.'
        );

        // we don't care about anything VoidEventReporter does, we just want to ensure it doesn't throw an exception.
        $this->assertTrue(true);
    }
}