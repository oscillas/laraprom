<?php

declare(strict_types=1);

namespace Oscillas\Laraprom;

use GuzzleHttp\Client;
use Illuminate\Support\ServiceProvider;
use Oscillas\Laraprom\Helpers\ApplicationMonitoringHelperInterface;
use Oscillas\Laraprom\Helpers\CloudwatchLogsHelper;
use Oscillas\Laraprom\Helpers\CloudwatchMonitoringHelper;
use Oscillas\Laraprom\Helpers\DatadogMonitoringHelper;
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

        $this->app->bind(ApplicationMonitoringHelperInterface::class, function ($app) {
            $driver = config('application_monitoring.default');

            return match ($driver) {
                'cloudwatch' => new CloudwatchMonitoringHelper(
                    new CloudwatchLogsHelper(
                        new Client(),
                        config('application_monitoring.drivers.cloudwatch.region'),
                    )
                ),
                'datadog' => new DatadogMonitoringHelper(
                    new Client([
                        'headers' => [
                            'Content-Type' => 'application/json',
                            'DD-API-KEY' => config('application_monitoring.drivers.datadog.api_key'),
                            'DD-APPLICATION-KEY' => config('application_monitoring.drivers.datadog.app_key'),
                        ]
                    ])
                ),
                'prometheus' => new PrometheusMetricReporter($this->app->make(RegistryInterface::class)),
                default => throw new \InvalidArgumentException("Unsupported application monitoring driver: {$driver}"),
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
