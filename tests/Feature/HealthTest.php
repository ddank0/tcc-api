<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HealthTest extends TestCase
{
    private function semearLog(): void
    {
        DB::table('ingestao_log')->insert([
            [
                'competencia' => '202403', 'arquivo' => '202403_silver',
                'linhas_lidas' => 213073, 'linhas_inseridas' => 213073,
                'linhas_atualizadas' => 0, 'linhas_rejeitadas' => 0,
                'iniciado_em' => '2026-08-18 10:00:00', 'finalizado_em' => '2026-08-18 10:00:12',
                'status' => 'sucesso', 'mensagem_erro' => null,
            ],
            [
                'competencia' => '202404', 'arquivo' => '202404_silver',
                'linhas_lidas' => 57187, 'linhas_inseridas' => 57187,
                'linhas_atualizadas' => 0, 'linhas_rejeitadas' => 0,
                'iniciado_em' => '2026-08-18 10:00:12', 'finalizado_em' => '2026-08-18 10:00:20',
                'status' => 'sucesso', 'mensagem_erro' => null,
            ],
        ]);
    }

    public function test_health_responde_ok(): void
    {
        $this->getJson('/api/health')->assertOk()->assertJson(['status' => 'ok']);
    }

    public function test_health_confirma_conexao_com_o_banco(): void
    {
        $this->getJson('/api/health')->assertOk()->assertJson(['database' => 'ok']);
    }

    public function test_health_expoe_a_ultima_ingestao(): void
    {
        // RF10: o critério de aceitação é literal - "GET /health expõe a
        // última ingestão". A API não executa job, mas precisa dizer quando o
        // último rodou.
        $this->semearLog();

        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonStructure([
                'ultima_ingestao' => [
                    'competencia', 'arquivo', 'status', 'finalizado_em',
                    'linhas_lidas', 'linhas_inseridas', 'linhas_rejeitadas',
                ],
            ])
            ->assertJsonPath('ultima_ingestao.competencia', '202404');
    }

    public function test_traz_a_mais_recente_e_nao_a_ultima_inserida(): void
    {
        $this->semearLog();

        // Uma competência anterior, registrada depois: ordenar por id daria a
        // resposta errada.
        DB::table('ingestao_log')->insert([
            'competencia' => '201301', 'arquivo' => '201301_silver',
            'linhas_lidas' => 100, 'linhas_inseridas' => 100,
            'linhas_atualizadas' => 0, 'linhas_rejeitadas' => 0,
            'iniciado_em' => '2026-08-18 09:00:00', 'finalizado_em' => '2026-08-18 09:00:05',
            'status' => 'sucesso', 'mensagem_erro' => null,
        ]);

        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonPath('ultima_ingestao.competencia', '202404');
    }

    public function test_sem_ingestao_registrada_nao_quebra(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonPath('ultima_ingestao', null);
    }

    public function test_expoe_falha_de_ingestao(): void
    {
        // O log existe para o caminho de erro. Esconder o status transformaria
        // /health em otimismo.
        DB::table('ingestao_log')->insert([
            'competencia' => '202405', 'arquivo' => '202405_silver',
            'linhas_lidas' => 0, 'linhas_inseridas' => 0,
            'linhas_atualizadas' => 0, 'linhas_rejeitadas' => 0,
            'iniciado_em' => '2026-08-18 11:00:00', 'finalizado_em' => '2026-08-18 11:00:01',
            'status' => 'erro', 'mensagem_erro' => 'silver ausente ou vazio',
        ]);

        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonPath('ultima_ingestao.status', 'erro')
            ->assertJsonPath('ultima_ingestao.mensagem_erro', 'silver ausente ou vazio');
    }
}
