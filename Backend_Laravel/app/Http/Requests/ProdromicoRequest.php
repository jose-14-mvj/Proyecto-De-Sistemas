<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\LimpiaSintomas;
use App\Http\Requests\Concerns\ValidaFechaRegistro;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProdromicoRequest extends FormRequest
{
    use ValidaFechaRegistro, LimpiaSintomas;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sintomas'      => ['required', 'array', 'min:1', 'max:24'],
            'sintomas.*'    => ['string', 'distinct', Rule::exists('sintoma_prodromico', 'codigo')->where('activo', 1)],
            'intensidad'    => ['required', 'integer', 'between:1,10'],
            'notas'         => ['nullable', 'string', 'max:500'],
            'inicio'        => $this->reglaFecha(),
        ];
    }

    public function attributes(): array
    {
        return ['sintomas.*' => 'síntoma', 'inicio' => 'fecha y hora'];
    }

    public function messages(): array
    {
        return ['sintomas.required' => 'Selecciona al menos un síntoma.', 'sintomas.min' => 'Selecciona al menos un síntoma.'];
    }
}
