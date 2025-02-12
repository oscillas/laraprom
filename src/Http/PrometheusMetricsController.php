<?php

declare(strict_types=1);

namespace Oscillas\Laraprom\Http;

use Illuminate\Http\Response;
use Prometheus\RegistryInterface;
use Prometheus\RendererInterface;

class PrometheusMetricsController
{
    public function __invoke(RegistryInterface $registry, RendererInterface $renderer): Response
    {
        $metrics = $registry->getMetricFamilySamples();

        return response(
            content: $renderer->render($metrics),
            headers: ['Content-Type' => 'text/plain; version=0.0.4']
        );
    }
}