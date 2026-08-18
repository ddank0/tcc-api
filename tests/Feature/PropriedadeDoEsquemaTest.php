<?php

namespace Tests\Feature;

use App\Models\Fornecedor;
use App\Models\IngestaoLog;
use App\Models\ItemLicitacao;
use App\Models\Licitacao;
use App\Models\Modalidade;
use App\Models\Orgao;
use App\Models\ParticipanteLicitacao;
use App\Models\RankingFornecedor;
use App\Models\RankingFornecedorTotal;
use App\Models\SerieMensal;
use App\Models\UnidadeGestora;
use Tests\TestCase;

class PropriedadeDoEsquemaTest extends TestCase
{
    public function test_a_api_nao_tem_migrations_proprias(): void
    {
        // O Alembic é o dono do esquema. Migration aqui significa duas
        // ferramentas versionando o mesmo banco.
        $arquivos = glob(database_path('migrations/*.php')) ?: [];

        $this->assertSame([], $arquivos, 'migrations do Laravel não devem existir');
    }

    public function test_modelos_apontam_para_as_tabelas_do_alembic(): void
    {
        $esperado = [
            Licitacao::class => 'licitacao',
            ItemLicitacao::class => 'item_licitacao',
            ParticipanteLicitacao::class => 'participante_licitacao',
            Orgao::class => 'orgao',
            UnidadeGestora::class => 'unidade_gestora',
            Modalidade::class => 'modalidade',
            Fornecedor::class => 'fornecedor',
            SerieMensal::class => 'serie_mensal',
            RankingFornecedor::class => 'ranking_fornecedor',
            RankingFornecedorTotal::class => 'ranking_fornecedor_total',
            IngestaoLog::class => 'ingestao_log',
        ];

        foreach ($esperado as $classe => $tabela) {
            $this->assertSame($tabela, (new $classe)->getTable());
        }
    }

    public function test_nenhum_modelo_espera_timestamps(): void
    {
        // As tabelas do Alembic não têm created_at nem updated_at. Com
        // $timestamps ligado, qualquer escrita quebraria - e a leitura de
        // relações também, em alguns caminhos.
        $modelos = [
            Licitacao::class,
            ItemLicitacao::class,
            ParticipanteLicitacao::class,
            Orgao::class,
            UnidadeGestora::class,
            Modalidade::class,
            Fornecedor::class,
            SerieMensal::class,
            RankingFornecedor::class,
            RankingFornecedorTotal::class,
            IngestaoLog::class,
        ];

        foreach ($modelos as $classe) {
            $this->assertFalse((new $classe)->usesTimestamps(), $classe);
        }
    }

    public function test_chaves_nao_inteiras_estao_declaradas(): void
    {
        // fornecedor.cnpj e orgao.codigo_orgao são texto. Sem declarar,
        // o Eloquent trata como int e o cast destrói zeros à esquerda -
        // e há CNPJ com e sem zero à esquerda no dado real.
        foreach ([Fornecedor::class, Orgao::class] as $classe) {
            $modelo = new $classe;
            $this->assertSame('string', $modelo->getKeyType(), $classe);
            $this->assertFalse($modelo->getIncrementing(), $classe);
        }
    }
}
