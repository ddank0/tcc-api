<?php

namespace App\Http\Controllers;

use App\Models\IngestaoLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
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
