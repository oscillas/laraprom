<?php

declare(strict_types=1);

namespace Tests\Integration\Transports;

use Carbon\CarbonImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Oscillas\Laraprom\Transports\CloudwatchPutMetricDataTransport;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CloudwatchPutMetricDataTransportTest extends TestCase
{
    use WithWorkbench;

    #[Test]
    public function sends_metrics_in_put_metric_data_format(): void
    {
        $container = [];
        $history = Middleware::history($container);
        $handlerStack = HandlerStack::create();
        $handlerStack->push($history);
        $guzzleClient = new Client(['handler' => $handlerStack]);

        $transport = new CloudwatchPutMetricDataTransport($guzzleClient);

        $namespace = 'TestNamespace';
        $dimensions = [
            'TenantUUID' => bin2hex(random_bytes(16)),
            'Environment' => 'testing',
        ];
        $metrics = [
            'MetricOne' => ['Value' => 1, 'Unit' => 'Count'],
            'MetricTwo' => ['Value' => 2.5, 'Unit' => 'Seconds'],
        ];
        $unixTimestampInMillis = (int) CarbonImmutable::now()->valueOf();

        $transport->sendMetrics($namespace, $unixTimestampInMillis, $dimensions, $metrics);

        $putMetricDataRequest = null;
        foreach ($container as $transaction) {
            $headers = $transaction['request']->getHeaders();
            if (
                array_key_exists('X-Amz-Target', $headers)
                && str_contains($headers['X-Amz-Target'][0], 'PutMetricData')
            ) {
                $putMetricDataRequest = $transaction;
                break;
            }
        }

        $this->assertNotNull($putMetricDataRequest, 'No PutMetricData request was captured');

        $body = json_decode((string) $putMetricDataRequest['request']->getBody(), true);

        $this->assertEquals($namespace, $body['Namespace']);
        $this->assertCount(2, $body['MetricData']);

        $expectedDimensions = [
            ['Name' => 'TenantUUID', 'Value' => $dimensions['TenantUUID']],
            ['Name' => 'Environment', 'Value' => 'testing'],
        ];

        $metricOne = $body['MetricData'][0];
        $this->assertEquals('MetricOne', $metricOne['MetricName']);
        $this->assertEquals(1, $metricOne['Value']);
        $this->assertEquals('Count', $metricOne['Unit']);
        $this->assertEquals($expectedDimensions, $metricOne['Dimensions']);
        $this->assertEquals($unixTimestampInMillis / 1000, $metricOne['Timestamp']);

        $metricTwo = $body['MetricData'][1];
        $this->assertEquals('MetricTwo', $metricTwo['MetricName']);
        $this->assertEquals(2.5, $metricTwo['Value']);
        $this->assertEquals('Seconds', $metricTwo['Unit']);
    }

    #[Test]
    public function specifying_region_in_constructor_overrides_default_us_east_1(): void
    {
        $awsRegionOverride = 'eu-west-1';

        $container = [];
        $history = Middleware::history($container);
        $handlerStack = HandlerStack::create();
        $handlerStack->push($history);
        $guzzleClient = new Client(['handler' => $handlerStack]);

        $transport = new CloudwatchPutMetricDataTransport($guzzleClient, $awsRegionOverride);

        $transport->sendMetrics(
            'TestNamespace',
            (int) CarbonImmutable::now()->valueOf(),
            ['TenantUUID' => 'test'],
            ['MetricOne' => ['Value' => 1, 'Unit' => 'Count']],
        );

        foreach ($container as $transaction) {
            $headers = $transaction['request']->getHeaders();
            $this->assertStringContainsString($awsRegionOverride, $headers['Host'][0]);
        }
    }
}
