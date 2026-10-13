<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EditarRegistroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'intensidad'  => ['required', 'integer', 'between:1,10'],
            'notas'       => ['nullable', 'string', 'max:500'],
            'medicamento' => ['nullable', 'string', 'max:80'],   // solo se usa en episodios
        ];
    }
}
