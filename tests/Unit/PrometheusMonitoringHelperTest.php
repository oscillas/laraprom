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
            $metricNameOne => [
                'Unit' => 'Count',
                'Value' => 42
            ],
            $metricNameTwo => [
                'Unit' => 'Count',
                'Value' => 13
            ],
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
            $this->assertEquals($value['Value'], $sample->getValue());
            $this->assertEquals($dimensions, $sample->getLabelValues());
        }
    }

    #[Test]
    public function putting_empty_metrics_causes_invalid_argument_exception(): void
    {
        // Arrange
        $registry = new CollectorRegistry(new InMemory(), false);
        $helper = new PrometheusMonitoringHelper($registry);
        $namespace = 'test';
        $dimensions = ['env' => 'dev'];

        // Act
        try {
            $helper->putMetric($namespace, 0, $dimensions, []);
        } catch (\InvalidArgumentException $e) {
            // Assert
            $this->assertEquals('$metrics array cannot be empty.', $e->getMessage());
            $this->assertEmpty($registry->getMetricFamilySamples());
            return;
        }

        $this->fail('Expected InvalidArgumentException was not thrown when $metrics argument is an empty array.');
    }

    #[Test]
    public function updating_an_existing_metric_overwrites_the_value(): void
    {
        // Arrange
        $registry = new CollectorRegistry(new InMemory(), false);
        $helper = new PrometheusMonitoringHelper($registry);
        $namespace = 'test';
        $metricName = 'requests_total';
        $dimensions = ['env' => 'dev'];

        $initialMetrics = [
            $metricName => ['Unit' => 'Count', 'Value' => 42]
        ];
        $updatedMetrics = [
            $metricName => ['Unit' => 'Count', 'Value' => $initialMetrics[$metricName]['Value'] + 1]
        ];

        // Act – insert initial value
        $helper->putMetric($namespace, 0, $dimensions, $initialMetrics);

        // Act – update gauge value
        $helper->putMetric($namespace, 0, $dimensions, $updatedMetrics);

        // Assert – retrieve metrics from registry
        $samples = $registry->getMetricFamilySamples();
        // There should be only one metric family.
        $this->assertCount(1, $samples);
        $family = $samples[0];

        // Check the sample value is now updated.
        $samples = $family->getSamples();
        $this->assertCount(1, $samples);
        $this->assertEquals($updatedMetrics[$metricName]['Value'], $samples[0]->getValue());
    }

    #[Test]
    public function putting_metric_with_invalid_structure_throws_error(): void
    {
        // Arrange
        $registry = new CollectorRegistry(new InMemory(), false);
        $helper = new PrometheusMonitoringHelper($registry);
        $namespace = 'test';
        $dimensions = ['env' => 'dev'];
        $metrics = [
            'invalid_metric' => ['Unit' => 'Count'] // Missing 'Value'
        ];

        // Act
        try {
            $helper->putMetric($namespace, 0, $dimensions, $metrics);
        } catch (\ErrorException $e) {
            // Assert
            $this->assertEquals('Undefined array key "Value"', $e->getMessage());
            $this->assertEmpty($registry->getMetricFamilySamples());
            return;
        }

        $this->fail('Expected ErrorException was not thrown when metric structure is invalid.');
    }
}
