<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Ranking por competência, para o caso com filtro de período.
 *
 * @property int $id
 * @property string $competencia
 * @property string $cnpj
 * @property int $itens_vencidos
 * @property int $licitacoes_distintas
 * @property numeric-string|null $valor_total
 */
class RankingFornecedor extends Model
{
    protected $table = 'ranking_fornecedor';

    public $timestamps = false;

    /** @var array<string, string> */
    protected $casts = [
        'itens_vencidos' => 'integer',
        'licitacoes_distintas' => 'integer',
        // Numeric(38,4) no banco: valor_item * quantidade chega a 9,6e20, e
        // 1.232 itens passam do limite de 18 dígitos. Não usar float aqui.
        'valor_total' => 'decimal:4',
    ];
}
