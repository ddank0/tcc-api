<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListarPrevisoesRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class ForecastController extends Controller
{
    /**
     * Previsões da rodada vigente, com intervalo e procedência.
     *
     * Lê a tabela materializada pelo job train - a API nunca executa modelo.
     * A tabela guarda só a rodada vigente por agrupamento, então não há
     * filtro de execução aqui.
     */
    #[OA\Get(
        path: '/forecast',
        summary: 'Previsões com intervalo de confiança',
        description: 'Materializado pelo job de treino. O meta traz a procedência: algoritmo, janela de treino e quando rodou.',
        tags: ['Análises'],
        parameters: [
            new OA\QueryParameter(name: 'serie', required: true, description: 'orgao:{codigo}, modalidade:{codigo} ou global', schema: new OA\Schema(type: 'string')),
            new OA\QueryParameter(name: 'alvo', schema: new OA\Schema(type: 'string', enum: ['quantidade', 'valor'])),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Previsões ordenadas por competência',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(properties: [
                        new OA\Property(property: 'competencia_alvo', type: 'string', example: '202405'),
                        new OA\Property(property: 'alvo', type: 'string', enum: ['quantidade', 'valor']),
                        new OA\Property(property: 'valor_previsto', type: 'string'),
                        new OA\Property(property: 'ic_inferior', type: 'string', nullable: true),
                        new OA\Property(property: 'ic_superior', type: 'string', nullable: true),
                    ], type: 'object')),
                    new OA\Property(property: 'meta', properties: [
                        new OA\Property(property: 'serie', type: 'string'),
                        new OA\Property(property: 'algoritmo', type: 'string', nullable: true),
                        new OA\Property(property: 'janela_treino_inicio', type: 'string', nullable: true),
                        new OA\Property(property: 'janela_treino_fim', type: 'string', nullable: true),
                        new OA\Property(property: 'executado_em', type: 'string', format: 'date-time', nullable: true),
                    ], type: 'object'),
                ], type: 'object')
            ),
            new OA\Response(response: 422, description: 'Série malformada ou ausente'),
        ]
    )]
    private static function texto(mixed $valor): string
    {
        return is_scalar($valor) ? (string) $valor : '';
    }

    public function index(ListarPrevisoesRequest $request): JsonResponse
    {
        $serie = $request->string('serie')->toString();

        $consulta = DB::table('previsao AS p')
            ->join('execucao_modelo AS e', 'e.id', '=', 'p.execucao_id')
            ->where('p.serie_chave', $serie)
            ->orderBy('p.competencia_alvo')
            ->orderBy('p.alvo');

        if ($request->filled('alvo')) {
            $consulta->where('p.alvo', $request->string('alvo')->toString());
        }

        $linhas = $consulta->get([
            'p.competencia_alvo', 'p.alvo', 'p.valor_previsto',
            'p.ic_inferior', 'p.ic_superior',
            'e.algoritmo', 'e.janela_treino_inicio', 'e.janela_treino_fim', 'e.executado_em',
        ]);

        $primeira = $linhas->first();

        return response()->json([
            'data' => $linhas->map(fn (object $l): array => [
                'competencia_alvo' => self::texto($l->competencia_alvo),
                'alvo' => self::texto($l->alvo),
                'valor_previsto' => self::texto($l->valor_previsto),
                'ic_inferior' => $l->ic_inferior === null ? null : self::texto($l->ic_inferior),
                'ic_superior' => $l->ic_superior === null ? null : self::texto($l->ic_superior),
            ])->all(),
            'meta' => [
                'serie' => $serie,
                // Procedência: sem algoritmo, janela e data, o número vira
                // oráculo - não dá para contestar nem reproduzir.
                'algoritmo' => $primeira?->algoritmo,
                'janela_treino_inicio' => $primeira?->janela_treino_inicio,
                'janela_treino_fim' => $primeira?->janela_treino_fim,
                'executado_em' => $primeira?->executado_em,
            ],
        ]);
    }
}
