<?php

declare(strict_types=1);

namespace Oscillas\Laraprom;

use Illuminate\Support\ServiceProvider;
use Prometheus\CollectorRegistry;
use Prometheus\RegistryInterface;
use Prometheus\RendererInterface;
use Prometheus\RenderTextFormat;
use Prometheus\Storage\InMemory;

class LarapromServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(RegistryInterface::class, function () {
            $storageAdapter = new LaravelCacheManagerAdapter($this->app->make('cache'));
            return new CollectorRegistry($storageAdapter, false);
        });

        $this->app->bind(RendererInterface::class, RenderTextFormat::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
