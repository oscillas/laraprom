<?php

declare(strict_types=1);

namespace Oscillas\Laraprom\Helpers;

class CloudwatchMonitoringHelper implements ApplicationMonitoringHelperInterface
{
    public function __construct(protected CloudwatchLogsHelperInterface $cloudwatchLogsHelper)
    {
    }

    public function putEvent(string $title, int $unixTimestampMillis, array $dimensions, string $text): void
    {
        throw new \BadMethodCallException("Function 'putEvent' is not yet implemented for CloudwatchLoggingHelper");
    }

    public function putMetric(string $namespace, int $unixTimestampMillis, array $dimensions, array $metrics): void
    {
        if (!array_key_exists('TenantUUID', $dimensions)) {
            throw new \InvalidArgumentException('TenantUUID must be present in dimensions array');
        }

        $logStream = "{$dimensions['TenantUUID']}/pipelines";

        $this->cloudwatchLogsHelper->putEmbeddedMetric(
            '/artemis/cloud',
            $logStream,
            $namespace,
            $unixTimestampMillis,
            $dimensions,
            $metrics,
        );
    }
}
