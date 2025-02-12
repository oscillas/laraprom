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
        $this->app->bind(RegistryInterface::class, fn() => new CollectorRegistry(new InMemory(), false));
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
