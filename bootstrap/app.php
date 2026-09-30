<?php

use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // i18n：每個網頁請求都套用使用者目前選的語言（存在 session 裡）。
        $middleware->web(append: [SetLocale::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
