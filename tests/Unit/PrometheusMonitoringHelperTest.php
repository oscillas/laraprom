<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Prometheus\Gauge;
use Tests\TestCase;
use Prometheus\CollectorRegistry;
use Prometheus\Storage\InMemory;
use Oscillas\Laraprom\Helpers\PrometheusMonitoringHelper;

final class PrometheusMonitoringHelperTest extends TestCase
{
    #[Test]
    public function putting_event_throws_not_implemented_exception(): void
    {
        // Arrange
        $registry = new CollectorRegistry(new InMemory(), false);
        $prometheusMonitoringHelper = new PrometheusMonitoringHelper($registry);

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage("Function 'putEvent' is not implemented for PrometheusMonitoringHelper. Prometheus is not designed to handle events.");

        // Act
        $prometheusMonitoringHelper->putEvent(
            'Test Event',
            0,
            ['env' => 'test'],
            'Test event text'
        );
    }

    #[Test]
    public function putting_a_single_metric_saves_gauge_to_registry_interface(): void
    {
        // Arrange
        $registry = new CollectorRegistry(new InMemory(), false);
        $prometheusMonitoringHelper = new PrometheusMonitoringHelper($registry);

        $namespace = 'test';
        $metricNameOne = 'a_requests_total';
        $metricNameTwo = 'b_requests_failed_total';
        $dimensions = ['env' => 'dev'];
        $metrics = [
            $metricNameOne => 42,
            $metricNameTwo => 13,
        ];

        // Act
        $prometheusMonitoringHelper->putMetric(
            $namespace,
            0,
            $dimensions,
            $metrics
        );

        // Assert
        $metricFamilySamples = $registry->getMetricFamilySamples(sortMetrics: true);
        $this->assertCount(2, $metricFamilySamples);

        $counter = 0;
        foreach ($metrics as $metricName => $value) {
            $family = $metricFamilySamples[$counter++];
            $this->assertEquals(Gauge::TYPE, $family->getType());

            $samples = $family->getSamples();
            $this->assertCount(1, $samples);
            $sample = $samples[0];
            $this->assertEquals("{$namespace}_{$metricName}", $sample->getName());
            $this->assertEquals($value, $sample->getValue());
            $this->assertEquals($dimensions, $sample->getLabelValues());
        }
    }
}
