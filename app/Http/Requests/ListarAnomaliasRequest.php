<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListarAnomaliasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'competencia_de' => ['sometimes', 'string', 'regex:/^\d{6}$/'],
            'competencia_ate' => ['sometimes', 'string', 'regex:/^\d{6}$/'],
            'codigo_orgao' => ['sometimes', 'string', 'max:10'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
