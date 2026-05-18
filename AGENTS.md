# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Laraprom (`oscillas/laraprom`) is a Laravel package that provides a unified interface for application monitoring — metrics and events — across multiple backends (Datadog, CloudWatch, Prometheus). It uses a driver-based architecture configured via `config/application_monitoring.php`.

## Commands

```bash
# Install dependencies
composer install

# Run all tests
vendor/bin/phpunit

# Run a specific test suite (Feature, Http, Integration, Unit)
vendor/bin/phpunit --testsuite=Unit

# Run a single test file
vendor/bin/phpunit tests/Unit/PrometheusMonitoringHelperTest.php

# Run a single test method
vendor/bin/phpunit --filter=can_submit_metrics

# Static analysis (level 5, with Larastan)
vendor/bin/phpstan analyse

# Serve the workbench app (for manual testing)
composer serve
```

Docker (provides PostgreSQL + Valkey for integration tests):
```bash
docker compose up -d
docker compose exec php vendor/bin/phpunit
```

## Architecture

**Driver pattern**: `LarapromServiceProvider` binds `MetricReporterInterface` and `EventReporterInterface` to concrete implementations based on config values `application_monitoring.metrics` and `application_monitoring.events`. Drivers are resolved via `match` expressions — adding a new driver means adding a case there and a new implementation class.

**Reporter interfaces** (`src/Reporters/`):
- `MetricReporterInterface::putMetric(namespace, timestamp, dimensions, metrics)` — metrics array uses `['MetricName' => ['Value' => x, 'Unit' => 'Count']]` format
- `EventReporterInterface::putEvent(title, timestamp, dimensions, text)`
- `DatadogReporter` implements both interfaces; other reporters implement one
- `VoidMetricReporter` / `VoidEventReporter` are no-op implementations for the `"void"` driver

**Prometheus storage**: `LaravelCacheManagerAdapter` bridges Prometheus's `Adapter` interface to Laravel's `CacheManager`, persisting metric state across requests via cache.

**Testing**: Uses Orchestra Testbench. Test base class is `Tests\TestCase` extending `Orchestra\Testbench\TestCase`. The `MetricReporterInterfaceTests` trait provides shared contract tests for any `MetricReporterInterface` implementation — concrete test classes implement `getMetricReporter()`, `assertMetricsSubmitted()`, and `assertDidNotSubmitAnyMetrics()`. Tests use PHPUnit 11 attributes (`#[Test]`).
