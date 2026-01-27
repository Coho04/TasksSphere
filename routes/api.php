<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TaskApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/fcm-token', [AuthController::class, 'updateFcmToken']);
    Route::get('/tasks', [TaskApiController::class, 'index']);
    Route::get('/tasks/occurrences', [TaskApiController::class, 'occurrences']);
    Route::post('/tasks', [TaskApiController::class, 'store']);
    Route::get('/tasks/{task}', [TaskApiController::class, 'show']);
    Route::put('/tasks/{task}', [TaskApiController::class, 'update']);
    Route::delete('/tasks/{task}', [TaskApiController::class, 'destroy']);
    Route::post('/tasks/{task}/complete', [TaskApiController::class, 'complete']);
    Route::post('/tasks/{task}/skip', [TaskApiController::class, 'skip']);
});
