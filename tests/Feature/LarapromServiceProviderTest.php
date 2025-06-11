<?php

namespace Tests\Feature;

use Orchestra\Testbench\Concerns\WithWorkbench;
use Oscillas\Laraprom\Reporters\CloudwatchMetricReporter;
use Oscillas\Laraprom\Reporters\DatadogReporter;
use Oscillas\Laraprom\Reporters\EventReporterInterface;
use Oscillas\Laraprom\Reporters\MetricReporterInterface;
use Oscillas\Laraprom\Reporters\NullMetricReporter;
use Oscillas\Laraprom\Reporters\PrometheusMetricReporter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LarapromServiceProviderTest extends TestCase
{
    use WithWorkbench;

    #[Test]
    public function valid_metric_reporters_get_resolved()
    {
        // Set the configuration for the prometheus driver
        config(['application_monitoring.metrics' => 'prometheus']);
        $reporter = $this->app->make(MetricReporterInterface::class);
        $this->assertInstanceOf(PrometheusMetricReporter::class, $reporter);

        config(['application_monitoring.metrics' => 'cloudwatch']);
        $reporter = $this->app->make(MetricReporterInterface::class);
        $this->assertInstanceOf(CloudwatchMetricReporter::class, $reporter);

        config(['application_monitoring.metrics' => 'datadog']);
        $reporter = $this->app->make(MetricReporterInterface::class);
        $this->assertInstanceOf(DatadogReporter::class, $reporter);

        config(['application_monitoring.metrics' => 'null']);
        $reporter = $this->app->make(MetricReporterInterface::class);
        $this->assertInstanceOf(NullMetricReporter::class, $reporter);
    }

    #[Test]
    public function invalid_metric_reporter_causes_exception(): void
    {
        # Arrange
        $invalidDriver = uniqid();
        config(['application_monitoring.metrics' => $invalidDriver]);

        # Act
        try {
            $this->app->make(MetricReporterInterface::class);
        } catch (\InvalidArgumentException $e) {
            # Assert
            $this->assertEquals("Unsupported metric reporter driver: {$invalidDriver}", $e->getMessage());
            return;
        }

        $this->fail('Expected InvalidArgumentException was not thrown when an invalid metric reporter driver is set.');
    }

    #[Test]
    public function valid_event_reporters_get_resolved(): void
    {
        config(['application_monitoring.metrics' => 'datadog']);
        $reporter = $this->app->make(EventReporterInterface::class);
        $this->assertInstanceOf(DatadogReporter::class, $reporter);
    }

    #[Test]
    public function invalid_event_reporter_causes_exception(): void
    {
        # Arrange
        $invalidDriver = uniqid();

        # Act
        try {
            config(['application_monitoring.events' => $invalidDriver]);
            $this->app->make(EventReporterInterface::class);
        } catch (\InvalidArgumentException $e) {
            # Assert
            $this->assertEquals("Unsupported event reporter driver: {$invalidDriver}", $e->getMessage());
            return;
        }

        $this->fail('Expected InvalidArgumentException was not thrown when an invalid event reporter driver is set.');
    }
}
