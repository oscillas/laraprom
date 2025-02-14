<?php

declare(strict_types=1);

namespace Oscillas\Laraprom\Helpers;

use InvalidArgumentException;
use Oscillas\Laraprom\Reporters\MetricReporterInterface;
use Prometheus\CollectorRegistry;
use Prometheus\Exception\MetricsRegistrationException;

class PrometheusMonitoringHelper implements MetricReporterInterface
{
    private CollectorRegistry $registry;

    public function __construct(CollectorRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * @throws InvalidArgumentException|MetricsRegistrationException
     */
    public function putMetric(
        string $namespace,
        int $unixTimestampMillis,
        array $dimensions,
        array $metrics
    ): void {
        if ([] === $metrics) {
            throw new InvalidArgumentException('$metrics array cannot be empty.');
        } else {
            foreach ($metrics as $metric) {
                if (!isset($metric['Value'])) {
                    throw new InvalidArgumentException('Each metric must have a "Value" key.');
                }

                if (!isset($metric['Unit'])) {
                    throw new InvalidArgumentException('Each metric must have a "Unit" key.');
                }
            }
        }

        foreach ($metrics as $metricName => $metricData) {
            // Register a Gauge metric.
            $gauge = $this->registry->getOrRegisterGauge(
                $namespace,
                $metricName,
                'Automatically registered gauge',
                array_keys($dimensions)
            );

            $gauge->set($metricData['Value'], $dimensions);
        }
    }
}
