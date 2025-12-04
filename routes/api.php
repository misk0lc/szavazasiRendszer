<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\PollApiController;
use App\Http\Controllers\Api\VoteApiController;

// Auth API (Bearer tokens via Sanctum)
Route::post('/register', [AuthApiController::class, 'register']);
Route::post('/login', [AuthApiController::class, 'login']);
Route::post('/logout', [AuthApiController::class, 'logout'])->middleware('auth:sanctum');

// Public poll endpoints
Route::get('/polls', [PollApiController::class, 'index']);
Route::get('/polls/{poll}', [PollApiController::class, 'show']);
Route::get('/polls/{poll}/results', [PollApiController::class, 'results']);

// Protected endpoints (Bearer token required)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/polls', [PollApiController::class, 'store']);
    Route::post('/polls/{poll}/vote', [VoteApiController::class, 'store']);
});

