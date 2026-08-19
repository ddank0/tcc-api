<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    /**
     * Tabelas semeadas pelos testes, na ordem de dependência inversa.
     *
     * O esquema é do Alembic e não há migration aqui, então os testes não
     * criam nem destroem tabelas - apenas limpam o que semearam. Truncar em
     * ordem errada esbarraria nas chaves estrangeiras.
     *
     * @var list<string>
     */
    protected array $tabelas = [
        'previsao',
        'execucao_modelo',
        'score_anomalia',
        'ranking_fornecedor_total',
        'ranking_fornecedor',
        'serie_mensal',
        'ingestao_log',
        'participante_licitacao',
        'item_licitacao',
        'licitacao',
        'fornecedor',
        'unidade_gestora',
        'orgao',
        'modalidade',
    ];

    /**
     * Sufixo obrigatório no nome do banco de teste.
     *
     * A suíte trunca tabelas. Sem esta verificação, um phpunit.xml mal
     * configurado apaga a base real - o `<env>` do PHPUnit não sobrescreve
     * variável já presente no ambiente sem `force="true"`, e foi assim que
     * um TRUNCATE atingiu o banco de 91 milhões de linhas.
     */
    private const SUFIXO_DE_TESTE = '_test';

    protected function setUp(): void
    {
        parent::setUp();

        // Testar contra PostgreSQL, não SQLite: o esquema é do Alembic, e
        // ILIKE, numeric(38,4) e a paginação com OFFSET se comportam de forma
        // diferente nos dois. Um teste que passa em SQLite não prova nada
        // sobre o banco que a API realmente usa.
        $this->assertSame('pgsql', DB::connection()->getDriverName());

        $this->exigirBancoDeTeste();
        $this->limpar();
    }

    /**
     * Aborta antes de tocar em qualquer dado se o banco não for de teste.
     *
     * Falhar aqui é barato; truncar a base real custa uma recarga inteira.
     */
    private function exigirBancoDeTeste(): void
    {
        $banco = (string) DB::connection()->getDatabaseName();

        if (! str_ends_with($banco, self::SUFIXO_DE_TESTE)) {
            $this->fail(
                "Banco \"{$banco}\" não termina em \"".self::SUFIXO_DE_TESTE.'". '
                .'A suíte trunca tabelas e se recusa a rodar fora de um banco de teste. '
                .'Confira DB_DATABASE no phpunit.xml, com force="true".'
            );
        }
    }

    protected function tearDown(): void
    {
        $this->limpar();

        parent::tearDown();
    }

    protected function limpar(): void
    {
        DB::statement('TRUNCATE '.implode(', ', $this->tabelas).' RESTART IDENTITY CASCADE');
    }
}
