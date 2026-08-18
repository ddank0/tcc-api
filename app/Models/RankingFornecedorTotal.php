<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Ranking global. Existe porque somar as 1,65 milhão de linhas de
 * ranking_fornecedor em tempo de request custa 1.530 ms; aqui são 33 ms.
 *
 * @property string $cnpj
 * @property int $itens_vencidos
 * @property int $licitacoes_distintas
 * @property numeric-string|null $valor_total
 */
class RankingFornecedorTotal extends Model
{
    protected $table = 'ranking_fornecedor_total';

    protected $primaryKey = 'cnpj';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    /** @var array<string, string> */
    protected $casts = [
        'itens_vencidos' => 'integer',
        'licitacoes_distintas' => 'integer',
        'valor_total' => 'decimal:4',
    ];
}
