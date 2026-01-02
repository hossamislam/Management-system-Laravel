<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TaskController;
use Illuminate\Support\Facades\Route;



Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth:sanctum')->group(function () {

    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/currentUser', [AuthController::class, 'currentUser']);
    });

    Route::apiResource('tasks', TaskController::class);

    Route::post('tasks/{id}/dependencies', [TaskController::class, 'addDependencies']);
});
