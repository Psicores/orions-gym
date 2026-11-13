<?php

use App\Http\Middleware\SecurityHeaders;
use Filament\Facades\Filament;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Cabeceras de seguridad en todas las respuestas (landing, portal y panel).
        $middleware->append(SecurityHeaders::class);
        // Quien entra sin sesión a una pantalla protegida va al ingreso único (/ingresar).
        $middleware->redirectGuestsTo(fn (): string => route('login'));
        // Quien ya tiene sesión y abre el ingreso va a su lugar: el socio a su portal, el personal al panel.
        $middleware->redirectUsersTo(fn (): string => Auth::guard('client')->check()
            ? route('member.dashboard')
            : Filament::getPanel('admin')->getUrl());
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
