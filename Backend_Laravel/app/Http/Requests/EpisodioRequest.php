<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\LimpiaSintomas;
use App\Http\Requests\Concerns\ValidaFechaRegistro;
use App\Services\RegistroService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EpisodioRequest extends FormRequest
{
    use ValidaFechaRegistro, LimpiaSintomas;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'intensidad'     => ['required', 'integer', 'between:1,10'],
            // duración estimada: solo hace falta si no se envía la hora de fin ni "en curso" (ver withValidator)
            'duracion'       => ['nullable', Rule::in(['menos_4h', '4_24h', '1_3d', 'mas_3d'])],
            'inicio'         => $this->reglaFecha(),
            'fin'            => $this->reglaFecha(),
            'en_curso'       => ['nullable', 'boolean'],
            'aura'           => ['nullable', Rule::in(RegistroService::AURA)],
            'sintomas'       => ['nullable', 'array', 'max:24'],
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
        return ['sintomas.*' => 'síntoma', 'disparadores.*' => 'desencadenante', 'inicio' => 'fecha y hora',
            'fin' => 'hora de fin', 'aura' => 'respuesta sobre el aura'];
    }

    public function withValidator($validator): void
    {
        $this->validarFinEpisodio($validator, true);
    }
}
