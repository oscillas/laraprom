<?php

declare(strict_types=1);

namespace Oscillas\Laraprom\Reporters;

final class VoidMetricReporter implements MetricReporterInterface
{
    public function putMetric(string $namespace, int $unixTimestampMillis, array $dimensions, array $metrics): void
    {
        // do nothing
    }
}