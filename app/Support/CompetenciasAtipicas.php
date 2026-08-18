<?php

namespace App\Support;

/**
 * Competências que não representam atividade normal e precisam de aviso.
 *
 * Sem marcação, um gráfico lê as duas como queda real de atividade - e essa
 * leitura errada é fácil de defender e difícil de desfazer. As causas estão
 * em brain/content/10_Dev/licitacoes-fontes-de-dados.md.
 */
final class CompetenciasAtipicas
{
    /**
     * O PHP converte chave de array numérica em inteiro, então "201812" vira
     * 201812 - daí o tipo aceitar int. A conversão também acontece na busca,
     * o que mantém `isset($m['201812'])` funcionando.
     *
     * @var array<int|string, string>
     */
    public const MOTIVOS = [
        '201812' => 'A fonte publica este ZIP truncado em 8 MiB. Licitações e itens foram recuperados; os participantes desta competência não existem na base.',
        '202404' => 'Última competência da série, publicada incompleta. O volume não é comparável com os meses anteriores.',
    ];

    public static function ehParcial(string $competencia): bool
    {
        return isset(self::MOTIVOS[$competencia]);
    }

    /**
     * Motivos das competências presentes no recorte.
     *
     * @param  list<string>  $competencias
     * @return array<int|string, string>
     */
    public static function motivosDe(array $competencias): array
    {
        return array_intersect_key(self::MOTIVOS, array_flip($competencias));
    }
}
