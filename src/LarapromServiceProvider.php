<?php

declare(strict_types=1);

namespace Oscillas\Laraprom;

use GuzzleHttp\Client;
use Illuminate\Support\ServiceProvider;
use Oscillas\Laraprom\Helpers\CloudwatchLogsHelper;
use Oscillas\Laraprom\Reporters\CloudwatchMetricReporter;
use Oscillas\Laraprom\Transports\CloudwatchEmfTransport;
use Oscillas\Laraprom\Transports\CloudwatchPutMetricDataTransport;
use Oscillas\Laraprom\Reporters\DatadogReporter;
use Oscillas\Laraprom\Reporters\EventReporterInterface;
use Oscillas\Laraprom\Reporters\MetricReporterInterface;
use Oscillas\Laraprom\Reporters\OtlpMetricReporter;
use Oscillas\Laraprom\Transports\OtlpHttpTransport;
use Oscillas\Laraprom\Reporters\VoidEventReporter;
use Oscillas\Laraprom\Reporters\VoidMetricReporter;
use Oscillas\Laraprom\Reporters\PrometheusMetricReporter;
use Prometheus\CollectorRegistry;
use Prometheus\RegistryInterface;
use Prometheus\RendererInterface;
use Prometheus\RenderTextFormat;

class LarapromServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/application_monitoring.php', 'application_monitoring');

        $this->app->bind(RegistryInterface::class, function () {
            $storageAdapter = new LaravelCacheManagerAdapter($this->app->make('cache'));
            return new CollectorRegistry($storageAdapter, false);
        });

        $this->app->bind(RendererInterface::class, RenderTextFormat::class);

        $this->app->bind(MetricReporterInterface::class, function ($app) {
            $driver = config('application_monitoring.metrics');

            return match ($driver) {
                'cloudwatch' => new CloudwatchMetricReporter(
                    new CloudwatchPutMetricDataTransport(
                        new Client(),
                        config('application_monitoring.drivers.cloudwatch.region'),
                    )
                ),
                'cloudwatch_emf' => new CloudwatchMetricReporter(
                    new CloudwatchEmfTransport(
                        new CloudwatchLogsHelper(
                            new Client(),
                            config('application_monitoring.drivers.cloudwatch_emf.region'),
                        )
                    )
                ),
                'datadog' => new DatadogReporter(
                    new Client([
                        'headers' => [
                            'Content-Type' => 'application/json',
                            'DD-API-KEY' => config('application_monitoring.drivers.datadog.api_key'),
                            'DD-APPLICATION-KEY' => config('application_monitoring.drivers.datadog.app_key'),
                        ]
                    ])
                ),
                'void' => new VoidMetricReporter(),
                'prometheus' => new PrometheusMetricReporter($app->make(RegistryInterface::class)),
                'otlp' => new OtlpMetricReporter(
                    new OtlpHttpTransport(
                        new Client(),
                        config('application_monitoring.drivers.otlp.endpoint'),
                        config('application_monitoring.drivers.otlp.service_name'),
                        config('application_monitoring.drivers.otlp.instance_id'),
                        config('application_monitoring.drivers.otlp.token'),
                    )
                ),
                default => throw new \InvalidArgumentException("Unsupported metric reporter driver: {$driver}"),
            };
        });

        $this->app->bind(EventReporterInterface::class, function ($app) {
            $driver = config('application_monitoring.events');

            return match ($driver) {
                'datadog' => new DatadogReporter(
                    new Client([
                        'headers' => [
                            'Content-Type' => 'application/json',
                            'DD-API-KEY' => config('application_monitoring.drivers.datadog.api_key'),
                            'DD-APPLICATION-KEY' => config('application_monitoring.drivers.datadog.app_key'),
                        ]
                    ])
                ),
                'void' => new VoidEventReporter(),
                default => throw new \InvalidArgumentException("Unsupported event reporter driver: {$driver}"),
            };
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/application_monitoring.php' => config_path('application_monitoring.php'),
        ], 'config');
    }
}
