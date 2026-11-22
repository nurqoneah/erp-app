<?php

use App\Core\Exceptions\BusinessException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Pelanggaran aturan bisnis → 422 dengan pesan untuk user.
        $exceptions->render(fn (BusinessException $e) => response()->json(['message' => $e->getMessage()], 422));
        $exceptions->dontReport(BusinessException::class);
    })->create();
