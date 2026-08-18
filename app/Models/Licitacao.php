<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $numero_licitacao
 * @property string $codigo_ug
 * @property int $codigo_modalidade
 * @property string|null $numero_processo
 * @property string|null $objeto
 * @property string|null $situacao
 * @property Carbon|null $data_abertura
 * @property Carbon|null $data_resultado
 * @property numeric-string|null $valor
 * @property string $competencia
 */
class Licitacao extends Model
{
    protected $table = 'licitacao';

    public $timestamps = false;

    /**
     * `data_abertura` vem nula em 72,6% das linhas na série inteira. O cast
     * mantém o nulo; qualquer filtro por essa coluna descartaria três de cada
     * quatro registros, e é por isso que o recorte de período usa competencia.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'codigo_modalidade' => 'integer',
        'data_abertura' => 'date',
        'data_resultado' => 'date',
        'valor' => 'decimal:4',
    ];

    /** @return BelongsTo<Modalidade, $this> */
    public function modalidade(): BelongsTo
    {
        return $this->belongsTo(Modalidade::class, 'codigo_modalidade', 'codigo');
    }

    /** @return BelongsTo<UnidadeGestora, $this> */
    public function unidadeGestora(): BelongsTo
    {
        return $this->belongsTo(UnidadeGestora::class, 'codigo_ug', 'codigo_ug');
    }

    /** @return HasMany<ItemLicitacao, $this> */
    public function itens(): HasMany
    {
        return $this->hasMany(ItemLicitacao::class, 'licitacao_id');
    }

    /**
     * 12.424 participantes por competência apontam para item inexistente, e é
     * por isso que não há chave estrangeira de participante para item. A
     * relação existe só com a licitação.
     *
     * @return HasMany<ParticipanteLicitacao, $this>
     */
    public function participantes(): HasMany
    {
        return $this->hasMany(ParticipanteLicitacao::class, 'licitacao_id');
    }
}
