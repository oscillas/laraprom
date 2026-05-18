<?php

declare(strict_types=1);

namespace Tests\Unit;

use Oscillas\Laraprom\Transports\CloudwatchEmfTransport;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\TestDoubles\Listeners\FakeCloudwatchLogsHelper;

final class CloudwatchEmfTransportTest extends TestCase
{
    private FakeCloudwatchLogsHelper $logsHelper;
    private CloudwatchEmfTransport $transport;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logsHelper = new FakeCloudwatchLogsHelper();
        $this->transport = new CloudwatchEmfTransport($this->logsHelper);
    }

    #[Test]
    public function sends_metrics_to_correct_log_group_and_stream(): void
    {
        $tenantId = bin2hex(random_bytes(16));
        $namespace = 'TestNamespace';
        $dimensions = ['TenantUUID' => $tenantId, 'Environment' => 'testing'];
        $metrics = ['RequestCount' => ['Value' => 42, 'Unit' => 'Count']];
        $timestamp = (int) (microtime(true) * 1000);

        $this->transport->sendMetrics($namespace, $timestamp, $dimensions, $metrics);

        $recorded = $this->logsHelper->putPipelineRunFailureEmbeddedMetric;

        $this->assertArrayHasKey('/artemis/cloud', $recorded);
        $this->assertArrayHasKey("{$tenantId}/pipelines", $recorded['/artemis/cloud']);
        $this->assertCount(1, $recorded['/artemis/cloud']["{$tenantId}/pipelines"]);

        $datum = $recorded['/artemis/cloud']["{$tenantId}/pipelines"][0];
        $this->assertEquals($namespace, $datum['Namespace']);
        $this->assertEquals($timestamp, $datum['Timestamp']);
        $this->assertEquals($dimensions, $datum['Dimensions']);
        $this->assertEquals($metrics, $datum['Metrics']);
    }
}
