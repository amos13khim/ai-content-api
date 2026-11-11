<?php

declare(strict_types=1);

use App\Http\Controllers\GenerationController;
use App\Http\Controllers\UsageController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('auth:sanctum')->group(function (): void {
    Route::post('generations', [GenerationController::class, 'store'])->middleware(['abilities:generations:write', 'throttle:api-write']);
    Route::middleware(['abilities:generations:read', 'throttle:api-read'])->group(function (): void {
        Route::get('generations', [GenerationController::class, 'index']);
        Route::get('generations/{generation}', [GenerationController::class, 'show'])->whereUlid('generation')->name('generations.show');
        Route::get('usage', UsageController::class);
    });
});
