<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\LimpiaSintomas;
use App\Http\Requests\Concerns\ValidaFechaRegistro;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * PUT /api/registros/{tipo}/{id}
 * - Edición rápida (como antes): intensidad, notas y medicamento.
 * - Edición completa (formulario de registro): además fecha/hora, síntomas y, en episodios,
 *   duración, desencadenantes y si tomó medicación. Solo cambia lo que se envía.
 */
class EditarRegistroRequest extends FormRequest
{
    use ValidaFechaRegistro, LimpiaSintomas;

    /** Campos que convierten la petición en una edición completa */
    public const CAMPOS_COMPLETOS = ['inicio', 'sintomas', 'duracion', 'disparadores', 'medicacion'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $episodio = $this->route('tipo') === 'episodio';
        $sintoma = ['string', 'distinct', Rule::exists('sintoma_prodromico', 'codigo')->where('activo', 1)];

        $reglas = [
            'intensidad'  => ['required', 'integer', 'between:1,10'],
            'notas'       => ['nullable', 'string', 'max:500'],
            'medicamento' => ['nullable', 'string', 'max:80'],   // solo se usa en episodios
            'inicio'      => array_merge(['sometimes'], $this->reglaFecha()),
            // en un prodrómico debe quedar al menos un síntoma; en un episodio puede quedar vacío
            'sintomas'    => $episodio ? ['sometimes', 'nullable', 'array', 'max:24'] : ['sometimes', 'array', 'min:1', 'max:24'],
            'sintomas.*'  => $sintoma,
        ];
        if ($episodio) {
            $reglas += [
                'duracion'       => ['sometimes', Rule::in(['menos_4h', '4_24h', '1_3d', 'mas_3d'])],
                'disparadores'   => ['sometimes', 'nullable', 'array', 'max:12'],
                'disparadores.*' => ['string', 'distinct', Rule::exists('desencadenante', 'codigo')->where('activo', 1)],
                'medicacion'     => ['sometimes', 'boolean'],
            ];
        }
        return $reglas;
    }

    public function attributes(): array
    {
        return ['sintomas.*' => 'síntoma', 'disparadores.*' => 'desencadenante', 'inicio' => 'fecha y hora'];
    }

    public function messages(): array
    {
        return ['sintomas.min' => 'Selecciona al menos un síntoma.'];
    }

    /** ¿Pide cambiar algo más que intensidad, notas y medicamento? */
    public function esCompleta(): bool
    {
        foreach (self::CAMPOS_COMPLETOS as $c) {
            if ($this->has($c)) {
                return true;
            }
        }
        return false;
    }
}
