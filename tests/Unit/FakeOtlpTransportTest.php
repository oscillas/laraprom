<?php

declare(strict_types=1);

namespace Tests\Unit;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\TestDoubles\Listeners\FakeOtlpTransport;

class FakeOtlpTransportTest extends TestCase
{
    #[Test]
    public function starts_with_no_recorded_calls(): void
    {
        $transport = new FakeOtlpTransport();

        $this->assertSame([], $transport->calls);
    }

    #[Test]
    public function records_each_send_metrics_call_in_order(): void
    {
        $transport = new FakeOtlpTransport();

        $firstTimestamp = (int) CarbonImmutable::now()->valueOf();
        $firstDimensions = ['env' => 'dev', 'TenantUUID' => bin2hex(random_bytes(16))];
        $firstMetrics = ['requests_total' => ['Value' => 42, 'Unit' => 'Count']];

        $secondTimestamp = $firstTimestamp + 1000;
        $secondDimensions = [];
        $secondMetrics = ['request_duration' => ['Value' => 2.5, 'Unit' => 'Seconds']];

        $transport->sendMetrics('first_namespace', $firstTimestamp, $firstDimensions, $firstMetrics);
        $transport->sendMetrics('second_namespace', $secondTimestamp, $secondDimensions, $secondMetrics);

        $this->assertSame([
            [
                'Namespace' => 'first_namespace',
                'Timestamp' => $firstTimestamp,
                'Dimensions' => $firstDimensions,
                'Metrics' => $firstMetrics,
            ],
            [
                'Namespace' => 'second_namespace',
                'Timestamp' => $secondTimestamp,
                'Dimensions' => $secondDimensions,
                'Metrics' => $secondMetrics,
            ],
        ], $transport->calls);
    }
}
