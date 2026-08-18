<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $licitacao_id
 * @property string|null $codigo_item_compra
 * @property string|null $cnpj_participante
 * @property bool|null $flag_vencedor
 */
class ParticipanteLicitacao extends Model
{
    protected $table = 'participante_licitacao';

    public $timestamps = false;

    /**
     * Para atributos de competitividade, `flag_vencedor` é a fonte de verdade -
     * ela e `item.cnpj_vencedor` discordam em alguns casos por competência.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'licitacao_id' => 'integer',
        'flag_vencedor' => 'boolean',
    ];

    /** @return BelongsTo<Licitacao, $this> */
    public function licitacao(): BelongsTo
    {
        return $this->belongsTo(Licitacao::class, 'licitacao_id');
    }

    /** @return BelongsTo<Fornecedor, $this> */
    public function fornecedor(): BelongsTo
    {
        return $this->belongsTo(Fornecedor::class, 'cnpj_participante', 'cnpj');
    }
}
