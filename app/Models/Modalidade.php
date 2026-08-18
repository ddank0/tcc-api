<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $codigo
 * @property string $nome
 */
class Modalidade extends Model
{
    protected $table = 'modalidade';

    protected $primaryKey = 'codigo';

    public $timestamps = false;

    public $incrementing = false;
}
