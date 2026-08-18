<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\Support\SemeiaBase;
use Tests\TestCase;

class AnalyticsRankingTest extends TestCase
{
    use SemeiaBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->semear();
        $this->semearSerie();
        $this->semearRanking();
    }

    public function test_orgaos_tem_a_forma_esperada(): void
    {
        $this->getJson('/api/analytics/orgaos')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['codigo_orgao', 'nome', 'quantidade_licitacoes', 'valor_total']],
            ]);
    }

    public function test_orgaos_traz_o_nome_e_ordena_por_valor(): void
    {
        $resposta = $this->getJson('/api/analytics/orgaos')->assertOk();

        /** @var list<array{nome: string|null, valor_total: string|null}> $linhas */
        $linhas = $resposta->json('data');

        $this->assertNotNull($linhas[0]['nome']);
        $this->assertSame('1200000.0000', $linhas[0]['valor_total']);
    }

    public function test_fornecedores_sem_filtro_le_a_tabela_global(): void
    {
        // Somar as 1,65 milhão de linhas da tabela por competência custa
        // 1.530 ms; a global responde em 33 ms. Sem filtro de período, tem
        // que ser a global.
        DB::enableQueryLog();

        $this->getJson('/api/analytics/fornecedores')->assertOk();

        $consultas = DB::getQueryLog();
        DB::disableQueryLog();

        $sql = implode(' ', array_map(fn (array $c): string => (string) $c['query'], $consultas));
        $this->assertStringContainsString('ranking_fornecedor_total', $sql);
    }

    public function test_fornecedores_com_periodo_le_a_tabela_por_competencia(): void
    {
        DB::enableQueryLog();

        $this->getJson('/api/analytics/fornecedores?competencia_de=202401&competencia_ate=202401')
            ->assertOk();

        $consultas = DB::getQueryLog();
        DB::disableQueryLog();

        $sql = implode(' ', array_map(fn (array $c): string => (string) $c['query'], $consultas));
        $this->assertStringContainsString('from "ranking_fornecedor"', $sql);
    }

    public function test_fornecedores_filtrado_reflete_o_periodo(): void
    {
        $resposta = $this->getJson('/api/analytics/fornecedores?competencia_de=202306&competencia_ate=202306')
            ->assertOk();

        /** @var list<array{cnpj: string, valor_total: string|null}> $linhas */
        $linhas = $resposta->json('data');

        $this->assertCount(1, $linhas);
        $this->assertSame('64799539000135', $linhas[0]['cnpj']);
        $this->assertSame('1200000.0000', $linhas[0]['valor_total']);
    }

    public function test_fornecedores_traz_o_nome_do_fornecedor(): void
    {
        $resposta = $this->getJson('/api/analytics/fornecedores')->assertOk();

        /** @var list<array{nome: string|null}> $linhas */
        $linhas = $resposta->json('data');

        $this->assertNotNull($linhas[0]['nome']);
    }

    public function test_fornecedores_ordena_por_valor_desc(): void
    {
        $resposta = $this->getJson('/api/analytics/fornecedores')->assertOk();

        /** @var list<array{valor_total: string|null}> $linhas */
        $linhas = $resposta->json('data');

        $this->assertSame('1400000.0000', $linhas[0]['valor_total']);
    }

    public function test_respeita_o_limite(): void
    {
        $resposta = $this->getJson('/api/analytics/fornecedores?limit=1')->assertOk();

        /** @var list<array<string, mixed>> $linhas */
        $linhas = $resposta->json('data');
        $this->assertCount(1, $linhas);
    }

    public function test_limite_acima_do_teto_e_rejeitado(): void
    {
        $this->getJson('/api/analytics/fornecedores?limit=5000')->assertStatus(422);
    }

    public function test_nenhum_endpoint_toca_as_tabelas_de_volume(): void
    {
        // A regra do projeto: a API não executa cálculo caro. Ler
        // item_licitacao ou participante_licitacao aqui significaria agregar
        // 14,2 ou 74,8 milhões de linhas em tempo de request.
        DB::enableQueryLog();

        $this->getJson('/api/analytics/fornecedores')->assertOk();
        $this->getJson('/api/analytics/orgaos')->assertOk();
        $this->getJson('/api/analytics/evolucao')->assertOk();
        $this->getJson('/api/analytics/modalidades')->assertOk();

        $consultas = DB::getQueryLog();
        DB::disableQueryLog();

        $sql = implode(' ', array_map(fn (array $c): string => (string) $c['query'], $consultas));

        $this->assertStringNotContainsString('item_licitacao', $sql);
        $this->assertStringNotContainsString('participante_licitacao', $sql);
    }

    public function test_cnpj_preserva_zero_a_esquerda(): void
    {
        $resposta = $this->getJson('/api/analytics/fornecedores')->assertOk();

        /** @var list<array{cnpj: string}> $linhas */
        $linhas = $resposta->json('data');

        $this->assertContains('08488971451', array_column($linhas, 'cnpj'));
    }
}
