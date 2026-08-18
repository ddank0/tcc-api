<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\Support\SemeiaBase;
use Tests\TestCase;

class LicitacaoIndexTest extends TestCase
{
    use SemeiaBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->semear();
    }

    public function test_lista_com_a_forma_esperada(): void
    {
        $this->getJson('/api/licitacoes')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [[
                    'id', 'numero_licitacao', 'numero_processo', 'objeto', 'situacao',
                    'valor', 'competencia', 'data_abertura', 'data_resultado',
                    'modalidade' => ['codigo', 'nome'],
                    'unidade_gestora' => ['codigo_ug', 'nome', 'uf', 'municipio',
                        'orgao' => ['codigo_orgao', 'nome']],
                ]],
                'meta' => ['total', 'per_page', 'current_page', 'last_page'],
            ])
            ->assertJsonPath('meta.total', 4);
    }

    public function test_filtra_por_uf(): void
    {
        $resposta = $this->getJson('/api/licitacoes?uf=SP')->assertOk();

        $this->assertSame(3, $resposta->json('meta.total'));

        /** @var list<array{unidade_gestora: array{uf: string}}> $linhas */
        $linhas = $resposta->json('data');
        foreach ($linhas as $linha) {
            $this->assertSame('SP', $linha['unidade_gestora']['uf']);
        }
    }

    public function test_filtra_por_orgao(): void
    {
        $this->getJson('/api/licitacoes?codigo_orgao=22000')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_filtra_por_modalidade(): void
    {
        $this->getJson('/api/licitacoes?codigo_modalidade=8')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_filtra_por_situacao(): void
    {
        $this->getJson('/api/licitacoes?situacao=Encerrado')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    public function test_filtra_por_intervalo_de_competencia(): void
    {
        // O recorte de período é por competencia, não por data_abertura:
        // 72,6% das datas de abertura são nulas na série real, e filtrar por
        // elas descartaria três de cada quatro licitações em silêncio.
        $this->getJson('/api/licitacoes?competencia_de=202401&competencia_ate=202412')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    public function test_filtra_por_faixa_de_valor(): void
    {
        $this->getJson('/api/licitacoes?valor_min=100000&valor_max=500000')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_busca_no_objeto_ignora_caixa(): void
    {
        $this->getJson('/api/licitacoes?q=MICROSCÓPIOS')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_busca_e_sensivel_a_acento(): void
    {
        // Limitação declarada: ignorar acento exigiria a extensão `unaccent`,
        // e o dono do esquema é o tcc-jobs. Fixado por teste para que a
        // limitação seja visível em vez de virar surpresa para quem usa.
        $this->getJson('/api/licitacoes?q=microscopios')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_filtros_combinam(): void
    {
        // Duas licitações em SP, modalidade 5 e encerradas: as de UG 150002 e
        // 130094. Um filtro que devolvesse uma só estaria descartando dado.
        $this->getJson('/api/licitacoes?uf=SP&codigo_modalidade=5&situacao=Encerrado')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    public function test_licitacao_sem_valor_nao_aparece_em_filtro_de_faixa(): void
    {
        // A licitação deserta tem valor nulo. Um NULL não pode ser tratado
        // como zero, senão ela entra em toda faixa que começa em 0.
        $resposta = $this->getJson('/api/licitacoes?valor_min=0')->assertOk();

        $this->assertSame(3, $resposta->json('meta.total'));
    }

    public function test_ordena_por_valor_desc(): void
    {
        $resposta = $this->getJson('/api/licitacoes?ordenar=valor&direcao=desc')->assertOk();

        /** @var list<array{valor: string|null}> $linhas */
        $linhas = $resposta->json('data');
        $valores = array_column($linhas, 'valor');

        $this->assertSame('1200000.0000', $valores[0]);
    }

    public function test_ordenacao_por_coluna_nao_permitida_e_rejeitada(): void
    {
        // Sem lista branca, `ordenar` viraria injeção na cláusula ORDER BY.
        $this->getJson('/api/licitacoes?ordenar=objeto;drop')->assertStatus(422);
    }

    public function test_pagina_vazia_devolve_lista_e_nao_erro(): void
    {
        $this->getJson('/api/licitacoes?page=99')
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_teto_de_per_page_e_respeitado(): void
    {
        // Sem teto, o cliente pede 1,7 milhão de linhas num request.
        $this->getJson('/api/licitacoes?per_page=5000')->assertStatus(422);
    }

    public function test_competencia_malformada_e_rejeitada(): void
    {
        $this->getJson('/api/licitacoes?competencia_de=2024')->assertStatus(422);
    }

    public function test_uf_invalida_e_rejeitada(): void
    {
        $this->getJson('/api/licitacoes?uf=XX')->assertStatus(422);
    }

    public function test_nao_faz_uma_consulta_por_linha(): void
    {
        // Sem eager loading, cada licitação dispara consultas para modalidade,
        // UG e órgão - o problema N+1, que numa página de 25 vira 76 consultas.
        DB::enableQueryLog();

        $this->getJson('/api/licitacoes')->assertOk();

        $total = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(6, $total, "consultas emitidas: {$total}");
    }
}
