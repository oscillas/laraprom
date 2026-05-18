<?php

declare(strict_types=1);

namespace Tests\Unit;

use Carbon\CarbonImmutable;
use Oscillas\Laraprom\Reporters\CloudwatchMetricReporter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\TestDoubles\Listeners\FakeCloudwatchTransport;

class CloudwatchMonitoringHelperTest extends TestCase
{
    private CloudwatchMetricReporter $cloudwatchMonitoringHelper;
    private FakeCloudwatchTransport $transport;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transport = new FakeCloudwatchTransport();
        $this->cloudwatchMonitoringHelper = new CloudwatchMetricReporter($this->transport);
    }

    #[Test]
    public function delegates_metrics_to_transport(): void
    {
        $tenantId = bin2hex(random_bytes(16));

        $namespace = 'Local/Testing/Namespace';
        $dimensions = ['DimensionOne' => bin2hex(random_bytes(16)), 'TenantUUID' => $tenantId];
        $metrics = ['MetricOne' => ['Value' => 1, 'Unit' => 'Count']];
        $unixTimestampInMillis = (int) CarbonImmutable::now()->valueOf();

        $this->cloudwatchMonitoringHelper->putMetric($namespace, $unixTimestampInMillis, $dimensions, $metrics);

        $this->assertCount(1, $this->transport->calls);

        $datum = $this->transport->calls[0];
        $this->assertEquals($namespace, $datum['Namespace']);
        $this->assertEquals($unixTimestampInMillis, $datum['Timestamp']);
        $this->assertEquals($dimensions, $datum['Dimensions']);
        $this->assertEquals($metrics, $datum['Metrics']);
    }

    #[Test]
    public function require_tenant_uuid_to_be_present_in_dimensions_array(): void
    {
        $namespace = 'Local/Testing/Namespace';
        $dimensions = ['DimensionOne' => bin2hex(random_bytes(16))];
        $metrics = ['MetricOne' => ['Value' => 1, 'Unit' => 'Count']];
        $unixTimestampInMillis = (int) CarbonImmutable::now()->valueOf();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('TenantUUID must be present in dimensions array');

        $this->cloudwatchMonitoringHelper->putMetric($namespace, $unixTimestampInMillis, $dimensions, $metrics);
    }
}
