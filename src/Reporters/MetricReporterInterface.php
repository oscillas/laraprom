<?php

namespace Oscillas\Laraprom\Reporters;

interface MetricReporterInterface
{
    public function putMetric(
        string $namespace,
        int $unixTimestampMillis,
        array $dimensions,
        array $metrics,
    ): void;
}