<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MyClientController;

/*
    |--------------------------------------------------------------------------
    | API Routes
    |--------------------------------------------------------------------------
    |
    | Here is where you can register API routes for your application. These
    | routes are loaded by the RouteServiceProvider within a group which
    | is assigned the "api" middleware group. Enjoy building your API!
    |
*/

Route::prefix('v1')->group(function () {
    Route::prefix('clients')->group(function () {
        Route::get('/', [MyClientController::class, 'index']);
        Route::post('/', [MyClientController::class, 'store']);
        Route::get('/{slug}', [MyClientController::class, 'show']);
        Route::put('/{id}', [MyClientController::class, 'update']);
        Route::delete('/{id}', [MyClientController::class, 'destroy']);
    });
});

