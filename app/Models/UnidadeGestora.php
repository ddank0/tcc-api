<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $codigo_ug
 * @property string|null $nome
 * @property string|null $uf
 * @property string|null $municipio
 * @property string|null $codigo_orgao
 */
class UnidadeGestora extends Model
{
    protected $table = 'unidade_gestora';

    protected $primaryKey = 'codigo_ug';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    /**
     * uf e municipio pertencem à UG, não à licitação - a licitação chega ao
     * órgão por aqui.
     *
     * @return BelongsTo<Orgao, $this>
     */
    public function orgao(): BelongsTo
    {
        return $this->belongsTo(Orgao::class, 'codigo_orgao', 'codigo_orgao');
    }
}
