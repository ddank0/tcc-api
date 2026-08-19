<?php

namespace Tests\Feature;

use Tests\Support\SemeiaBase;
use Tests\TestCase;

class ForecastTest extends TestCase
{
    use SemeiaBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->semear();
        $this->semearPrevisao();
    }

    public function test_lista_previsoes_com_a_forma_esperada(): void
    {
        $this->getJson('/api/forecast?serie=orgao:26000')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [[
                    'competencia_alvo', 'alvo', 'valor_previsto', 'ic_inferior', 'ic_superior',
                ]],
                'meta' => ['serie', 'algoritmo', 'janela_treino_fim', 'executado_em'],
            ]);
    }

    public function test_filtra_por_serie_e_alvo(): void
    {
        $resposta = $this->getJson('/api/forecast?serie=orgao:26000&alvo=quantidade')->assertOk();

        /** @var list<array{competencia_alvo: string}> $linhas */
        $linhas = $resposta->json('data');

        $this->assertCount(2, $linhas);
        $this->assertSame('202405', $linhas[0]['competencia_alvo']);
    }

    public function test_previsao_vem_ordenada_pela_competencia(): void
    {
        $resposta = $this->getJson('/api/forecast?serie=orgao:26000&alvo=quantidade')->assertOk();

        /** @var list<array{competencia_alvo: string}> $linhas */
        $linhas = $resposta->json('data');
        $competencias = array_column($linhas, 'competencia_alvo');
        $ordenadas = $competencias;
        sort($ordenadas);

        $this->assertSame($ordenadas, $competencias);
    }

    public function test_serie_sem_previsao_devolve_lista_vazia(): void
    {
        $this->getJson('/api/forecast?serie=orgao:99999')
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_serie_e_obrigatoria(): void
    {
        $this->getJson('/api/forecast')->assertStatus(422);
    }

    public function test_valores_preservam_precisao_decimal(): void
    {
        $resposta = $this->getJson('/api/forecast?serie=orgao:26000&alvo=quantidade')->assertOk();

        $this->assertSame('2500.0000', $resposta->json('data.0.valor_previsto'));
    }

    public function test_meta_expoe_a_procedencia(): void
    {
        // Sem procedência - algoritmo, janela de treino, quando rodou - o
        // número vira oráculo: não dá para contestar nem reproduzir.
        $this->getJson('/api/forecast?serie=orgao:26000')
            ->assertOk()
            ->assertJsonPath('meta.algoritmo', 'AutoARIMA')
            ->assertJsonPath('meta.janela_treino_fim', '202404');
    }
}
