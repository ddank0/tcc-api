<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $cnpj
 * @property string|null $nome
 */
class Fornecedor extends Model
{
    protected $table = 'fornecedor';

    protected $primaryKey = 'cnpj';

    /**
     * O CNPJ é texto, não número. Há CNPJ com e sem zero à esquerda para a
     * mesma empresa no dado real, e há CPF de 11 dígitos no lugar de CNPJ -
     * tratar como int destruiria os dois casos.
     */
    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;
}
