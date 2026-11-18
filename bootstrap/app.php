<?php

use App\Http\Middleware\AdminCan;
use App\Http\Middleware\AdminIsActive;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequireVerification;
use App\Http\Middleware\SignOutBannedMembers;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')->group(base_path('routes/admin.php'));
            Route::middleware('web')->group(base_path('routes/affiliate.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'member.verified' => RequireVerification::class,
            'admin.active' => AdminIsActive::class,
            'admin.can' => AdminCan::class,
        ]);

        // Admin pages send signed-out visitors to the admin sign-in, not the
        // member one; and a signed-in admin visiting the admin sign-in goes to
        // the admin dashboard.
        $middleware->redirectGuestsTo(fn (Request $request) => match (true) {
            $request->is('admin', 'admin/*') => route('admin.login'),
            $request->is('affiliate', 'affiliate/*') => route('affiliate.login'),
            default => route('login'),
        });
        $middleware->redirectUsersTo(fn (Request $request) => match (true) {
            $request->is('admin', 'admin/*') => route('admin.dashboard'),
            $request->is('affiliate', 'affiliate/*') => route('affiliate.dashboard'),
            default => route('dashboard'),
        });

        // Paystack cannot send our CSRF token; its messages are signed instead.
        $middleware->validateCsrfTokens(except: ['webhooks/paystack']);

        $middleware->web(append: [
            SignOutBannedMembers::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
