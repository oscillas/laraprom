<?php

namespace Tests\Feature;

use Orchestra\Testbench\Concerns\WithWorkbench;
use Oscillas\Laraprom\Helpers\ApplicationMonitoringHelperInterface;
use Oscillas\Laraprom\Reporters\PrometheusMetricReporter;
use Tests\TestCase;

class LarapromServiceProviderTest extends TestCase
{
    use WithWorkbench;

    public function test_prometheus_monitoring_helper_is_resolved()
    {
        // Set the configuration for the prometheus driver
        config(['application_monitoring.default' => 'prometheus']);
        
        // Resolve the monitoring helper from the container
        $helper = $this->app->make(ApplicationMonitoringHelperInterface::class);
        
        // Assert we get the PrometheusMonitoringHelper
        $this->assertInstanceOf(PrometheusMetricReporter::class, $helper);
    }
}
