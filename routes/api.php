<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\LicitacaoController;
use Illuminate\Support\Facades\Route;

Route::get('/health', [HealthController::class, 'index']);

Route::get('/licitacoes', [LicitacaoController::class, 'index']);
Route::get('/licitacoes/{id}', [LicitacaoController::class, 'show'])
    ->whereNumber('id');

Route::prefix('analytics')->group(function (): void {
    Route::get('/evolucao', [AnalyticsController::class, 'evolucao']);
    Route::get('/modalidades', [AnalyticsController::class, 'modalidades']);
    Route::get('/orgaos', [AnalyticsController::class, 'orgaos']);
    Route::get('/fornecedores', [AnalyticsController::class, 'fornecedores']);
});
