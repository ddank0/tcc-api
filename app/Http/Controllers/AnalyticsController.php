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
