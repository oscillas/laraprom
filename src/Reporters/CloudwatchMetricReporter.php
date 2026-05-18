<?php

declare(strict_types=1);

namespace Oscillas\Laraprom\Reporters;

use Oscillas\Laraprom\Transports\CloudwatchTransportInterface;

class CloudwatchMetricReporter implements MetricReporterInterface
{
    public function __construct(protected CloudwatchTransportInterface $transport)
    {
    }

    public function putMetric(string $namespace, int $unixTimestampMillis, array $dimensions, array $metrics): void
    {
        if (!array_key_exists('TenantUUID', $dimensions)) {
            throw new \InvalidArgumentException('TenantUUID must be present in dimensions array');
        }

        $this->transport->sendMetrics($namespace, $unixTimestampMillis, $dimensions, $metrics);
    }
}
