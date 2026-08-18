<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\Support\SemeiaBase;
use Tests\TestCase;

class LicitacaoShowTest extends TestCase
{
    use SemeiaBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->semear();
    }

    private function idDe(string $numero): int
    {
        $id = DB::table('licitacao')->where('numero_licitacao', $numero)->value('id');

        $this->assertIsNumeric($id, "licitação {$numero} não foi semeada");

        return (int) $id;
    }

    public function test_traz_a_licitacao_com_itens_e_participantes(): void
    {
        $this->getJson('/api/licitacoes/'.$this->idDe('000012024'))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id', 'numero_licitacao', 'objeto', 'situacao', 'valor', 'competencia',
                    'modalidade' => ['codigo', 'nome'],
                    'unidade_gestora' => ['codigo_ug', 'nome', 'uf', 'orgao' => ['codigo_orgao', 'nome']],
                    'itens' => [['codigo_item_compra', 'descricao', 'quantidade', 'valor_item', 'cnpj_vencedor']],
                    'participantes' => [['codigo_item_compra', 'cnpj_participante', 'flag_vencedor']],
                    'totais' => ['itens', 'participantes'],
                ],
            ])
            ->assertJsonPath('data.totais.itens', 2)
            ->assertJsonPath('data.totais.participantes', 2);
    }

    public function test_id_inexistente_devolve_404(): void
    {
        $this->getJson('/api/licitacoes/99999999')->assertStatus(404);
    }

    public function test_id_nao_numerico_devolve_404(): void
    {
        $this->getJson('/api/licitacoes/abc')->assertStatus(404);
    }

    public function test_licitacao_sem_itens_devolve_listas_vazias(): void
    {
        // 30.983 licitações reais (1,78%) não têm item, e 34.644 não têm
        // participante. Não é caso de erro.
        $this->getJson('/api/licitacoes/'.$this->idDe('000042023'))
            ->assertOk()
            ->assertJsonPath('data.itens', [])
            ->assertJsonPath('data.participantes', [])
            ->assertJsonPath('data.totais.itens', 0);
    }

    public function test_flag_vencedor_vem_como_booleano(): void
    {
        // A fonte traz SIM/NÃO. Para atributos de competitividade, esta coluna
        // é a fonte de verdade - ela e item.cnpj_vencedor discordam em alguns
        // casos por competência.
        $resposta = $this->getJson('/api/licitacoes/'.$this->idDe('000012024'))->assertOk();

        /** @var list<array{flag_vencedor: bool|null}> $participantes */
        $participantes = $resposta->json('data.participantes');
        foreach ($participantes as $p) {
            $this->assertIsBool($p['flag_vencedor']);
        }
    }

    public function test_valor_preserva_precisao_decimal(): void
    {
        $resposta = $this->getJson('/api/licitacoes/'.$this->idDe('000012024'))->assertOk();

        // String, não float: valores chegam a trilhões no dado real e float
        // perderia precisão.
        $this->assertSame('250000.0000', $resposta->json('data.valor'));
    }

    public function test_cnpj_preserva_zero_a_esquerda(): void
    {
        $resposta = $this->getJson('/api/licitacoes/'.$this->idDe('000012024'))->assertOk();

        /** @var list<array{cnpj_vencedor: string|null}> $itens */
        $itens = $resposta->json('data.itens');
        $cnpjs = array_column($itens, 'cnpj_vencedor');

        $this->assertContains('08488971451', $cnpjs);
    }

    public function test_nao_faz_uma_consulta_por_filho(): void
    {
        DB::enableQueryLog();

        $this->getJson('/api/licitacoes/'.$this->idDe('000012024'))->assertOk();

        $total = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(7, $total, "consultas emitidas: {$total}");
    }
}
