<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $codigo_orgao
 * @property string|null $nome
 * @property string|null $codigo_orgao_superior
 */
class Orgao extends Model
{
    protected $table = 'orgao';

    protected $primaryKey = 'codigo_orgao';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    /**
     * A hierarquia é auto-relacionada e tem raízes múltiplas: 41 órgãos
     * apontam para si mesmos e 10 não têm superior.
     *
     * @return BelongsTo<Orgao, $this>
     */
    public function superior(): BelongsTo
    {
        return $this->belongsTo(self::class, 'codigo_orgao_superior', 'codigo_orgao');
    }
}
