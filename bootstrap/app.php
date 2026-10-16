<?php

use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\PublicMaintenanceMode;
use App\Http\Middleware\ExpireIdleAdminSession;
use App\Console\Commands\CreateSuperAdmin;
use App\Console\Commands\PruneDemoData;
use App\Console\Commands\InspectDemoEngine;
use App\Console\Commands\InspectDemoLifecycle;
use App\Console\Commands\InspectQrLifecycle;
use App\Console\Commands\InspectMediaStorage;
use App\Console\Commands\InspectMediaReferences;
use App\Console\Commands\InspectPlatformIntegrity;
use App\Console\Commands\InspectSiteBuilder;
use App\Console\Commands\InspectSiteRedirects;
use App\Console\Commands\InspectAuditLogHygiene;
use App\Console\Commands\ReleaseReadinessCheck;
use App\Console\Commands\PruneVideoUploads;
use App\Console\Commands\PruneVideoProcessingArtifacts;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withCommands([CreateSuperAdmin::class, PruneDemoData::class, InspectDemoEngine::class, InspectDemoLifecycle::class, InspectQrLifecycle::class, InspectMediaStorage::class, InspectMediaReferences::class, InspectPlatformIntegrity::class, InspectSiteBuilder::class, InspectSiteRedirects::class, InspectAuditLogHygiene::class, ReleaseReadinessCheck::class, PruneVideoUploads::class, PruneVideoProcessingArtifacts::class])
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('orvionart:prune-demo-data')->dailyAt('03:20')->withoutOverlapping();
        $schedule->command('orvionart:prune-video-uploads')->dailyAt('03:35')->withoutOverlapping();
        $schedule->command('orvionart:prune-video-processing-artifacts')->dailyAt('03:45')->withoutOverlapping();
    })
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);
        $middleware->append(PublicMaintenanceMode::class);
        $middleware->alias([
            'superadmin' => EnsureSuperAdmin::class,
            'admin.session' => ExpireIdleAdminSession::class,
        ]);
        $middleware->redirectGuestsTo(fn (Request $request) => route('admin.login'));
        $middleware->redirectUsersTo(fn (Request $request) => route('admin.dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e): bool {
            return $request->is('health') || $request->expectsJson();
        });
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('health')) {
                return response()->json(['status' => 'unavailable'], 503);
            }
            return null;
        });
    })->create();
