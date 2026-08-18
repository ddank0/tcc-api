<?php

namespace Tests\Feature;

use Tests\Support\SemeiaBase;
use Tests\TestCase;

class AnalyticsEvolucaoTest extends TestCase
{
    use SemeiaBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->semear();
        $this->semearSerie();
    }

    public function test_evolucao_tem_a_forma_esperada(): void
    {
        $this->getJson('/api/analytics/evolucao')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['competencia', 'quantidade_licitacoes', 'valor_total', 'parcial']],
                'meta' => ['competencias'],
            ]);
    }

    public function test_evolucao_vem_ordenada_por_competencia(): void
    {
        $resposta = $this->getJson('/api/analytics/evolucao')->assertOk();

        /** @var list<array{competencia: string}> $linhas */
        $linhas = $resposta->json('data');
        $competencias = array_column($linhas, 'competencia');
        $ordenadas = $competencias;
        sort($ordenadas);

        $this->assertSame($ordenadas, $competencias, 'série temporal precisa vir em ordem');
    }

    public function test_soma_bate_com_a_serie_semeada(): void
    {
        $resposta = $this->getJson('/api/analytics/evolucao')->assertOk();

        /** @var list<array{quantidade_licitacoes: int}> $linhas */
        $linhas = $resposta->json('data');

        // 1+1+1+1+40+2 do seed
        $this->assertSame(46, array_sum(array_column($linhas, 'quantidade_licitacoes')));
    }

    public function test_competencias_atipicas_vem_marcadas(): void
    {
        // 202404 encerra a série e tem volume anômalo; 201812 tem licitações
        // mas nenhum participante. Sem marcação, um gráfico lê as duas como
        // queda real de atividade.
        $resposta = $this->getJson('/api/analytics/evolucao')->assertOk();

        /** @var list<array{competencia: string, parcial: bool}> $linhas */
        $linhas = $resposta->json('data');
        /** @var array<int|string, bool> $porCompetencia */
        $porCompetencia = array_column($linhas, 'parcial', 'competencia');

        $this->assertTrue($porCompetencia['202404'] ?? false, '202404 é truncada na fonte');
        $this->assertTrue($porCompetencia['201812'] ?? false, '201812 não tem participantes');
        $this->assertFalse($porCompetencia['202401'] ?? true, 'competência normal não é parcial');
    }

    public function test_marcacao_traz_o_motivo(): void
    {
        $resposta = $this->getJson('/api/analytics/evolucao')->assertOk();

        /** @var array<string, mixed> $avisos */
        $avisos = $resposta->json('meta.competencias_parciais');

        $this->assertArrayHasKey('202404', $avisos);
        $this->assertArrayHasKey('201812', $avisos);
    }

    public function test_filtra_por_intervalo(): void
    {
        $resposta = $this->getJson('/api/analytics/evolucao?competencia_de=202401&competencia_ate=202412')
            ->assertOk();

        $this->assertSame(3, $resposta->json('meta.competencias'));
    }

    public function test_valor_nulo_nao_vira_zero_silencioso(): void
    {
        // 202312 tem valor_total nulo no seed: somar nulo como zero mascara
        // ausência de dado.
        $resposta = $this->getJson('/api/analytics/evolucao?competencia_de=202312&competencia_ate=202312')
            ->assertOk();

        $this->assertNull($resposta->json('data.0.valor_total'));
    }

    public function test_modalidades_tem_a_forma_esperada(): void
    {
        $this->getJson('/api/analytics/modalidades')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['codigo_modalidade', 'nome', 'quantidade_licitacoes', 'valor_total']],
            ]);
    }

    public function test_modalidades_traz_o_nome_e_nao_so_o_codigo(): void
    {
        $resposta = $this->getJson('/api/analytics/modalidades')->assertOk();

        /** @var list<array{nome: string|null}> $linhas */
        $linhas = $resposta->json('data');
        $nomes = array_column($linhas, 'nome');

        $this->assertContains('Pregão', $nomes);
    }

    public function test_modalidades_ordenadas_por_quantidade(): void
    {
        $resposta = $this->getJson('/api/analytics/modalidades')->assertOk();

        /** @var list<array{quantidade_licitacoes: int}> $linhas */
        $linhas = $resposta->json('data');
        $quantidades = array_column($linhas, 'quantidade_licitacoes');

        $this->assertSame($quantidades, array_reverse(array_reverse($quantidades)));
        $this->assertGreaterThanOrEqual($quantidades[1] ?? 0, $quantidades[0]);
    }

    public function test_competencia_malformada_e_rejeitada(): void
    {
        $this->getJson('/api/analytics/evolucao?competencia_de=abc')->assertStatus(422);
    }
}
