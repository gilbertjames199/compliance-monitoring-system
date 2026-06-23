<?php

use App\Http\Controllers\Api\ComplianceController;
use App\Http\Controllers\Api\ComplianceTrackingPrintController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Api\RequiredDocumentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Route::get('/compliance-monitoring-system', [ComplianceController::class, 'index']);

// Route::middleware('auth:sanctum')->get(
//     '/compliance-monitoring-system',
//     [ComplianceController::class, 'index']
// );

// Authentication routes
// Route::post('/register', [AuthController::class, 'register']);
// Route::post('/login', [AuthController::class, 'login']);

// // Protected routes
// Route::middleware('auth:sanctum')->group(function () {
//     // Auth routes
//     Route::post('/logout', [AuthController::class, 'logout']);
//     Route::post('/logout-all', [AuthController::class, 'logoutAll']);
//     Route::get('/user', [AuthController::class, 'user']);
//     Route::post('/refresh', [AuthController::class, 'refresh']);
    
//     // Compliance routes
//     Route::get('/compliance-monitoring-system', [ComplianceController::class, 'index']);
// });


// Route::get('/compliance-monitoring-system', [ComplianceController::class, 'index']);


// Route::middleware('auth:sanctum')->get('/user', function (Request $request){
//     return $request->user();
// });



Route::post('/register', [AuthController::class, 'register']);
Route::post('/authenticate/get-access-token', [AuthController::class, 'accessToken']);

// Protected routes — require Sanctum token
Route::middleware('auth:sanctum')->group(function () {
    // Get current authenticated user
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::get('/compliance-monitoring-system', [ComplianceController::class, 'index']);
    
     // Logout current token
    Route::post('/logout', [AuthController::class, 'logout']);
});

//http://172.31.102.215:8001/api/required-documents?department_code=26
Route::get('/required-documents', [RequiredDocumentController::class, 'show']);
// Route::get('/compliance-monitoring-system', [ComplianceController::class, 'index']);



Route::get('compliance/print', [ComplianceTrackingPrintController::class, 'print']);
