<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListarLicitacoesRequest extends FormRequest
{
    /**
     * Colunas aceitas em ORDER BY.
     *
     * Lista branca, não validação de formato: qualquer coisa vinda do cliente
     * numa cláusula ORDER BY é injeção.
     *
     * @var list<string>
     */
    public const ORDENAVEIS = ['valor', 'competencia', 'data_resultado', 'numero_licitacao'];

    /** Teto de página. Sem ele, o cliente pede 1,7 milhão de linhas num request. */
    public const POR_PAGINA_MAXIMO = 100;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'codigo_orgao' => ['sometimes', 'string', 'max:10'],
            'codigo_modalidade' => ['sometimes', 'integer'],
            'uf' => ['sometimes', 'string', 'size:2', 'regex:/^[A-Za-z]{2}$/', 'in:'.self::UFS],
            // AAAAMM. O recorte de período usa competencia porque 72,6% das
            // data_abertura são nulas no dado real.
            'competencia_de' => ['sometimes', 'string', 'regex:/^\d{6}$/'],
            'competencia_ate' => ['sometimes', 'string', 'regex:/^\d{6}$/'],
            'situacao' => ['sometimes', 'string', 'max:60'],
            'valor_min' => ['sometimes', 'numeric', 'min:0'],
            'valor_max' => ['sometimes', 'numeric', 'min:0'],
            'q' => ['sometimes', 'string', 'min:2', 'max:120'],
            'ordenar' => ['sometimes', 'string', 'in:'.implode(',', self::ORDENAVEIS)],
            'direcao' => ['sometimes', 'string', 'in:asc,desc'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.self::POR_PAGINA_MAXIMO],
        ];
    }

    private const UFS = 'AC,AL,AP,AM,BA,CE,DF,ES,GO,MA,MT,MS,MG,PA,PB,PR,PE,PI,RJ,RN,RS,RO,RR,SC,SP,SE,TO';
}
