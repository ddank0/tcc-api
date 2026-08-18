<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $competencia
 * @property string|null $codigo_orgao
 * @property int|null $codigo_modalidade
 * @property int $quantidade_licitacoes
 * @property numeric-string|null $valor_total
 * @property numeric-string|null $valor_mediano
 */
class SerieMensal extends Model
{
    protected $table = 'serie_mensal';

    public $timestamps = false;

    /** @var array<string, string> */
    protected $casts = [
        'codigo_modalidade' => 'integer',
        'quantidade_licitacoes' => 'integer',
        'valor_total' => 'decimal:4',
        'valor_mediano' => 'decimal:4',
    ];
}
