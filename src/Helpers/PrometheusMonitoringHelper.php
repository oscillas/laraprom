<?php

declare(strict_types=1);

namespace Oscillas\Laraprom\Helpers;

use InvalidArgumentException;
use Prometheus\CollectorRegistry;
use Prometheus\Exception\MetricsRegistrationException;

class PrometheusMonitoringHelper implements ApplicationMonitoringHelperInterface
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

    public function putEvent(
        string $title,
        int $unixTimestampMillis,
        array $dimensions,
        string $text
    ): void {
        throw new \BadMethodCallException("Function 'putEvent' is not implemented for PrometheusMonitoringHelper. Prometheus is not designed to handle events.");
    }
}
