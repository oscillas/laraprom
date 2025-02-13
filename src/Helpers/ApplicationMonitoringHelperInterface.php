<?php

declare(strict_types=1);

namespace Oscillas\Laraprom\Helpers;

interface ApplicationMonitoringHelperInterface
{
    public function putEvent(
        string $title,
        int $unixTimestampMillis,
        array $dimensions,
        string $text,
    ): void;

    public function putMetric(
        string $namespace,
        int $unixTimestampMillis,
        array $dimensions,
        array $metrics,
    ): void;
}
