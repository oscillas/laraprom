<?php

namespace Tests\Integration\Helpers;

use Aws\CloudWatchLogs\CloudWatchLogsClient;
use Carbon\CarbonImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Oscillas\Laraprom\Helpers\CloudwatchLogsHelper;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;


class CloudwatchLogsHelperTest extends TestCase
{
    use WithWorkbench;

    #[Test]
    public function pushes_expected_format_to_cloudwatch_logs(): void
    {
        # Arrange
        $container = [];
        $history = Middleware::history($container);
        $handlerStack = HandlerStack::create();
        $handlerStack->push($history);
        $guzzleClient = new Client(['handler' => $handlerStack]);

        $cloudwatchLogsHelper = new CloudwatchLogsHelper($guzzleClient);

        $logGroup = '/local-testing';
        $logStream = bin2hex(random_bytes(16));
        $namespace = 'LocalTestingNamespace';
        $dimensions = [
            'DimensionOne' => bin2hex(random_bytes(16)),
            'DimensionTwo' => bin2hex(random_bytes(16)),
        ];
        $metrics = [
            'MetricOne' => [
                'Value' => 1,
                'Unit' => 'Count',
            ],
            'MetricTwo' => [
                'Value' => 2,
                'Unit' => 'Count',
            ],
        ];

        $unixTimestampInMillis = (int) CarbonImmutable::now()->valueOf();

        # Act
        $cloudwatchLogsHelper->putEmbeddedMetric(
            $logGroup,
            $logStream,
            $namespace,
            $unixTimestampInMillis,
            $dimensions,
            $metrics,
        );

        # Assert
        foreach ($container as $transaction) {
            $this->assertEquals(200, $transaction['response']->getStatusCode());

            $headers = $transaction['request']->getHeaders();
            if (
                array_key_exists('X-Amz-Target', $headers)
                && $headers['X-Amz-Target'][0] === 'Logs_20140328.PutLogEvents'
            ) {
                $body = json_decode((string) $transaction['request']->getBody(), true);
                $embeddedMetric = json_decode($body['logEvents'][0]['message'], true);

                $this->assertArrayHasKey('_aws', $embeddedMetric);

                $this->assertEquals($unixTimestampInMillis, $embeddedMetric['_aws']['Timestamp']);

                $this->assertCount(1, $embeddedMetric['_aws']['CloudWatchMetrics']);
                $this->assertEquals($namespace, $embeddedMetric['_aws']['CloudWatchMetrics'][0]['Namespace']);
                $sentDimensions = $embeddedMetric['_aws']['CloudWatchMetrics'][0]['Dimensions'];
                $this->assertCount(1, $sentDimensions);
                $this->assertContains('DimensionOne', $sentDimensions[0]);
                $this->assertContains('DimensionTwo', $sentDimensions[0]);

                $this->assertEquals($dimensions['DimensionOne'], $embeddedMetric['DimensionOne']);
                $this->assertEquals($dimensions['DimensionTwo'], $embeddedMetric['DimensionTwo']);

                $sentMetrics = $embeddedMetric['_aws']['CloudWatchMetrics'][0]['Metrics'];
                $this->assertCount(2, $sentMetrics);
                $this->assertEquals('MetricOne', $sentMetrics[0]['Name']);
                $this->assertEquals('Count', $sentMetrics[0]['Unit']);
                $this->assertEquals('MetricTwo', $sentMetrics[1]['Name']);
                $this->assertEquals('Count', $sentMetrics[1]['Unit']);

                $this->assertEquals(1, $embeddedMetric['MetricOne']);
                $this->assertEquals(2, $embeddedMetric['MetricTwo']);
            }
        }
    }

    #[Test]
    public function if_log_stream_already_exists_push_to_existing_stream(): void
    {
        # Arrange
        $logGroup = '/local-testing';
        $alreadyExistingLogStream = uniqid(more_entropy: true);

        $clientConfiguration = [
            'region' => 'us-east-1',
            'version' => 'latest',
        ];

        $client = new CloudWatchLogsClient($clientConfiguration);

        $client->createLogStream([
            'logGroupName' => $logGroup,
            'logStreamName' => $alreadyExistingLogStream,
        ]);

        # Arrange
        $container = [];
        $history = Middleware::history($container);
        $handlerStack = HandlerStack::create();
        $handlerStack->push($history);
        $guzzleClient = new Client(['handler' => $handlerStack]);

        $cloudwatchLogsHelper = new CloudwatchLogsHelper($guzzleClient);

        $namespace = 'LocalTestingNamespace';
        $dimensions = [
            'DimensionOne' => bin2hex(random_bytes(16)),
            'DimensionTwo' => bin2hex(random_bytes(16)),
        ];
        $metrics = [
            'MetricOne' => [
                'Value' => 1,
                'Unit' => 'Count',
            ],
            'MetricTwo' => [
                'Value' => 2,
                'Unit' => 'Count',
            ],
        ];

        $unixTimestampInMillis = (int) CarbonImmutable::now()->valueOf();

        # Act
        $cloudwatchLogsHelper->putEmbeddedMetric(
            $logGroup,
            $alreadyExistingLogStream,
            $namespace,
            $unixTimestampInMillis,
            $dimensions,
            $metrics,
        );

        # Assert
        foreach ($container as $transaction) {
            $statusCode = $transaction['response']->getStatusCode();
            $this->assertThat(
                $statusCode,
                $this->logicalOr($this->equalTo(200), $this->equalTo(400))
            );

            if (400 == $transaction['response']->getStatusCode()) {
                continue;
            }

            $headers = $transaction['request']->getHeaders();
            if (
                array_key_exists('X-Amz-Target', $headers)
                && $headers['X-Amz-Target'][0] === 'Logs_20140328.PutLogEvents'
            ) {
                $body = json_decode((string) $transaction['request']->getBody(), true);
                $embeddedMetric = json_decode($body['logEvents'][0]['message'], true);

                $this->assertArrayHasKey('_aws', $embeddedMetric);

                $this->assertEquals($unixTimestampInMillis, $embeddedMetric['_aws']['Timestamp']);

                $this->assertCount(1, $embeddedMetric['_aws']['CloudWatchMetrics']);
                $this->assertEquals($namespace, $embeddedMetric['_aws']['CloudWatchMetrics'][0]['Namespace']);
                $sentDimensions = $embeddedMetric['_aws']['CloudWatchMetrics'][0]['Dimensions'];
                $this->assertCount(1, $sentDimensions);
                $this->assertContains('DimensionOne', $sentDimensions[0]);
                $this->assertContains('DimensionTwo', $sentDimensions[0]);

                $this->assertEquals($dimensions['DimensionOne'], $embeddedMetric['DimensionOne']);
                $this->assertEquals($dimensions['DimensionTwo'], $embeddedMetric['DimensionTwo']);

                $sentMetrics = $embeddedMetric['_aws']['CloudWatchMetrics'][0]['Metrics'];
                $this->assertCount(2, $sentMetrics);
                $this->assertEquals('MetricOne', $sentMetrics[0]['Name']);
                $this->assertEquals('Count', $sentMetrics[0]['Unit']);
                $this->assertEquals('MetricTwo', $sentMetrics[1]['Name']);
                $this->assertEquals('Count', $sentMetrics[1]['Unit']);

                $this->assertEquals(1, $embeddedMetric['MetricOne']);
                $this->assertEquals(2, $embeddedMetric['MetricTwo']);
            }
        }
    }

    #[Test]
    public function specifying_region_in_constructor_overrides_default_us_east_1(): void
    {
        # Arrange
        $awsRegionOverride = 'us-east-2';

        $logGroup = '/local-testing';
        $alreadyExistingLogStream = uniqid(more_entropy: true);

        $clientConfiguration = [
            'region' => $awsRegionOverride,
            'version' => 'latest',
        ];

        $client = new CloudWatchLogsClient($clientConfiguration);

        $client->createLogStream([
            'logGroupName' => $logGroup,
            'logStreamName' => $alreadyExistingLogStream,
        ]);

        # Arrange
        $container = [];
        $history = Middleware::history($container);
        $handlerStack = HandlerStack::create();
        $handlerStack->push($history);
        $guzzleClient = new Client(['handler' => $handlerStack]);

        $namespace = 'LocalTestingNamespace';
        $dimensions = ['DimensionOne' => bin2hex(random_bytes(16)),];
        $metrics = ['MetricOne' => ['Value' => 1, 'Unit' => 'Count']];

        $unixTimestampInMillis = (int) CarbonImmutable::now()->valueOf();

        # Act
        $cloudwatchLogsHelper = new CloudwatchLogsHelper($guzzleClient, $awsRegionOverride);

        $cloudwatchLogsHelper->putEmbeddedMetric(
            $logGroup,
            $alreadyExistingLogStream,
            $namespace,
            $unixTimestampInMillis,
            $dimensions,
            $metrics,
        );

        # Assert
        foreach ($container as $transaction) {
            $statusCode = $transaction['response']->getStatusCode();
            $this->assertThat(
                $statusCode,
                $this->logicalOr($this->equalTo(200), $this->equalTo(400))
            );

            if (400 == $transaction['response']->getStatusCode()) {
                continue;
            }

            $headers = $transaction['request']->getHeaders();
            $this->assertStringContainsString($awsRegionOverride, $headers['Host'][0]);
        }
    }
}
