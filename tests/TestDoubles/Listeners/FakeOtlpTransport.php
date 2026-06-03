<?php

declare(strict_types=1);

namespace Tests\TestDoubles\Listeners;

use Oscillas\Laraprom\Transports\OtlpTransportInterface;

class FakeOtlpTransport implements OtlpTransportInterface
{
    public array $calls = [];

    public function sendMetrics(
        string $namespace,
        int $unixTimestampMillis,
        array $dimensions,
        array $metrics,
    ): void {
        $this->calls[] = [
            'Namespace' => $namespace,
            'Timestamp' => $unixTimestampMillis,
            'Dimensions' => $dimensions,
            'Metrics' => $metrics,
        ];
    }
}
