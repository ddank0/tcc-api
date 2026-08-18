<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Atende ao RF10: /health expõe a última ingestão.
 *
 * @property int $id
 * @property string $competencia
 * @property string|null $arquivo
 * @property int $linhas_lidas
 * @property int $linhas_inseridas
 * @property int $linhas_atualizadas
 * @property int $linhas_rejeitadas
 * @property Carbon|null $iniciado_em
 * @property Carbon|null $finalizado_em
 * @property string $status
 * @property string|null $mensagem_erro
 */
class IngestaoLog extends Model
{
    protected $table = 'ingestao_log';

    public $timestamps = false;

    /** @var array<string, string> */
    protected $casts = [
        'linhas_lidas' => 'integer',
        'linhas_inseridas' => 'integer',
        'linhas_atualizadas' => 'integer',
        'linhas_rejeitadas' => 'integer',
        'iniciado_em' => 'datetime',
        'finalizado_em' => 'datetime',
    ];
}
