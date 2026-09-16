<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\TicketTypeController;
use App\Http\Controllers\VenueController;
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

            Route::scopeBindings()
                ->prefix("/{event}/ticket-types")
                ->as("ticketTypes.")
                ->group(function () {
                    Route::get("/", [TicketTypeController::class, "index"])
                        ->name("index");

                    Route::post("/", [TicketTypeController::class, "create"])
                        ->middleware("can:update,event")
                        ->name("create");

                    Route::put("/{ticketType}", [TicketTypeController::class, "update"])
                        ->middleware("can:update,event")
                        ->name("update");

                    Route::delete("/{ticketType}", [TicketTypeController::class, "delete"])
                        ->middleware("can:update,event")
                        ->name("delete");
                });
        });
    Route::prefix("/categories")
        ->as("categories.")
        ->group(function () {
            Route::get("/", [CategoryController::class, "index"])
                ->name("index");

            Route::post("/", [CategoryController::class, "create"])
                ->name("create");

            Route::put("/{category}", [CategoryController::class, "update"])
                ->name("update");

            Route::delete("/{category}", [CategoryController::class, "delete"])
                ->name("delete");
        });

    Route::prefix("/venues")
        ->as("venues.")
        ->group(function () {
            Route::get("/", [VenueController::class, "index"])
                ->name("index");

            Route::post("/", [VenueController::class, "create"])
                ->name("create");

            Route::put("/{venue}", [VenueController::class, "update"])
                ->name("update");

            Route::delete("/{venue}", [VenueController::class, "delete"])
                ->name("delete");
        });
});
