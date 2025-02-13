<?php

namespace Tests\TestDoubles\Listeners;

use Oscillas\Laraprom\Helpers\ApplicationMonitoringHelperInterface;

class FakeApplicationMonitoringHelper implements ApplicationMonitoringHelperInterface
{
    public array $metrics = [];

    public array $events = [];

    public function __construct()
    {
    }

    public function putMetric(
        string $namespace,
        int $unixTimestampMillis,
        array $dimensions,
        array $metrics,
    ): void {
        $this->metrics[] = [
            'Namespace' => $namespace,
            'Timestamp' => $unixTimestampMillis,
            'Dimensions' => $dimensions,
            'Metrics' => $metrics,
        ];
    }

    public function putEvent(string $title, int $unixTimestampMillis, array $dimensions, string $text): void
    {
        $this->events[] = [
            'Title' => $title,
            'Timestamp' => $unixTimestampMillis,
            'Dimensions' => $dimensions,
            'Text' => $text,
        ];
    }
}
