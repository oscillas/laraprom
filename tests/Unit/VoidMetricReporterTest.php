<?php

declare(strict_types=1);

namespace Tests\Unit;

use Oscillas\Laraprom\Reporters\VoidMetricReporter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class VoidMetricReporterTest extends TestCase
{

    #[Test]
    public function does_nothing(): void
    {
        $reporter = new VoidMetricReporter();

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