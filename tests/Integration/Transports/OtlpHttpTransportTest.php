<?php

declare(strict_types=1);

namespace Tests\Integration\Transports;

use Carbon\CarbonImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Oscillas\Laraprom\Transports\OtlpHttpTransport;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OtlpHttpTransportTest extends TestCase
{
    use WithWorkbench;

    /** @var array<int, array{request: \Psr\Http\Message\RequestInterface, response: \Psr\Http\Message\ResponseInterface}> */
    private array $container = [];

    private function makeGuzzleClient(int $queuedResponses = 1): Client
    {
        $this->container = [];
        $mockHandler = new MockHandler(array_fill(0, $queuedResponses, new Response(200)));
        $handlerStack = HandlerStack::create($mockHandler);
        $handlerStack->push(Middleware::history($this->container));

        return new Client(['handler' => $handlerStack]);
    }

    #[Test]
    public function sends_metrics_in_otlp_http_json_format(): void
    {
        $endpoint = 'https://otlp-gateway-prod-us-east-0.grafana.net/otlp';
        $instanceId = '123456';
        $token = 'glc_test_token';
        $serviceName = 'test-service';

        $transport = new OtlpHttpTransport(
            $this->makeGuzzleClient(),
            $endpoint,
            $serviceName,
            $instanceId,
            $token,
        );

        $namespace = 'test_namespace';
        $dimensions = [
            'env' => 'dev',
            'TenantUUID' => bin2hex(random_bytes(16)),
        ];
        $metrics = [
            'requests_total' => ['Value' => 42, 'Unit' => 'Count'],
            'request_duration' => ['Value' => 2.5, 'Unit' => 'Seconds'],
        ];
        $unixTimestampInMillis = (int) CarbonImmutable::now()->valueOf();

        $transport->sendMetrics($namespace, $unixTimestampInMillis, $dimensions, $metrics);

        $this->assertCount(1, $this->container);
        $request = $this->container[0]['request'];

        $this->assertEquals('POST', $request->getMethod());
        $this->assertEquals("{$endpoint}/v1/metrics", (string) $request->getUri());
        $this->assertEquals('application/json', $request->getHeaderLine('Content-Type'));
        $this->assertEquals(
            'Basic ' . base64_encode("{$instanceId}:{$token}"),
            $request->getHeaderLine('Authorization'),
        );

        $body = json_decode((string) $request->getBody(), true);

        $resourceMetrics = $body['resourceMetrics'][0];
        $this->assertEquals(
            [['key' => 'service.name', 'value' => ['stringValue' => $serviceName]]],
            $resourceMetrics['resource']['attributes'],
        );

        $scopeMetrics = $resourceMetrics['scopeMetrics'][0];
        $this->assertEquals('laraprom', $scopeMetrics['scope']['name']);
        $this->assertCount(2, $scopeMetrics['metrics']);

        $expectedAttributes = [
            ['key' => 'env', 'value' => ['stringValue' => 'dev']],
            ['key' => 'TenantUUID', 'value' => ['stringValue' => $dimensions['TenantUUID']]],
        ];
        $expectedTimeUnixNano = (string) ($unixTimestampInMillis * 1_000_000);

        $metricOne = $scopeMetrics['metrics'][0];
        $this->assertEquals('test_namespace.requests_total', $metricOne['name']);
        $this->assertEquals('{count}', $metricOne['unit']);
        $dataPointOne = $metricOne['gauge']['dataPoints'][0];
        $this->assertEquals($expectedAttributes, $dataPointOne['attributes']);
        $this->assertSame($expectedTimeUnixNano, $dataPointOne['timeUnixNano']);
        // OTLP/JSON encodes int64 values as strings.
        $this->assertSame('42', $dataPointOne['asInt']);
        $this->assertArrayNotHasKey('asDouble', $dataPointOne);

        $metricTwo = $scopeMetrics['metrics'][1];
        $this->assertEquals('test_namespace.request_duration', $metricTwo['name']);
        $this->assertEquals('s', $metricTwo['unit']);
        $dataPointTwo = $metricTwo['gauge']['dataPoints'][0];
        $this->assertEquals($expectedAttributes, $dataPointTwo['attributes']);
        $this->assertSame($expectedTimeUnixNano, $dataPointTwo['timeUnixNano']);
        // Doubles stay JSON numbers.
        $this->assertSame(2.5, $dataPointTwo['asDouble']);
        $this->assertArrayNotHasKey('asInt', $dataPointTwo);
    }

    #[Test]
    public function omits_authorization_header_when_credentials_are_absent(): void
    {
        // Self-hosted collector case: endpoint only, no credentials.
        $transport = new OtlpHttpTransport($this->makeGuzzleClient(), 'http://collector.example:4318/');

        $transport->sendMetrics(
            'test_namespace',
            (int) CarbonImmutable::now()->valueOf(),
            ['env' => 'dev'],
            ['requests_total' => ['Value' => 1, 'Unit' => 'Count']],
        );

        $request = $this->container[0]['request'];
        $this->assertFalse($request->hasHeader('Authorization'));
        // A trailing slash on the endpoint must not produce a double slash.
        $this->assertEquals('http://collector.example:4318/v1/metrics', (string) $request->getUri());
    }

    #[Test]
    public function empty_dimensions_serialize_as_an_empty_json_list(): void
    {
        $transport = new OtlpHttpTransport($this->makeGuzzleClient(), 'http://collector.example:4318');

        $transport->sendMetrics(
            'test_namespace',
            (int) CarbonImmutable::now()->valueOf(),
            [],
            ['requests_total' => ['Value' => 1, 'Unit' => 'Count']],
        );

        $rawBody = (string) $this->container[0]['request']->getBody();
        $this->assertStringContainsString('"attributes":[]', $rawBody);
    }

    #[Test]
    public function converts_units_to_ucum_and_passes_unknown_units_through(): void
    {
        $unitMap = [
            'Count' => '{count}',
            'None' => '',
            'Seconds' => 's',
            'Microseconds' => 'us',
            'Milliseconds' => 'ms',
            'Bytes' => 'By',
            'Kilobytes' => 'kBy',
            'Megabytes' => 'MBy',
            'Gigabytes' => 'GBy',
            'Bits' => 'bit',
            'Percent' => '%',
            'Bytes/Second' => 'By/s',
            'Count/Second' => '1/s',
            'Furlongs' => 'Furlongs',
        ];

        $metrics = [];
        foreach (array_keys($unitMap) as $index => $unit) {
            $metrics["metric_{$index}"] = ['Value' => 1, 'Unit' => $unit];
        }

        $transport = new OtlpHttpTransport($this->makeGuzzleClient(), 'http://collector.example:4318');
        $transport->sendMetrics('test_namespace', (int) CarbonImmutable::now()->valueOf(), [], $metrics);

        $body = json_decode((string) $this->container[0]['request']->getBody(), true);
        $sentMetrics = $body['resourceMetrics'][0]['scopeMetrics'][0]['metrics'];

        $this->assertSame(array_values($unitMap), array_column($sentMetrics, 'unit'));
    }

    #[Test]
    public function casts_int_and_float_dimension_values_to_strings(): void
    {
        $transport = new OtlpHttpTransport($this->makeGuzzleClient(), 'http://collector.example:4318');

        $dimensions = ['retries' => 3, 'ratio' => 0.5];
        $expectedAttributes = [
            ['key' => 'retries', 'value' => ['stringValue' => '3']],
            ['key' => 'ratio', 'value' => ['stringValue' => '0.5']],
        ];

        $transport->sendMetrics(
            'test_namespace',
            (int) CarbonImmutable::now()->valueOf(),
            $dimensions,
            ['requests_total' => ['Value' => 1, 'Unit' => 'Count']],
        );

        $body = json_decode((string) $this->container[0]['request']->getBody(), true);
        $attributes = $body['resourceMetrics'][0]['scopeMetrics'][0]['metrics'][0]['gauge']['dataPoints'][0]['attributes'];

        $this->assertSame($expectedAttributes, $attributes);
    }

    #[Test]
    public function throws_when_metric_value_is_not_an_int_or_float(): void
    {
        $transport = new OtlpHttpTransport($this->makeGuzzleClient(), 'http://collector.example:4318');

        $metricName = 'requests_total';
        $garbageValues = [
            'string' => 'five',
            'bool' => true,
            'null' => null,
            'array' => [5],
        ];

        foreach ($garbageValues as $type => $garbageValue) {
            try {
                $transport->sendMetrics(
                    'test_namespace',
                    (int) CarbonImmutable::now()->valueOf(),
                    ['env' => 'dev'],
                    [$metricName => ['Value' => $garbageValue, 'Unit' => 'Count']],
                );

                $this->fail("Expected InvalidArgumentException was not thrown for a {$type} \"Value\".");
            } catch (\InvalidArgumentException $e) {
                $this->assertEquals(
                    "Metric \"{$metricName}\" must have an int or float \"Value\", {$type} given.",
                    $e->getMessage(),
                );
            }
        }

        $this->assertCount(0, $this->container, 'No request should be sent for an invalid metric value.');
    }

    #[Test]
    public function throws_when_dimension_value_is_not_a_string_int_or_float(): void
    {
        $transport = new OtlpHttpTransport($this->makeGuzzleClient(), 'http://collector.example:4318');

        $dimensionName = 'env';
        $garbageDimensions = [
            'bool' => false,
            'null' => null,
            'array' => ['nested'],
            'stdClass' => new \stdClass(),
        ];

        foreach ($garbageDimensions as $type => $garbageDimension) {
            try {
                $transport->sendMetrics(
                    'test_namespace',
                    (int) CarbonImmutable::now()->valueOf(),
                    [$dimensionName => $garbageDimension],
                    ['requests_total' => ['Value' => 1, 'Unit' => 'Count']],
                );

                $this->fail("Expected InvalidArgumentException was not thrown for a {$type} dimension value.");
            } catch (\InvalidArgumentException $e) {
                $this->assertEquals(
                    "Dimension \"{$dimensionName}\" must have a string, int, or float value, {$type} given.",
                    $e->getMessage(),
                );
            }
        }

        $this->assertCount(0, $this->container, 'No request should be sent for an invalid dimension value.');
    }

    #[Test]
    public function throws_when_metric_unit_is_not_a_string(): void
    {
        $transport = new OtlpHttpTransport($this->makeGuzzleClient(), 'http://collector.example:4318');

        $metricName = 'requests_total';
        $garbageUnits = [
            'int' => 1,
            'float' => 1.5,
            'bool' => true,
            'null' => null,
            'array' => ['Count'],
        ];

        foreach ($garbageUnits as $type => $garbageUnit) {
            try {
                $transport->sendMetrics(
                    'test_namespace',
                    (int) CarbonImmutable::now()->valueOf(),
                    ['env' => 'dev'],
                    [$metricName => ['Value' => 1, 'Unit' => $garbageUnit]],
                );

                $this->fail("Expected InvalidArgumentException was not thrown for a {$type} \"Unit\".");
            } catch (\InvalidArgumentException $e) {
                $this->assertEquals(
                    "Metric \"{$metricName}\" must have a string \"Unit\", {$type} given.",
                    $e->getMessage(),
                );
            }
        }

        $this->assertCount(0, $this->container, 'No request should be sent for an invalid metric unit.');
    }

    #[Test]
    public function sends_one_request_per_send_metrics_call(): void
    {
        $endpoint = 'http://collector.example:4318';
        $instanceId = '123456';
        $token = 'glc_test_token';
        $expectedAuthorization = 'Basic ' . base64_encode("{$instanceId}:{$token}");

        $transport = new OtlpHttpTransport(
            $this->makeGuzzleClient(2),
            $endpoint,
            'test-service',
            $instanceId,
            $token,
        );

        $firstNamespace = 'first_namespace';
        $secondNamespace = 'second_namespace';
        $timestamp = (int) CarbonImmutable::now()->valueOf();

        $transport->sendMetrics($firstNamespace, $timestamp, ['env' => 'dev'], [
            'requests_total' => ['Value' => 1, 'Unit' => 'Count'],
        ]);
        $transport->sendMetrics($secondNamespace, $timestamp, ['env' => 'dev'], [
            'request_duration' => ['Value' => 2.5, 'Unit' => 'Seconds'],
        ]);

        $this->assertCount(2, $this->container);

        $expectedMetricNames = [
            "{$firstNamespace}.requests_total",
            "{$secondNamespace}.request_duration",
        ];

        foreach ($this->container as $index => $transaction) {
            $request = $transaction['request'];
            $this->assertEquals("{$endpoint}/v1/metrics", (string) $request->getUri());
            $this->assertEquals($expectedAuthorization, $request->getHeaderLine('Authorization'));

            $body = json_decode((string) $request->getBody(), true);
            $sentMetrics = $body['resourceMetrics'][0]['scopeMetrics'][0]['metrics'];
            // Each request carries only its own batch — no state bleeds between calls.
            $this->assertCount(1, $sentMetrics);
            $this->assertEquals($expectedMetricNames[$index], $sentMetrics[0]['name']);
        }
    }
}
