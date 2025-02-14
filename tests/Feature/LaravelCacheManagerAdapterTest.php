<?php

namespace Tests\Feature;

use Orchestra\Testbench\Concerns\WithWorkbench;
use Oscillas\Laraprom\LaravelCacheManagerAdapter;
use PHPUnit\Framework\Attributes\Test;
use Prometheus\Counter;
use Prometheus\Gauge;
use Prometheus\Histogram;
use Prometheus\Storage\Adapter;
use Prometheus\Summary;
use Tests\TestCase;

class LaravelCacheManagerAdapterTest extends TestCase
{
    use WithWorkbench;

    private function updateAndAssertMetric(array $data, string $updateMethod, ?float $expectedValue = null): void
    {
        $cache = $this->app->make('cache');
        $adapter = new LaravelCacheManagerAdapter($cache);

        // Call the update method that was passed in.
        $adapter->$updateMethod($data);

        // Reinstantiate the adapter to simulate retrieving from cache.
        $newAdapter = new LaravelCacheManagerAdapter($cache);
        $metrics = $newAdapter->collect();

        // Find the metric family by name.
        $this->assertCount(1, $metrics);

        $metricFamily = $metrics[0];
        $this->assertEquals($data['name'], $metricFamily->getName());
        $this->assertEquals($data['help'], $metricFamily->getHelp());
        $this->assertEquals($data['type'], $metricFamily->getType());

        $samples = $metricFamily->getSamples();
        if ($data['type'] === Gauge::TYPE || $data['type'] === Counter::TYPE) {
            // Expect exactly one sample for gauge/counter.
            $this->assertCount(1, $samples, "Metric {$data['name']} should have one sample");
            $this->assertEquals($expectedValue, $samples[0]->getValue());
        } else { // Histogram or Summary
            // Find the sample with a name ending in '_sum'
            $foundSum = false;
            $sumValue = null;
            foreach ($samples as $sample) {
                if (str_ends_with($sample->getName(), '_sum')) {
                    $foundSum = true;
                    $sumValue = $sample->getValue();
                    break;
                }
            }
            $this->assertTrue($foundSum, "Expected _sum sample for metric {$data['name']}");
            if ($expectedValue !== null) {
                $this->assertEquals($expectedValue, $sumValue, "Unexpected _sum value for metric {$data['name']}");
            }
        }
    }
    #[Test]
    public function can_set_and_increment_gauge(): void
    {
        $data = [
            'name'        => 'test_gauge_metric',
            'help'        => 'Test gauge metric',
            'type'        => Gauge::TYPE,
            'labelNames'  => ['label'],
            'labelValues' => ['value'],
            'value'       => 10.5,
            'command'     => Adapter::COMMAND_SET,
        ];
        $this->updateAndAssertMetric($data, 'updateGauge', 10.5);

        $data['command'] = Adapter::COMMAND_INCREMENT_FLOAT;
        $this->updateAndAssertMetric($data, 'updateGauge', 21.0);
    }

    #[Test]
    public function can_increment_counter(): void
    {
        $data = [
            'name'        => 'test_counter_metric',
            'help'        => 'Test counter metric',
            'type'        => Counter::TYPE,
            'labelNames'  => ['label'],
            'labelValues' => ['value'],
            'value'       => 3,
            'command'     => Adapter::COMMAND_INCREMENT_INTEGER,
        ];
        $this->updateAndAssertMetric($data, 'updateCounter', 3);

        $data['value'] = 7;
        $this->updateAndAssertMetric($data, 'updateCounter', 10);
    }

    #[Test]
    public function can_observe_histogram(): void
    {
        $data = [
            'name'        => 'test_histogram_metric',
            'help'        => 'Test histogram metric',
            'type'        => Histogram::TYPE,
            'labelNames'  => ['label'],
            'labelValues' => ['value'],
            'value'       => 5.0,
            'buckets'     => Histogram::exponentialBuckets(1, 2, 3),
        ];
        // First update: record an observation of 5.0 and expect _sum = 5.0.
        $this->updateAndAssertMetric($data, 'updateHistogram', 5.0);
    
        // Second update: record an observation of 7.0 and expect _sum to increase to 12.0.
        $data['value'] = 7.0;
        $this->updateAndAssertMetric($data, 'updateHistogram', 12.0);
    }

    #[Test]
    public function can_set_summary(): void
    {
        $data = [
            'name'          => 'test_summary_metric',
            'help'          => 'Test summary metric',
            'type'          => Summary::TYPE,
            'labelNames'    => ['label'],
            'labelValues'   => ['value'],
            'value'         => 0.95,
            'maxAgeSeconds' => 60,
            'quantiles'     => Summary::getDefaultQuantiles(),
        ];
        // First update: record an observation of 0.95 and expect _sum = 0.95.
        $this->updateAndAssertMetric($data, 'updateSummary', 0.95);
    
        // Second update: record a second 0.95 observation and expect _sum to increase to 1.90.
        $this->updateAndAssertMetric($data, 'updateSummary', 1.90);
    }
}
