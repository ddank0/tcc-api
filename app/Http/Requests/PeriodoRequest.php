<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Recorte de período para os endpoints analíticos. */
class PeriodoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // AAAAMM. O recorte é por competencia porque 72,6% das
            // data_abertura são nulas no dado real.
            'competencia_de' => ['sometimes', 'string', 'regex:/^\d{6}$/'],
            'competencia_ate' => ['sometimes', 'string', 'regex:/^\d{6}$/'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
