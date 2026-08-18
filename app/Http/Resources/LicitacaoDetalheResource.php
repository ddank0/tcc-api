<?php

namespace App\Http\Resources;

use App\Models\Licitacao;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Licitacao */
class LicitacaoDetalheResource extends JsonResource
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
            'itens' => $this->itens->map(fn ($item): array => [
                'codigo_item_compra' => $item->codigo_item_compra,
                'descricao' => $item->descricao,
                'quantidade' => $item->quantidade,
                'valor_item' => $item->valor_item,
                'cnpj_vencedor' => $item->cnpj_vencedor,
            ])->all(),
            'participantes' => $this->participantes->map(fn ($p): array => [
                'codigo_item_compra' => $p->codigo_item_compra,
                'cnpj_participante' => $p->cnpj_participante,
                // Fonte de verdade para atributos de competitividade.
                'flag_vencedor' => $p->flag_vencedor,
            ])->all(),
            'totais' => [
                'itens' => $this->itens->count(),
                'participantes' => $this->participantes->count(),
            ],
        ];
    }
}
