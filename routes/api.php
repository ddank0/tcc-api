<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AnomaliesController;
use App\Http\Controllers\ForecastController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\LicitacaoController;
use Illuminate\Support\Facades\Route;

Route::get('/health', [HealthController::class, 'index']);

Route::get('/licitacoes', [LicitacaoController::class, 'index']);
Route::get('/licitacoes/{id}', [LicitacaoController::class, 'show'])
    ->whereNumber('id');

Route::get('/forecast', [ForecastController::class, 'index']);

Route::get('/anomalies', [AnomaliesController::class, 'index']);
Route::get('/anomalies/{id}', [AnomaliesController::class, 'show'])->whereNumber('id');

Route::prefix('analytics')->group(function (): void {
    Route::get('/evolucao', [AnalyticsController::class, 'evolucao']);
    Route::get('/modalidades', [AnalyticsController::class, 'modalidades']);
    Route::get('/orgaos', [AnalyticsController::class, 'orgaos']);
    Route::get('/fornecedores', [AnalyticsController::class, 'fornecedores']);
});

// A especificação servida é o arquivo versionado, e não uma geração em
// runtime: é ele que gera o cliente do Angular, então servir outra coisa
// esconderia divergência.
Route::get('/openapi.json', function () {
    $caminho = base_path('openapi.json');

    abort_unless(file_exists($caminho), 404);

    return response()->file($caminho, ['Content-Type' => 'application/json']);
});

Route::get('/docs', fn () => view('docs'));
