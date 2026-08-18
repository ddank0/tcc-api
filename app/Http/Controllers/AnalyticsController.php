<?php

namespace App\Http\Controllers;

use App\Http\Requests\PeriodoRequest;
use App\Support\CompetenciasAtipicas;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Análises históricas. Todas leem tabelas materializadas pelo `aggregate`.
 *
 * Nenhum endpoint aqui toca item_licitacao ou participante_licitacao: o
 * ranking de fornecedores a partir dos 14,2 milhões de itens levava 7,9 s, e a
 * regra do projeto é que a API não executa cálculo caro.
 */
class AnalyticsController extends Controller
{
    /** Evolução temporal. Medido em 19 ms sobre serie_mensal. */
    public function evolucao(PeriodoRequest $request): JsonResponse
    {
        $linhas = $this->comPeriodo(DB::table('serie_mensal'), $request, 'competencia')
            ->selectRaw('competencia, sum(quantidade_licitacoes) AS quantidade_licitacoes, sum(valor_total) AS valor_total')
            ->groupBy('competencia')
            ->orderBy('competencia')
            ->get();

        $dados = $linhas->map(fn (object $l): array => [
            'competencia' => self::texto($l->competencia) ?? '',
            'quantidade_licitacoes' => self::inteiro($l->quantidade_licitacoes),
            // Nulo permanece nulo: somar como zero mascararia ausência de dado.
            'valor_total' => self::texto($l->valor_total),
            'parcial' => CompetenciasAtipicas::ehParcial(self::texto($l->competencia) ?? ''),
        ])->all();

        /** @var list<string> $competencias */
        $competencias = array_column($dados, 'competencia');

        return response()->json([
            'data' => $dados,
            'meta' => [
                'competencias' => count($dados),
                'competencias_parciais' => CompetenciasAtipicas::motivosDe($competencias),
            ],
        ]);
    }

    /** Distribuição por modalidade. Medido em 26 ms. */
    public function modalidades(PeriodoRequest $request): JsonResponse
    {
        $linhas = $this->comPeriodo(DB::table('serie_mensal AS s'), $request, 's.competencia')
            ->leftJoin('modalidade AS m', 'm.codigo', '=', 's.codigo_modalidade')
            ->selectRaw('s.codigo_modalidade, m.nome, sum(s.quantidade_licitacoes) AS quantidade_licitacoes, sum(s.valor_total) AS valor_total')
            ->groupBy('s.codigo_modalidade', 'm.nome')
            ->orderByDesc('quantidade_licitacoes')
            ->get();

        return response()->json([
            'data' => $linhas->map(fn (object $l): array => [
                'codigo_modalidade' => $l->codigo_modalidade === null ? null : self::inteiro($l->codigo_modalidade),
                'nome' => self::texto($l->nome),
                'quantidade_licitacoes' => self::inteiro($l->quantidade_licitacoes),
                'valor_total' => self::texto($l->valor_total),
            ])->all(),
        ]);
    }

    /** Ranking de órgãos. Lê serie_mensal. */
    public function orgaos(PeriodoRequest $request): JsonResponse
    {
        $linhas = $this->comPeriodo(DB::table('serie_mensal AS s'), $request, 's.competencia')
            ->leftJoin('orgao AS o', 'o.codigo_orgao', '=', 's.codigo_orgao')
            ->selectRaw('s.codigo_orgao, o.nome, sum(s.quantidade_licitacoes) AS quantidade_licitacoes, sum(s.valor_total) AS valor_total')
            ->groupBy('s.codigo_orgao', 'o.nome')
            ->orderByRaw('sum(s.valor_total) DESC NULLS LAST')
            ->limit($request->integer('limit', 20))
            ->get();

        return response()->json([
            'data' => $linhas->map(fn (object $l): array => [
                'codigo_orgao' => self::texto($l->codigo_orgao),
                'nome' => self::texto($l->nome),
                'quantidade_licitacoes' => self::inteiro($l->quantidade_licitacoes),
                'valor_total' => self::texto($l->valor_total),
            ])->all(),
        ]);
    }

    /**
     * Ranking de fornecedores.
     *
     * A tabela é escolhida pela presença de filtro de período. Sem filtro, lê
     * ranking_fornecedor_total, com 314.731 linhas: 33 ms. Com filtro, lê
     * ranking_fornecedor, com 1,65 milhão: 205 ms. Usar a granularidade fina
     * no caso global custaria 1.530 ms, 3x o orçamento - e é o caso que a tela
     * abre por padrão.
     *
     * Nenhum dos dois toca item_licitacao: agregar os 14,2 milhões de itens em
     * tempo de request levava 7.866 ms.
     */
    public function fornecedores(PeriodoRequest $request): JsonResponse
    {
        $comPeriodo = $request->filled('competencia_de') || $request->filled('competencia_ate');
        $limite = $request->integer('limit', 20);

        $linhas = $comPeriodo
            ? $this->comPeriodo(DB::table('ranking_fornecedor AS r'), $request, 'r.competencia')
                ->leftJoin('fornecedor AS f', 'f.cnpj', '=', 'r.cnpj')
                ->selectRaw('r.cnpj, f.nome, sum(r.itens_vencidos) AS itens_vencidos, sum(r.licitacoes_distintas) AS licitacoes_distintas, sum(r.valor_total) AS valor_total')
                ->groupBy('r.cnpj', 'f.nome')
                ->orderByRaw('sum(r.valor_total) DESC NULLS LAST')
                ->limit($limite)
                ->get()
            : DB::table('ranking_fornecedor_total AS r')
                ->leftJoin('fornecedor AS f', 'f.cnpj', '=', 'r.cnpj')
                ->select('r.cnpj', 'f.nome', 'r.itens_vencidos', 'r.licitacoes_distintas', 'r.valor_total')
                ->orderByRaw('r.valor_total DESC NULLS LAST')
                ->limit($limite)
                ->get();

        return response()->json([
            'data' => $linhas->map(fn (object $l): array => [
                'cnpj' => self::texto($l->cnpj) ?? '',
                'nome' => self::texto($l->nome),
                'itens_vencidos' => self::inteiro($l->itens_vencidos),
                'licitacoes_distintas' => self::inteiro($l->licitacoes_distintas),
                'valor_total' => self::texto($l->valor_total),
            ])->all(),
            'meta' => [
                'granularidade' => $comPeriodo ? 'por_competencia' : 'global',
            ],
        ]);
    }

    /** Converte valor vindo do banco, que o PHPStan enxerga como mixed. */
    private static function texto(mixed $valor): ?string
    {
        return $valor === null ? null : (string) (is_scalar($valor) ? $valor : '');
    }

    private static function inteiro(mixed $valor): int
    {
        return is_numeric($valor) ? (int) $valor : 0;
    }

    private function comPeriodo(Builder $consulta, PeriodoRequest $request, string $coluna): Builder
    {
        if ($request->filled('competencia_de')) {
            $consulta->where($coluna, '>=', $request->string('competencia_de')->toString());
        }

        if ($request->filled('competencia_ate')) {
            $consulta->where($coluna, '<=', $request->string('competencia_ate')->toString());
        }

        return $consulta;
    }
}
