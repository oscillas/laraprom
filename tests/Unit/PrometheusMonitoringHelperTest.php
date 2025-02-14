<?php

namespace Tests\Unit;

use Oscillas\Laraprom\Reporters\MetricReporterInterface;
use PHPUnit\Framework\Attributes\Test;
use Prometheus\Gauge;
use Tests\TestCase;
use Prometheus\CollectorRegistry;
use Prometheus\Storage\InMemory;
use Oscillas\Laraprom\Helpers\PrometheusMonitoringHelper;

final class PrometheusMonitoringHelperTest extends TestCase
{
    use MetricReporterInterfaceTests;

    private CollectorRegistry $registry;

    private PrometheusMonitoringHelper $reporter;

    protected function getMetricReporter(): MetricReporterInterface
    {
        $this->registry = new CollectorRegistry(new InMemory(), false);
        $this->reporter = new PrometheusMonitoringHelper($this->registry);
        return $this->reporter;
    }

    protected function assertMetricsSubmitted(
        string $expectedNamespace,
        int    $expectedUnixTimestampMillis,
        array  $expectedDimensions,
        array  $expectedMetrics
    ): void {
        // Assert
        $metricFamilySamples = $this->registry->getMetricFamilySamples(sortMetrics: true);
        $this->assertCount(2, $metricFamilySamples);

        $counter = 0;
        foreach ($expectedMetrics as $metricName => $value) {
            $family = $metricFamilySamples[$counter++];
            $this->assertEquals(Gauge::TYPE, $family->getType());

            $samples = $family->getSamples();
            $this->assertCount(1, $samples);
            $sample = $samples[0];
            $this->assertEquals("{$expectedNamespace}_{$metricName}", $sample->getName());
            $this->assertEquals($value['Value'], $sample->getValue());
            $this->assertEquals($expectedDimensions, $sample->getLabelValues());
        }
    }

    protected function assertDidNotSubmitAnyMetrics(): void
    {
        $this->assertEmpty($this->registry->getMetricFamilySamples());
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
}
