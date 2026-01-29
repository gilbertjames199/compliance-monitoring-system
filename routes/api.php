<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ComplianceController;

// Route::get('/compliance-monitoring-system', [ComplianceController::class, 'index']);

Route::middleware('auth:sanctum')->get(
    '/compliance-monitoring-system',
    [ComplianceController::class, 'index']
);