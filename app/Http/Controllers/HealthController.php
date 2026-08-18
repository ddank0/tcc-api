<?php

namespace App\Http\Controllers;

use App\Models\IngestaoLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;
use Throwable;

class HealthController extends Controller
{
    /**
     * Estado do serviço e da última ingestão.
     *
     * Atende ao RF10: a API não executa job, mas precisa dizer quando o
     * último rodou, e com que resultado. A tabela tem uma linha por
     * competência e permanece pequena - 136 hoje.
     */
    #[OA\Get(
        path: '/health',
        summary: 'Estado do serviço e da última ingestão',
        tags: ['Serviço'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Estado atual',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'status', type: 'string'),
                    new OA\Property(property: 'database', type: 'string', enum: ['ok', 'erro']),
                    new OA\Property(property: 'ultima_ingestao', nullable: true, properties: [
                        new OA\Property(property: 'competencia', type: 'string'),
                        new OA\Property(property: 'arquivo', type: 'string', nullable: true),
                        new OA\Property(property: 'status', type: 'string'),
                        new OA\Property(property: 'mensagem_erro', type: 'string', nullable: true),
                        new OA\Property(property: 'iniciado_em', type: 'string', format: 'date-time', nullable: true),
                        new OA\Property(property: 'finalizado_em', type: 'string', format: 'date-time', nullable: true),
                        new OA\Property(property: 'linhas_lidas', type: 'integer'),
                        new OA\Property(property: 'linhas_inseridas', type: 'integer'),
                        new OA\Property(property: 'linhas_rejeitadas', type: 'integer'),
                    ], type: 'object'),
                ], type: 'object')
            ),
        ]
    )]
    public function index(): JsonResponse
    {
        try {
            DB::connection()->getPdo();
            $banco = 'ok';
        } catch (Throwable) {
            $banco = 'erro';
        }

        return response()->json([
            'status' => 'ok',
            'database' => $banco,
            'ultima_ingestao' => $banco === 'ok' ? $this->ultimaIngestao() : null,
        ]);
    }

    /** @return array<string, mixed>|null */
    private function ultimaIngestao(): ?array
    {
        // Por finalizado_em, não por id: reprocessar uma competência antiga
        // insere uma linha nova, e ordenar por id apontaria para ela.
        $log = IngestaoLog::query()->orderByDesc('finalizado_em')->first();

        if ($log === null) {
            return null;
        }

        return [
            'competencia' => $log->competencia,
            'arquivo' => $log->arquivo,
            'status' => $log->status,
            'mensagem_erro' => $log->mensagem_erro,
            'iniciado_em' => $log->iniciado_em?->toIso8601String(),
            'finalizado_em' => $log->finalizado_em?->toIso8601String(),
            'linhas_lidas' => $log->linhas_lidas,
            'linhas_inseridas' => $log->linhas_inseridas,
            'linhas_rejeitadas' => $log->linhas_rejeitadas,
        ];
    }
}
