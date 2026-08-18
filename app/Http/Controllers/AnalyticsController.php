<?php

namespace App\Http\Controllers;

use App\Http\Requests\PeriodoRequest;
use App\Support\CompetenciasAtipicas;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

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
    #[OA\Get(
        path: '/analytics/evolucao',
        summary: 'Série temporal de quantidade e valor por competência',
        tags: ['Análises'],
        parameters: [
            new OA\QueryParameter(name: 'competencia_de', description: 'AAAAMM', schema: new OA\Schema(type: 'string')),
            new OA\QueryParameter(name: 'competencia_ate', description: 'AAAAMM', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Série ordenada por competência',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/PontoDaSerie')),
                    new OA\Property(property: 'meta', properties: [
                        new OA\Property(property: 'competencias', type: 'integer'),
                        new OA\Property(property: 'competencias_parciais', description: 'Motivo de cada competência marcada como parcial.', type: 'object', additionalProperties: new OA\AdditionalProperties(type: 'string')),
                    ], type: 'object'),
                ], type: 'object')
            ),
        ]
    )]
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
    #[OA\Get(
        path: '/analytics/modalidades',
        summary: 'Distribuição por modalidade',
        tags: ['Análises'],
        parameters: [
            new OA\QueryParameter(name: 'competencia_de', description: 'AAAAMM', schema: new OA\Schema(type: 'string')),
            new OA\QueryParameter(name: 'competencia_ate', description: 'AAAAMM', schema: new OA\Schema(type: 'string')),
        ],
        responses: [new OA\Response(response: 200, description: 'Modalidades ordenadas por quantidade')]
    )]
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
    #[OA\Get(
        path: '/analytics/orgaos',
        summary: 'Ranking de órgãos por valor',
        tags: ['Análises'],
        parameters: [
            new OA\QueryParameter(name: 'competencia_de', description: 'AAAAMM', schema: new OA\Schema(type: 'string')),
            new OA\QueryParameter(name: 'competencia_ate', description: 'AAAAMM', schema: new OA\Schema(type: 'string')),
            new OA\QueryParameter(name: 'limit', schema: new OA\Schema(type: 'integer', maximum: 100)),
        ],
        responses: [new OA\Response(response: 200, description: 'Órgãos ordenados por valor total')]
    )]
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
    #[OA\Get(
        path: '/analytics/fornecedores',
        summary: 'Ranking de fornecedores por valor',
        description: 'Sem filtro de período lê a tabela global; com filtro, a tabela por competência. A granularidade usada vem em meta.',
        tags: ['Análises'],
        parameters: [
            new OA\QueryParameter(name: 'competencia_de', description: 'AAAAMM', schema: new OA\Schema(type: 'string')),
            new OA\QueryParameter(name: 'competencia_ate', description: 'AAAAMM', schema: new OA\Schema(type: 'string')),
            new OA\QueryParameter(name: 'limit', schema: new OA\Schema(type: 'integer', maximum: 100)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Fornecedores ordenados por valor total',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/RankingFornecedor')),
                    new OA\Property(property: 'meta', properties: [
                        new OA\Property(property: 'granularidade', type: 'string', enum: ['global', 'por_competencia']),
                    ], type: 'object'),
                ], type: 'object')
            ),
        ]
    )]
    public function fornecedores(PeriodoRequest $request): JsonResponse
    {
        // Um intervalo que cobre a série inteira é o ranking global, e ler a
        // granularidade fina nesse caso custa 1.099 ms contra 33 ms. Não é
        // atalho: as duas tabelas somam exatamente o mesmo total.
        $comPeriodo = ($request->filled('competencia_de') || $request->filled('competencia_ate'))
            && ! $this->cobreASerieInteira($request);
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

    /**
     * O recorte pedido abrange toda a série disponível?
     *
     * Os limites vêm do banco, e não de constante: a janela da fonte pode
     * mudar se o conector do PNCP entrar, e um limite fixo passaria a mentir.
     */
    private function cobreASerieInteira(PeriodoRequest $request): bool
    {
        /** @var object{minimo: ?string, maximo: ?string}|null $limites */
        $limites = DB::table('ranking_fornecedor')
            ->selectRaw('min(competencia) AS minimo, max(competencia) AS maximo')
            ->first();

        if ($limites?->minimo === null || $limites->maximo === null) {
            return false;
        }

        $de = $request->string('competencia_de', $limites->minimo)->toString();
        $ate = $request->string('competencia_ate', $limites->maximo)->toString();

        return $de <= $limites->minimo && $ate >= $limites->maximo;
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
