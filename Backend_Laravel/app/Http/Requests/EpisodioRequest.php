<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidaFechaRegistro;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EpisodioRequest extends FormRequest
{
    use ValidaFechaRegistro;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'intensidad'     => ['required', 'integer', 'between:1,10'],
            'duracion'       => ['required', Rule::in(['menos_4h', '4_24h', '1_3d', 'mas_3d'])],
            'inicio'         => $this->reglaFecha(),
            'sintomas'       => ['nullable', 'array', 'max:16'],
            'sintomas.*'     => ['string', 'distinct', Rule::exists('sintoma_prodromico', 'codigo')->where('activo', 1)],
            'disparadores'   => ['nullable', 'array', 'max:12'],
            'disparadores.*' => ['string', 'distinct', Rule::exists('desencadenante', 'codigo')->where('activo', 1)],
            'medicacion'     => ['nullable', 'boolean'],
            'medicamento'    => ['nullable', 'string', 'max:80'],
            'notas'          => ['nullable', 'string', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return ['sintomas.*' => 'síntoma', 'disparadores.*' => 'desencadenante', 'inicio' => 'fecha y hora'];
    }
}
