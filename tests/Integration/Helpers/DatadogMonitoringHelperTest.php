<?php

declare(strict_types=1);

namespace Tests\Integration\Helpers;

use Carbon\CarbonImmutable;
use GuzzleHttp\Client;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Oscillas\Laraprom\Helpers\DatadogMonitoringHelper;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DatadogMonitoringHelperTest extends TestCase
{
    use WithWorkbench;

    private Client $client;
    private DatadogMonitoringHelper $datadogHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new Client();
        $this->datadogHelper = $this->app->make(DatadogMonitoringHelper::class);
    }

    #[Test]
    public function build_datadog_event_object_and_report_it(): void
    {
        # Arrange
        $start = CarbonImmutable::now();
        $title = 'LocalTestingNamespace';
        $dimensions = [
            'TagOne' => bin2hex(random_bytes(16)),
            'TagTwo' => bin2hex(random_bytes(16)),
            'env' => 'testing',
        ];
        $unixTimestampInMillis = (int) $start->valueOf();
        $body = 'This is an event sent by developers for testing purposes.';

        # Act
        $this->datadogHelper->putEvent(
            $title,
            $unixTimestampInMillis,
            $dimensions,
            $body,
        );

        # Assert
        sleep(120); // wait for datadog to post the event ~2min
        $response = $this->client->get('https://api.datadoghq.com/api/v1/events', [
            'headers' => [
                'DD-API-KEY' => env('DATADOG_API_KEY'),
                'DD-APPLICATION-KEY' => env('DATADOG_APP_KEY'),
            ],
            'query' => [
                'start' => $start->subMinutes(5)->getTimestamp(),
                'end' => CarbonImmutable::now()->getTimestamp(),
                'tags' => "env:testing",
            ],
        ]);

        $events = json_decode($response->getBody()->getContents(), true)['events'];

        $this->assertNotEmpty($events);
        $this->assertEquals($title, $events[0]['title']);
        $this->assertEquals($body, $events[0]['text']);
    }

    #[Test]
    public function build_datadog_metric_object_and_report_it(): void
    {
        # Arrange
        $start = CarbonImmutable::now();
        $namespace = 'Local/Testing/Namespace';
        $dimensions = ['DimensionOne' => bin2hex(random_bytes(16)),];
        $metrics = ['MetricOne' => ['Value' => 1, 'Unit' => 'Count']];

        $unixTimestampInMillis = (int) CarbonImmutable::now()->valueOf();

        # Act
        $this->datadogHelper->putMetric(
            $namespace,
            $unixTimestampInMillis,
            $dimensions,
            $metrics,
        );

        # Assert
        sleep(120); // wait for datadog to post the event ~2min
        $response = $this->client->get('https://api.datadoghq.com/api/v1/metrics', [
            'headers' => [
                'DD-API-KEY' => env('DATADOG_API_KEY'),
                'DD-APPLICATION-KEY' => env('DATADOG_APP_KEY'),
            ],
            'query' => [
                'from' => $start->subMinutes(5)->getTimestamp(),
            ],
        ]);

        $metrics = json_decode($response->getBody()->getContents(), true)['metrics'];
        $this->assertTrue(in_array('local.testing.namespace.MetricOne', $metrics));
    }
}
