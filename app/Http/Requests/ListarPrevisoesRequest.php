<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListarPrevisoesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // orgao:22000, modalidade:5, global - formato documentado no modelo
            'serie' => ['required', 'string', 'max:50', 'regex:/^(orgao:\w+|modalidade:\d+|global)$/'],
            'alvo' => ['sometimes', 'string', 'in:quantidade,valor'],
        ];
    }
}
