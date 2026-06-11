<?php

use App\Http\Controllers\Api\Agent\ChatController;
use App\Http\Controllers\Api\Agent\CommandController;
use App\Http\Controllers\Api\Agent\ContextController;
use App\Http\Controllers\Api\Agent\ManifestController;
use App\Http\Controllers\Api\Agent\TaskController;
use Illuminate\Support\Facades\Route;

Route::prefix('agent')
  ->middleware(['auth:sanctum', 'agent.access', 'agent.company'])
  ->group(function () {
    Route::get('manifest', [ManifestController::class, 'show'])->name('api.agent.manifest');

    Route::post('commands/{command}', [CommandController::class, 'execute'])
      ->where('command', '[a-z0-9_.]+')
      ->name('api.agent.commands.execute');

    Route::get('context/rental/{identifier}', [ContextController::class, 'rental'])
      ->name('api.agent.context.rental');

    Route::get('context/customer/{customer}', [ContextController::class, 'customer'])
      ->name('api.agent.context.customer');

    Route::get('context/system', [ContextController::class, 'system'])
      ->name('api.agent.context.system');

    Route::get('context/maintenance/{identifier}', [ContextController::class, 'maintenance'])
      ->name('api.agent.context.maintenance');

    Route::get('context/asset/{identifier}', [ContextController::class, 'asset'])
      ->name('api.agent.context.asset');

    Route::get('context/quote/{identifier}', [ContextController::class, 'quote'])
      ->name('api.agent.context.quote');

    Route::get('context/receivable/{identifier}', [ContextController::class, 'receivable'])
      ->name('api.agent.context.receivable');

    Route::post('chat', ChatController::class)->name('api.agent.chat');

    Route::get('tasks', [TaskController::class, 'index'])->name('api.agent.tasks.index');
    Route::post('tasks', [TaskController::class, 'store'])->name('api.agent.tasks.store');
    Route::get('tasks/{task}', [TaskController::class, 'show'])->name('api.agent.tasks.show');
    Route::post('tasks/{task}/cancel', [TaskController::class, 'cancel'])->name('api.agent.tasks.cancel');
  });
