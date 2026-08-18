<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;

/**
 * Semeia o mínimo para exercitar consultas, preservando as peculiaridades do
 * dado real: `data_abertura` majoritariamente nula, licitação sem item,
 * CNPJ com zero à esquerda e valores em faixas muito distintas.
 */
trait SemeiaBase
{
    /** Semeia serie_mensal, incluindo as competências atípicas. */
    protected function semearSerie(): void
    {
        DB::table('serie_mensal')->insert([
            ['competencia' => '202306', 'codigo_orgao' => '22000', 'codigo_modalidade' => 5, 'quantidade_licitacoes' => 1, 'valor_total' => '1200000.0000', 'valor_mediano' => '1200000.0000'],
            ['competencia' => '202312', 'codigo_orgao' => '26000', 'codigo_modalidade' => 5, 'quantidade_licitacoes' => 1, 'valor_total' => null, 'valor_mediano' => null],
            ['competencia' => '202401', 'codigo_orgao' => '26000', 'codigo_modalidade' => 5, 'quantidade_licitacoes' => 1, 'valor_total' => '250000.0000', 'valor_mediano' => '250000.0000'],
            ['competencia' => '202402', 'codigo_orgao' => '26000', 'codigo_modalidade' => 8, 'quantidade_licitacoes' => 1, 'valor_total' => '5000.0000', 'valor_mediano' => '5000.0000'],
            // 201812 tem licitações mas nenhum participante: a fonte publica o
            // ZIP truncado. 202404 encerra a série e vem com volume anômalo.
            ['competencia' => '201812', 'codigo_orgao' => '26000', 'codigo_modalidade' => 5, 'quantidade_licitacoes' => 40, 'valor_total' => '900000.0000', 'valor_mediano' => '20000.0000'],
            ['competencia' => '202404', 'codigo_orgao' => '26000', 'codigo_modalidade' => 5, 'quantidade_licitacoes' => 2, 'valor_total' => '10000.0000', 'valor_mediano' => '5000.0000'],
        ]);
    }

    protected function semear(): void
    {
        DB::table('modalidade')->insert([
            ['codigo' => 5, 'nome' => 'Pregão'],
            ['codigo' => 8, 'nome' => 'Dispensa de Licitação'],
        ]);

        DB::table('orgao')->insert([
            ['codigo_orgao' => '26000', 'nome' => 'Ministério da Educação', 'codigo_orgao_superior' => null],
            ['codigo_orgao' => '22000', 'nome' => 'Ministério da Agricultura', 'codigo_orgao_superior' => null],
        ]);

        DB::table('unidade_gestora')->insert([
            ['codigo_ug' => '150001', 'nome' => 'UFV', 'uf' => 'MG', 'municipio' => 'Viçosa', 'codigo_orgao' => '26000'],
            ['codigo_ug' => '150002', 'nome' => 'USP', 'uf' => 'SP', 'municipio' => 'São Paulo', 'codigo_orgao' => '26000'],
            ['codigo_ug' => '130094', 'nome' => 'Embrapa', 'uf' => 'SP', 'municipio' => 'Campinas', 'codigo_orgao' => '22000'],
        ]);

        DB::table('fornecedor')->insert([
            // Zero à esquerda preservado, e CPF de 11 dígitos: os dois casos
            // existem no dado real e quebram se a chave for tratada como int.
            ['cnpj' => '08488971451', 'nome' => 'Fornecedor Pessoa Física'],
            ['cnpj' => '64799539000135', 'nome' => 'Fornecedor Grande'],
        ]);

        DB::table('licitacao')->insert([
            [
                'numero_licitacao' => '000012024', 'codigo_ug' => '150002', 'codigo_modalidade' => 5,
                'numero_processo' => 'P-1', 'objeto' => 'Aquisição de microscópios',
                'situacao' => 'Encerrado', 'data_abertura' => null, 'data_resultado' => '2024-01-15',
                'valor' => '250000.0000', 'competencia' => '202401',
            ],
            [
                'numero_licitacao' => '000022024', 'codigo_ug' => '150001', 'codigo_modalidade' => 8,
                'numero_processo' => 'P-2', 'objeto' => 'Serviço de limpeza',
                'situacao' => 'Em andamento', 'data_abertura' => '2024-02-01', 'data_resultado' => null,
                'valor' => '5000.0000', 'competencia' => '202402',
            ],
            [
                'numero_licitacao' => '000032023', 'codigo_ug' => '130094', 'codigo_modalidade' => 5,
                'numero_processo' => 'P-3', 'objeto' => 'Sementes de soja',
                'situacao' => 'Encerrado', 'data_abertura' => null, 'data_resultado' => '2023-06-10',
                'valor' => '1200000.0000', 'competencia' => '202306',
            ],
            [
                // Sem item e sem participante: 1,78% das licitações reais são assim.
                'numero_licitacao' => '000042023', 'codigo_ug' => '150002', 'codigo_modalidade' => 5,
                'numero_processo' => 'P-4', 'objeto' => 'Licitação sem itens',
                'situacao' => 'Deserta', 'data_abertura' => null, 'data_resultado' => null,
                'valor' => null, 'competencia' => '202312',
            ],
        ]);

        $ids = DB::table('licitacao')->orderBy('id')->pluck('id', 'numero_licitacao');

        DB::table('item_licitacao')->insert([
            ['licitacao_id' => $ids['000012024'], 'codigo_item_compra' => '1', 'descricao' => 'Microscópio óptico', 'quantidade' => '2.0000', 'valor_item' => '100000.0000', 'cnpj_vencedor' => '64799539000135'],
            ['licitacao_id' => $ids['000012024'], 'codigo_item_compra' => '2', 'descricao' => 'Lâminas', 'quantidade' => '100.0000', 'valor_item' => '500.0000', 'cnpj_vencedor' => '08488971451'],
            ['licitacao_id' => $ids['000032023'], 'codigo_item_compra' => '1', 'descricao' => 'Semente certificada', 'quantidade' => '1000.0000', 'valor_item' => '1200.0000', 'cnpj_vencedor' => '64799539000135'],
        ]);

        DB::table('participante_licitacao')->insert([
            ['licitacao_id' => $ids['000012024'], 'codigo_item_compra' => '1', 'cnpj_participante' => '64799539000135', 'flag_vencedor' => true],
            ['licitacao_id' => $ids['000012024'], 'codigo_item_compra' => '1', 'cnpj_participante' => '08488971451', 'flag_vencedor' => false],
            ['licitacao_id' => $ids['000032023'], 'codigo_item_compra' => '1', 'cnpj_participante' => '64799539000135', 'flag_vencedor' => true],
        ]);
    }
}
