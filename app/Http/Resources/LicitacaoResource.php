<?php

namespace App\Http\Resources;

use App\Models\Licitacao;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Licitacao */
class LicitacaoResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'numero_licitacao' => $this->numero_licitacao,
            'numero_processo' => $this->numero_processo,
            'objeto' => $this->objeto,
            'situacao' => $this->situacao,
            'valor' => $this->valor,
            'competencia' => $this->competencia,
            // Nula em 72,6% dos registros. Exposta como está - preencher com
            // um valor inventado esconderia a lacuna.
            'data_abertura' => $this->data_abertura?->toDateString(),
            'data_resultado' => $this->data_resultado?->toDateString(),
            'modalidade' => [
                'codigo' => $this->modalidade?->codigo,
                'nome' => $this->modalidade?->nome,
            ],
            'unidade_gestora' => [
                'codigo_ug' => $this->unidadeGestora?->codigo_ug,
                'nome' => $this->unidadeGestora?->nome,
                'uf' => $this->unidadeGestora?->uf,
                'municipio' => $this->unidadeGestora?->municipio,
                'orgao' => [
                    'codigo_orgao' => $this->unidadeGestora?->orgao?->codigo_orgao,
                    'nome' => $this->unidadeGestora?->orgao?->nome,
                ],
            ],
        ];
    }
}
