<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $licitacao_id
 * @property string|null $codigo_item_compra
 * @property string|null $descricao
 * @property numeric-string|null $quantidade
 * @property numeric-string|null $valor_item
 * @property string|null $cnpj_vencedor
 */
class ItemLicitacao extends Model
{
    protected $table = 'item_licitacao';

    public $timestamps = false;

    /** @var array<string, string> */
    protected $casts = [
        'licitacao_id' => 'integer',
        'quantidade' => 'decimal:4',
        'valor_item' => 'decimal:4',
    ];

    /** @return BelongsTo<Licitacao, $this> */
    public function licitacao(): BelongsTo
    {
        return $this->belongsTo(Licitacao::class, 'licitacao_id');
    }

    /** @return BelongsTo<Fornecedor, $this> */
    public function vencedor(): BelongsTo
    {
        return $this->belongsTo(Fornecedor::class, 'cnpj_vencedor', 'cnpj');
    }
}
