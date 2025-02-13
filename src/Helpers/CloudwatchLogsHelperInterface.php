<?php

declare(strict_types=1);

namespace Oscillas\Laraprom\Helpers;

interface CloudwatchLogsHelperInterface
{
    public function putEmbeddedMetric(
        string $logGroup,
        string $logStream,
        string $namespace,
        int $unixTimestampMillis,
        array $dimensions,
        array $metrics,
    ): void;
}
