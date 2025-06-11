<?php

declare(strict_types=1);

namespace Tests\Unit;

use Oscillas\Laraprom\Reporters\NullMetricReporter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class NullMetricReporterTest extends TestCase
{

    #[Test]
    public function does_nothing(): void
    {
        $reporter = new NullMetricReporter();

        $reporter->putMetric(
            'TestNamespace',
            1700000000000,
            ['Dimension1' => 'Value1'],
            ['Metric1' => 100]
        );

        // we don't care about anything NullMetricReporter does, we just want to ensure it doesn't throw an exception.
        $this->assertTrue(true);
    }
}