<?php

use App\Http\Middleware\EnsureFeature;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['fitur' => EnsureFeature::class]);
        // Hanya untuk jalankan-hp-aman.bat (proksi HTTPS lokal): agar alamat aset memakai https. Jangan diaktifkan di server sungguhan.
        if (env('TRUST_PROXY_LOKAL') === 'true' || env('TRUST_PROXY_LOKAL') === true) {
            $middleware->trustProxies(at: '*');
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
