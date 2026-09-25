<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PartController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/parts', [PartController::class, 'index']);