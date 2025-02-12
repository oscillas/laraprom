<?php

use Illuminate\Support\Facades\Route;
use Oscillas\Laraprom\Http\PrometheusMetricsController;

Route::get(
    '/metrics',
    PrometheusMetricsController::class
)->name('prometheus');