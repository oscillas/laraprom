<?php

declare(strict_types=1);

namespace Oscillas\Laraprom\Reporters;

use InvalidArgumentException;
use Oscillas\Laraprom\Transports\OtlpTransportInterface;

class OtlpMetricReporter implements MetricReporterInterface
{
    public function __construct(protected OtlpTransportInterface $transport)
    {
    }

    /**
     * @throws InvalidArgumentException
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

        foreach ($metrics as $metric) {
            if (!isset($metric['Value'])) {
                throw new InvalidArgumentException('Each metric must have a "Value" key.');
            }

            if (!isset($metric['Unit'])) {
                throw new InvalidArgumentException('Each metric must have a "Unit" key.');
            }
        }

        $this->transport->sendMetrics($namespace, $unixTimestampMillis, $dimensions, $metrics);
    }
}
