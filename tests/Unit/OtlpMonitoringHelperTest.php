<?php

declare(strict_types=1);

namespace Tests\Unit;

use Oscillas\Laraprom\Reporters\MetricReporterInterface;
use Oscillas\Laraprom\Reporters\OtlpMetricReporter;
use Tests\TestCase;
use Tests\TestDoubles\Listeners\FakeOtlpTransport;

final class OtlpMonitoringHelperTest extends TestCase
{
    use MetricReporterInterfaceTests;

    private FakeOtlpTransport $transport;

    protected function getMetricReporter(): MetricReporterInterface
    {
        $this->transport = new FakeOtlpTransport();

        return new OtlpMetricReporter($this->transport);
    }

    protected function assertMetricsSubmitted(
        string $expectedNamespace,
        int    $expectedUnixTimestampMillis,
        array  $expectedDimensions,
        array  $expectedMetrics
    ): void {
        $this->assertCount(1, $this->transport->calls);

        $call = $this->transport->calls[0];
        $this->assertEquals($expectedNamespace, $call['Namespace']);
        $this->assertEquals($expectedUnixTimestampMillis, $call['Timestamp']);
        $this->assertEquals($expectedDimensions, $call['Dimensions']);
        $this->assertEquals($expectedMetrics, $call['Metrics']);
    }

    protected function assertDidNotSubmitAnyMetrics(): void
    {
        $this->assertEmpty($this->transport->calls);
    }
}
