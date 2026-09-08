<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\HealthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get("/health", HealthController::class);

Route::prefix("/auth")
    ->as("auth.")
    ->group(function () {
        Route::post("/register", [AuthController::class, "register"])
            ->name("register")
            ->middleware(["throttle:reg"]);
        Route::post("/login", [AuthController::class, "login"])
            ->name("login")
            ->middleware(["throttle:login"]);
        Route::post("verify", [AuthController::class, "verifyEmail"])
            ->name("verify");
    });

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix("/events")
        ->as("events.")
        ->group(function () {
            Route::get("/", [EventController::class, "index"])
                ->name("index");

            Route::post("/", [EventController::class, "create"])
                ->name("create");

            Route::put("/{event}", [EventController::class, "update"])
                ->middleware("can:update,event")
                ->name("update");

            Route::post("/{event}/publish", [EventController::class, "publish"])
                ->name("publish");

            Route::post("/{event}/cancel", [EventController::class, "cancel"])
                ->name("cancel");

            Route::delete("/{event}", [EventController::class, "delete"])
                ->middleware("can:delete,event")
                ->name("delete");
            });
});
