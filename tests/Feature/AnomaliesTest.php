<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\Support\SemeiaBase;
use Tests\TestCase;

class AnomaliesTest extends TestCase
{
    use SemeiaBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->semear();
        $this->semearScores();
    }

    public function test_ranking_com_a_forma_esperada(): void
    {
        $this->getJson('/api/anomalies')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [[
                    'licitacao_id', 'score', 'posicao_ranking',
                    'licitacao' => ['numero_licitacao', 'objeto', 'valor', 'competencia', 'orgao'],
                ]],
                'meta' => ['total', 'aviso', 'algoritmo', 'executado_em'],
            ]);
    }

    public function test_ordenado_pela_posicao(): void
    {
        $resposta = $this->getJson('/api/anomalies')->assertOk();

        /** @var list<array{posicao_ranking: int}> $linhas */
        $linhas = $resposta->json('data');
        $posicoes = array_column($linhas, 'posicao_ranking');

        $this->assertSame([1, 2, 3], $posicoes);
    }

    public function test_score_e_string_decimal(): void
    {
        $this->getJson('/api/anomalies')
            ->assertOk()
            ->assertJsonPath('data.0.score', '0.810000');
    }

    public function test_aviso_permanente_no_meta(): void
    {
        // Restrição de produto: a tela do dashboard apenas exibe este texto,
        // que é fixo e testado aqui.
        $resposta = $this->getJson('/api/anomalies')->assertOk();

        $aviso = $resposta->json('meta.aviso');
        assert(is_string($aviso));
        $this->assertStringContainsString('não caracteriza', $aviso);
        $this->assertStringContainsString('estatística', $aviso);
    }

    public function test_detalhe_traz_contribuicoes_e_valores(): void
    {
        $id = DB::table('score_anomalia')
            ->where('posicao_ranking', 1)->value('licitacao_id');
        assert(is_int($id));

        $this->getJson("/api/anomalies/{$id}")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'score', 'posicao_ranking',
                    'contribuicoes' => [['atributo', 'desvio']],
                    'valores',
                    'licitacao' => ['numero_licitacao', 'objeto'],
                    'metodo',
                ],
                'meta' => ['aviso'],
            ])
            ->assertJsonPath('data.contribuicoes.0.atributo', 'razao_valor_grupo');
    }

    public function test_detalhe_documenta_o_metodo(): void
    {
        $id = DB::table('score_anomalia')
            ->where('posicao_ranking', 1)->value('licitacao_id');
        assert(is_int($id));

        $resposta = $this->getJson("/api/anomalies/{$id}")->assertOk();

        $metodo = $resposta->json('data.metodo');
        assert(is_string($metodo));
        $this->assertStringContainsString('desvio robusto', $metodo);
    }

    public function test_licitacao_sem_score_devolve_404(): void
    {
        $maior = DB::table('licitacao')->max('id');
        assert(is_int($maior));
        $sem = $maior + 999;

        $this->getJson("/api/anomalies/{$sem}")->assertStatus(404);
    }

    public function test_filtra_por_competencia(): void
    {
        $resposta = $this->getJson('/api/anomalies?competencia_de=202401&competencia_ate=202401')
            ->assertOk();

        /** @var list<array{licitacao: array{competencia: string}}> $linhas */
        $linhas = $resposta->json('data');
        foreach ($linhas as $l) {
            $this->assertSame('202401', $l['licitacao']['competencia']);
        }
    }

    public function test_paginacao_com_teto(): void
    {
        $this->getJson('/api/anomalies?per_page=5000')->assertStatus(422);
    }

    public function test_nenhum_termo_de_acusacao(): void
    {
        $corpo = mb_strtolower($this->getJson('/api/anomalies')->content());

        foreach (['suspeita', 'irregularidade', 'fraude', 'ilícito'] as $termo) {
            $this->assertStringNotContainsString($termo, $corpo);
        }
    }
}
