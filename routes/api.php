<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::middleware('api')->group(function () {
    Route::get('/hello', function () {
        return response()->json(['message' => 'Hello, World!']);
    });
});

