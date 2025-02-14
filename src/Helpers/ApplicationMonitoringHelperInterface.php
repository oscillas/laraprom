<?php

declare(strict_types=1);

namespace Oscillas\Laraprom\Helpers;

use Oscillas\Laraprom\Reporters\EventReporterInterface;
use Oscillas\Laraprom\Reporters\MetricReporterInterface;

/**
 * @deprecated 0.0.5 Should not be used for new code. Use EventReporterInterface or MetricReporterInterface instead.
 * @see EventReporterInterface
 * @see MetricReporterInterface
 */
interface ApplicationMonitoringHelperInterface extends EventReporterInterface, MetricReporterInterface
{
}
