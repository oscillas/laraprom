<?php

declare(strict_types=1);

namespace Tests\Unit;

use Oscillas\Laraprom\Reporters\MetricReporterInterface;
use PHPUnit\Framework\Attributes\Test;

trait MetricReporterInterfaceTests
{
    abstract protected function getMetricReporter(): MetricReporterInterface;

    abstract protected function assertMetricsSubmitted(
        string $expectedNamespace,
        int    $expectedUnixTimestampMillis,
        array  $expectedDimensions,
        array  $expectedMetrics
    ): void;

    abstract protected function assertDidNotSubmitAnyMetrics(): void;

    #[Test]
    public function can_submit_metrics(): void
    {
        # Arrange
        $reporter = $this->getMetricReporter();

        # Act
        $namespace = 'test_namespace';
        $unixTimestampMillis = (int) (microtime(true) * 1000);

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

        $reporter->putMetric(
            $namespace,
            $unixTimestampMillis,
            $dimensions,
            $metrics,
        );

        # Assert
        $this->assertMetricsSubmitted(
            $namespace,
            $unixTimestampMillis,
            $dimensions,
            $metrics,
        );
    }

    #[Test]
    public function putting_empty_metrics_causes_invalid_argument_exception(): void
    {
        // Arrange
        $reporter = $this->getMetricReporter();

        // Act
        try {
            $reporter->putMetric('doesnt matter', 0, ['foo' => 'bar'], []);
        } catch (\InvalidArgumentException $e) {
            // Assert
            $this->assertEquals('$metrics array cannot be empty.', $e->getMessage());
            $this->assertDidNotSubmitAnyMetrics();
            return;
        }

        $this->fail('Expected InvalidArgumentException was not thrown when $metrics argument is an empty array.');
    }

    #[Test]
    public function putting_metric_with_invalid_structure_throws_error(): void
    {
        // Arrange
        $reporter = $this->getMetricReporter();

        // Act
        try {
            $reporter->putMetric('doesnt_matter', 0, ['foo' => 'bar'], [
                'invalid_metric' => ['Unit' => 'Count']
            ]);

            $this->fail('Expected ErrorException was not thrown when metric structure is invalid.');
        } catch (\Exception $e) {
            // Assert
            $this->assertInstanceOf(\InvalidArgumentException::class, $e);
            $this->assertEquals('Each metric must have a "Value" key.', $e->getMessage());
            $this->assertDidNotSubmitAnyMetrics();
        }

        try {
            $reporter->putMetric('doesnt_matter', 0, ['foo' => 'bar'], [
                'invalid_metric' => ['Value' => 42]
            ]);

            $this->fail('Expected ErrorException was not thrown when metric structure is invalid.');
        } catch (\Exception $e) {
            // Assert
            $this->assertInstanceOf(\InvalidArgumentException::class, $e);
            $this->assertEquals('Each metric must have a "Unit" key.', $e->getMessage());
            $this->assertDidNotSubmitAnyMetrics();
        }
    }
}