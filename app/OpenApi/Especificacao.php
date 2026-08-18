<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

/**
 * Metadados e schemas compartilhados da especificação.
 *
 * O contrato é declarado, não inferido: o cliente TypeScript do Angular é
 * gerado deste arquivo, e um contrato que muda sozinho a cada versão da
 * ferramenta geraria um cliente que não corresponde à API.
 */
#[OA\Info(
    version: '1.0.0',
    title: 'API de Licitações',
    description: <<<'TXT'
    Consulta e análise histórica de licitações públicas federais.

    Somente leitura. Os dados vêm do Portal da Transparência, competências
    201301 a 202404 - a fonte foi descontinuada com a transição para a Lei
    14.133/2021.

    As análises leem tabelas materializadas por job em lote; nenhum endpoint
    calcula agregação sobre as tabelas de volume.

    O recorte de período usa `competencia` (AAAAMM) e não a data de abertura,
    porque 72,6% das datas de abertura são nulas na série.
    TXT
)]
#[OA\Server(url: '/api', description: 'Base da API')]
#[OA\Tag(name: 'Licitações', description: 'Consulta de licitações')]
#[OA\Tag(name: 'Análises', description: 'Séries e rankings a partir de tabelas materializadas')]
#[OA\Tag(name: 'Serviço', description: 'Estado do serviço e da ingestão')]
#[OA\Schema(
    schema: 'Modalidade',
    properties: [
        new OA\Property(property: 'codigo', type: 'integer', nullable: true),
        new OA\Property(property: 'nome', type: 'string', nullable: true),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'Orgao',
    properties: [
        new OA\Property(property: 'codigo_orgao', type: 'string', nullable: true),
        new OA\Property(property: 'nome', type: 'string', nullable: true),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'UnidadeGestora',
    properties: [
        new OA\Property(property: 'codigo_ug', type: 'string', nullable: true),
        new OA\Property(property: 'nome', type: 'string', nullable: true),
        new OA\Property(property: 'uf', type: 'string', nullable: true),
        new OA\Property(property: 'municipio', type: 'string', nullable: true),
        new OA\Property(property: 'orgao', ref: '#/components/schemas/Orgao'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'Licitacao',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'numero_licitacao', type: 'string'),
        new OA\Property(property: 'numero_processo', type: 'string', nullable: true),
        new OA\Property(property: 'objeto', type: 'string', nullable: true),
        new OA\Property(property: 'situacao', type: 'string', nullable: true),
        new OA\Property(
            property: 'valor',
            description: 'Decimal como string: valores chegam a trilhões e float perderia precisão.',
            type: 'string',
            nullable: true
        ),
        new OA\Property(property: 'competencia', type: 'string', example: '202401'),
        new OA\Property(
            property: 'data_abertura',
            description: 'Nula em 72,6% dos registros na série completa.',
            type: 'string',
            format: 'date',
            nullable: true
        ),
        new OA\Property(property: 'data_resultado', type: 'string', format: 'date', nullable: true),
        new OA\Property(property: 'modalidade', ref: '#/components/schemas/Modalidade'),
        new OA\Property(property: 'unidade_gestora', ref: '#/components/schemas/UnidadeGestora'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'PaginacaoMeta',
    properties: [
        new OA\Property(property: 'total', type: 'integer'),
        new OA\Property(property: 'per_page', type: 'integer'),
        new OA\Property(property: 'current_page', type: 'integer'),
        new OA\Property(property: 'last_page', type: 'integer'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'PontoDaSerie',
    properties: [
        new OA\Property(property: 'competencia', type: 'string', example: '202401'),
        new OA\Property(property: 'quantidade_licitacoes', type: 'integer'),
        new OA\Property(property: 'valor_total', type: 'string', nullable: true),
        new OA\Property(
            property: 'parcial',
            description: 'true quando a competência é publicada incompleta pela fonte. O motivo vem em meta.competencias_parciais.',
            type: 'boolean'
        ),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'RankingFornecedor',
    properties: [
        new OA\Property(property: 'cnpj', type: 'string'),
        new OA\Property(property: 'nome', type: 'string', nullable: true),
        new OA\Property(property: 'itens_vencidos', type: 'integer'),
        new OA\Property(property: 'licitacoes_distintas', type: 'integer'),
        new OA\Property(property: 'valor_total', type: 'string', nullable: true),
    ],
    type: 'object'
)]
final class Especificacao {}
