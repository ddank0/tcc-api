<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListarAnomaliasRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class AnomaliesController extends Controller
{
    /**
     * Texto exibido pela tela de anomalias, fixo e testado.
     *
     * Restrição de produto: o sistema aponta desvio estatístico em relação ao
     * histórico e não caracteriza conduta. A tela apenas exibe.
     */
    public const AVISO = 'Os scores indicam atipicidade estatística em relação ao padrão '
        .'histórico. Um registro atípico não caracteriza qualquer conduta: o sinal é '
        .'insumo para análise humana, e a contribuição dos atributos existe para que '
        .'cada posição possa ser compreendida e contestada.';

    /** Como a contribuição é calculada; devolvido no detalhe. */
    public const METODO = 'Contribuição por desvio robusto: para cada atributo, a distância '
        .'da mediana da população em unidades de IQR. Não é SHAP - responde "o que está '
        .'longe do típico neste registro", com o valor do atributo exposto junto.';

    #[OA\Get(
        path: '/anomalies',
        summary: 'Ranking de atipicidade',
        description: 'Scores materializados pelo job de scoring. Maior score = mais atípico.',
        tags: ['Análises'],
        parameters: [
            new OA\QueryParameter(name: 'competencia_de', description: 'AAAAMM', schema: new OA\Schema(type: 'string')),
            new OA\QueryParameter(name: 'competencia_ate', description: 'AAAAMM', schema: new OA\Schema(type: 'string')),
            new OA\QueryParameter(name: 'codigo_orgao', schema: new OA\Schema(type: 'string')),
            new OA\QueryParameter(name: 'page', schema: new OA\Schema(type: 'integer', minimum: 1)),
            new OA\QueryParameter(name: 'per_page', schema: new OA\Schema(type: 'integer', maximum: 100)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Ranking ordenado por posição',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(properties: [
                        new OA\Property(property: 'licitacao_id', type: 'integer'),
                        new OA\Property(property: 'score', type: 'string'),
                        new OA\Property(property: 'posicao_ranking', type: 'integer'),
                        new OA\Property(property: 'licitacao', type: 'object'),
                    ], type: 'object')),
                    new OA\Property(property: 'meta', properties: [
                        new OA\Property(property: 'total', type: 'integer'),
                        new OA\Property(property: 'aviso', type: 'string'),
                        new OA\Property(property: 'algoritmo', type: 'string', nullable: true),
                        new OA\Property(property: 'executado_em', type: 'string', nullable: true),
                    ], type: 'object'),
                ], type: 'object')
            ),
            new OA\Response(response: 422, description: 'Parâmetro inválido'),
        ]
    )]
    private static function texto(mixed $valor): string
    {
        return is_scalar($valor) ? (string) $valor : '';
    }

    private static function inteiro(mixed $valor): int
    {
        return is_numeric($valor) ? (int) $valor : 0;
    }

    public function index(ListarAnomaliasRequest $request): JsonResponse
    {
        $consulta = DB::table('score_anomalia AS s')
            ->join('licitacao AS l', 'l.id', '=', 's.licitacao_id')
            ->join('unidade_gestora AS u', 'u.codigo_ug', '=', 'l.codigo_ug')
            ->leftJoin('orgao AS o', 'o.codigo_orgao', '=', 'u.codigo_orgao')
            ->orderBy('s.posicao_ranking');

        // Filtro pela coluna desnormalizada de score_anomalia, não pela do
        // join: o índice composto (competencia, posicao_ranking) atende o
        // recorte direto - via join, o p95 media 542 ms.
        if ($request->filled('competencia_de')) {
            $consulta->where('s.competencia', '>=', $request->string('competencia_de')->toString());
        }
        if ($request->filled('competencia_ate')) {
            $consulta->where('s.competencia', '<=', $request->string('competencia_ate')->toString());
        }
        if ($request->filled('codigo_orgao')) {
            $consulta->where('u.codigo_orgao', $request->string('codigo_orgao')->toString());
        }

        $porPagina = $request->integer('per_page', 25);
        $pagina = max(1, $request->integer('page', 1));

        $itens = $consulta
            ->offset(($pagina - 1) * $porPagina)
            ->limit($porPagina)
            ->get([
                's.licitacao_id', 's.score', 's.posicao_ranking',
                'l.numero_licitacao', 'l.objeto', 'l.valor', 'l.competencia', 'o.nome AS orgao',
            ]);

        // O join com licitacao é 1:1 por FK: sem filtro, contar só a tabela
        // de scores dá o mesmo total e evita o join de 1,74M linhas - o COUNT
        // do paginate custava ~600 ms sozinho.
        // Só o filtro de órgão exige o join na contagem; competência conta
        // direto na tabela de scores, pela coluna desnormalizada.
        if ($request->filled('codigo_orgao')) {
            $total = $consulta->count();
        } else {
            $contagem = DB::table('score_anomalia');
            if ($request->filled('competencia_de')) {
                $contagem->where('competencia', '>=', $request->string('competencia_de')->toString());
            }
            if ($request->filled('competencia_ate')) {
                $contagem->where('competencia', '<=', $request->string('competencia_ate')->toString());
            }
            $total = $contagem->count();
        }

        $execucao = DB::table('execucao_modelo')->where('tipo', 'anomaly:licitacao')->first();

        return response()->json([
            'data' => array_map(function (object $item): array {
                /** @var array<string, mixed> $l */
                $l = (array) $item;

                return [
                    'licitacao_id' => self::inteiro($l['licitacao_id'] ?? 0),
                    'score' => self::texto($l['score'] ?? null),
                    'posicao_ranking' => self::inteiro($l['posicao_ranking'] ?? 0),
                    'licitacao' => [
                        'numero_licitacao' => self::texto($l['numero_licitacao'] ?? null),
                        'objeto' => ($l['objeto'] ?? null) === null ? null : self::texto($l['objeto']),
                        'valor' => ($l['valor'] ?? null) === null ? null : self::texto($l['valor']),
                        'competencia' => self::texto($l['competencia'] ?? null),
                        'orgao' => ($l['orgao'] ?? null) === null ? null : self::texto($l['orgao']),
                    ],
                ];
            }, $itens->all()),
            'meta' => [
                'total' => $total,
                'per_page' => $porPagina,
                'current_page' => $pagina,
                'aviso' => self::AVISO,
                'algoritmo' => $execucao?->algoritmo,
                'executado_em' => $execucao?->executado_em,
            ],
        ]);
    }

    #[OA\Get(
        path: '/anomalies/{id}',
        summary: 'Score de uma licitação, com a contribuição dos atributos',
        tags: ['Análises'],
        parameters: [new OA\PathParameter(name: 'id', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Score, contribuições e método'),
            new OA\Response(response: 404, description: 'Licitação sem score'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $linha = DB::table('score_anomalia AS s')
            ->join('licitacao AS l', 'l.id', '=', 's.licitacao_id')
            ->where('s.licitacao_id', $id)
            ->first([
                's.score', 's.posicao_ranking', 's.features_json',
                'l.numero_licitacao', 'l.objeto', 'l.valor', 'l.competencia',
            ]);

        if ($linha === null) {
            return response()->json(['message' => 'Licitação sem score de atipicidade.'], 404);
        }

        /** @var array<string, mixed> $dados */
        $dados = (array) $linha;
        /** @var array{valores?: array<string, mixed>, contribuicoes?: list<array<string, mixed>>} $features */
        $features = json_decode(self::texto($dados['features_json'] ?? null), true) ?? [];

        return response()->json([
            'data' => [
                'licitacao_id' => $id,
                'score' => self::texto($dados['score'] ?? null),
                'posicao_ranking' => self::inteiro($dados['posicao_ranking'] ?? 0),
                'contribuicoes' => $features['contribuicoes'] ?? [],
                'valores' => $features['valores'] ?? [],
                'metodo' => self::METODO,
                'licitacao' => [
                    'numero_licitacao' => self::texto($dados['numero_licitacao'] ?? null),
                    'objeto' => ($dados['objeto'] ?? null) === null ? null : self::texto($dados['objeto']),
                    'valor' => ($dados['valor'] ?? null) === null ? null : self::texto($dados['valor']),
                    'competencia' => self::texto($dados['competencia'] ?? null),
                ],
            ],
            'meta' => ['aviso' => self::AVISO],
        ]);
    }
}
