<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PartController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/parts', [PartController::class, 'index']);
Route::get('/parts/create', [PartController::class, 'create']);
Route::post('/parts', [PartController::class, 'store']);
Route::get('/parts/{part}/edit', [PartController::class, 'edit']);
Route::put('/parts/{part}', [PartController::class, 'update']);
Route::delete('/parts/{part}', [PartController::class, 'destroy']);