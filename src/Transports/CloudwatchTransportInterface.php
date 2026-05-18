<?php

declare(strict_types=1);

namespace Oscillas\Laraprom\Transports;

interface CloudwatchTransportInterface
{
    public function sendMetrics(
        string $namespace,
        int $unixTimestampMillis,
        array $dimensions,
        array $metrics,
    ): void;
}
