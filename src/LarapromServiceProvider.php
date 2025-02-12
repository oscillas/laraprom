<?php

declare(strict_types=1);

namespace Oscillas\Laraprom;

use Illuminate\Support\ServiceProvider;
use Prometheus\RendererInterface;
use Prometheus\RenderTextFormat;

class LarapromServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
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
