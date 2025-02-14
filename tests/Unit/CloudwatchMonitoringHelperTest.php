<?php

declare(strict_types=1);

namespace Tests\Unit;

use Carbon\CarbonImmutable;
use Oscillas\Laraprom\Reporters\CloudwatchMetricReporter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\TestDoubles\Listeners\FakeCloudwatchLogsHelper;

class CloudwatchMonitoringHelperTest extends TestCase
{
    private CloudwatchMetricReporter $cloudwatchMonitoringHelper;
    private FakeCloudwatchLogsHelper $cloudwatchLogsHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cloudwatchLogsHelper = new FakeCloudwatchLogsHelper();
        $this->cloudwatchMonitoringHelper = new CloudwatchMetricReporter($this->cloudwatchLogsHelper);
    }

    #[Test]
    public function push_metrics_to_cloudwatch_in_expected_format(): void
    {
        # Arrange
        $tenantId = bin2hex(random_bytes(16));

        $namespace = 'Local/Testing/Namespace';
        $dimensions = ['DimensionOne' => bin2hex(random_bytes(16)), 'TenantUUID' => $tenantId];
        $metrics = ['MetricOne' => ['Value' => 1, 'Unit' => 'Count']];
        $unixTimestampInMillis = (int) CarbonImmutable::now()->valueOf();

        $this->cloudwatchMonitoringHelper->putMetric($namespace, $unixTimestampInMillis, $dimensions, $metrics);

        $this->assertArrayHasKey('/artemis/cloud', $this->cloudwatchLogsHelper->putPipelineRunFailureEmbeddedMetric);
        $this->assertArrayHasKey("$tenantId/pipelines", $this->cloudwatchLogsHelper->putPipelineRunFailureEmbeddedMetric['/artemis/cloud']);
        $this->assertCount(1, $this->cloudwatchLogsHelper->putPipelineRunFailureEmbeddedMetric['/artemis/cloud']["$tenantId/pipelines"]);

        $datum = $this->cloudwatchLogsHelper->putPipelineRunFailureEmbeddedMetric['/artemis/cloud']["$tenantId/pipelines"][0];
        $this->assertEquals($namespace, $datum['Namespace']);
        $this->assertEquals($unixTimestampInMillis, $datum['Timestamp']);
        $this->assertEquals($dimensions, $datum['Dimensions']);
        $this->assertEquals($metrics, $datum['Metrics']);
    }

    #[Test]
    public function require_tenant_uuid_to_be_present_in_dimensions_array(): void
    {
        # Arrange
        $namespace = 'Local/Testing/Namespace';
        $dimensions = ['DimensionOne' => bin2hex(random_bytes(16))];
        $metrics = ['MetricOne' => ['Value' => 1, 'Unit' => 'Count']];
        $unixTimestampInMillis = (int) CarbonImmutable::now()->valueOf();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('TenantUUID must be present in dimensions array');

        # Act
        $this->cloudwatchMonitoringHelper->putMetric($namespace, $unixTimestampInMillis, $dimensions, $metrics);
    }
}
