<?php

declare(strict_types=1);

namespace Tests\Http;

use Orchestra\Testbench\Concerns\WithWorkbench;
use PHPUnit\Framework\Attributes\Test;
use Prometheus\CollectorRegistry;
use Prometheus\RegistryInterface;
use Prometheus\Storage\InMemory;
use Tests\TestCase;

class PrometheusMetricsControllerTest extends TestCase
{
    use WithWorkbench;

    #[Test]
    public function renders_metrics_from_bound_collector_registry(): void
    {
        # Arrange
        $registry = new CollectorRegistry(new InMemory(), false);

        $namespace = 'namespace';
        $name = 'name';
        $help = 'this is a test gauge';
        $labels = ['label1', 'label2'];

        $gauge = $registry->registerGauge(
            $namespace,
            $name,
            $help,
            $labels,
        );

        $value = 123.0;
        $gauge->set($value, ['value1', 'value2']);

        $this->app->bind(RegistryInterface::class, fn() => $registry);

        # Act
        $response = $this->get(route('prometheus'));

        # Assert
        $response->assertStatus(200);
        $response->assertHeader(
            'Content-Type',
            'text/plain; version=0.0.4; charset=UTF-8',
        );
        $response->assertSeeText("{$namespace}_{$name}");
        $response->assertSeeText("# HELP {$namespace}_{$name} {$help}");
        $response->assertSeeText("# TYPE {$namespace}_{$name} gauge");
        $response->assertSeeText("{$namespace}_{$name}{label1=\"value1\",label2=\"value2\"} 123", escape: false);
    }
}