<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('maintenance:process-preventive-due')->dailyAt('06:00');
        $schedule->command('rentals:process-billing-renewals')->dailyAt('06:30');
        $schedule->command('quotes:expire')->dailyAt('07:00');
        $schedule->command('notifications:operational-alerts')->dailyAt('07:45');
        $schedule->command('crm:follow-up-reminders')->dailyAt('08:00');
        $schedule->command('crm:process-outbound')->everyFiveMinutes();
        $schedule->command('queue:prune-failed --hours=168')->weekly();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'agent.access' => \App\Http\Middleware\EnsureAgentApiAccess::class,
            'agent.company' => \App\Http\Middleware\SetAgentOperatingCompany::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\SetActiveOperatingCompany::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'webhooks/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->renderable(function (
            \Livewire\Mechanisms\HandleComponents\CorruptComponentPayloadException $e,
            Request $request
        ) {
            if ($request->expectsJson() || $request->hasHeader('X-Livewire')) {
                return response()->json([
                    'message' => 'A página expirou. Recarregue (F5) e tente novamente.',
                    'redirect' => url()->previous() ?: route('dashboard'),
                ], 419);
            }

            return redirect()
                ->to(url()->previous() ?: route('dashboard'))
                ->with('error', 'A sessão da página expirou (comum após atualizar o sistema). Recarregue com F5 e tente de novo.');
        });
    })->create();
