<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // O Alembic, no tcc-jobs, é o único dono do esquema. Dois sistemas de
        // migration versionando o mesmo banco, cada um com sua tabela de
        // controle, produzem conflito garantido - quem cria as tabelas define a
        // estrutura, e esta camada é consumidora.
        DB::prohibitDestructiveCommands($this->app->isProduction());

        // Esta API é somente leitura. Sem isto, um `save()` esquecido em
        // desenvolvimento escreveria numa tabela que o pipeline reescreve.
        Model::preventLazyLoading(! $this->app->isProduction());
    }
}
