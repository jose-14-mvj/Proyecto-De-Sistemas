<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Las 9 preguntas del onboarding (mismas claves y valores que js/onboarding.js). */
class PerfilRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $condiciones = ['ansiedad_depresion', 'hipertension', 'trastornos_sueno', 'hormonal',
                        'alergias_sinusitis', 'ninguna', 'otra'];

        return [
            'genero'            => ['required', Rule::in(['femenino', 'masculino', 'otro'])],
            'ocupacion'         => ['required', Rule::in(['estudiante', 'oficina_virtual', 'fisico_campo',
                                                          'turnos_nocturno', 'hogar', 'sin_ocupacion'])],
            'tiempoMigrana'     => ['required', Rule::in(['menos_1_anio', '1_3_anios', '4_10_anios', 'mas_10_anios'])],
            'frecuencia'        => ['required', Rule::in(['menos_1_mes', '1_3_mes', '1_2_semana',
                                                          '3_mas_semana', 'casi_diario'])],
            'tieneOrden'        => ['required', Rule::in(['si', 'no'])],
            'ordenAparicion'    => ['required_if:tieneOrden,si', 'prohibited_if:tieneOrden,no', 'nullable',
                                    Rule::in(['2_3_meses', '4_5_meses', '6_7_meses', '8_9_meses', '9_10_meses'])],
            'momentoDia'        => ['required', Rule::in(['madrugada', 'manana', 'tarde', 'noche', 'sin_horario'])],
            'duracion'          => ['required', Rule::in(['menos_4h', '4_12h', '12_24h', '1_3d', 'mas_3d'])],
            'intensidad'        => ['required', Rule::in(['leve', 'moderada', 'intensa', 'muy_intensa'])],
            'conocesProdromica' => ['required', Rule::in(['si', 'no', 'no_seguro'])],
            'sueno'             => ['required', Rule::in(['menos_5', '5_6', '7_8', 'mas_8'])],
            'estres'            => ['required', Rule::in(['bajo', 'moderado', 'alto', 'muy_alto'])],
            'cafeina'           => ['required', Rule::in(['si', 'no'])],
            'porcionesCafeina'  => ['required_if:cafeina,si', 'prohibited_if:cafeina,no', 'nullable',
                                    Rule::in(['1', '2_3', '4_mas'])],
            'condiciones'       => ['required', 'array', 'min:1', 'max:7'],
            'condiciones.*'     => ['string', 'distinct', Rule::in($condiciones)],
        ];
    }

    /** "ninguna" no se puede combinar con otras condiciones. */
    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $c = $this->input('condiciones');
            if (is_array($c) && in_array('ninguna', $c, true) && count($c) > 1) {
                $v->errors()->add('condiciones', 'No puedes elegir "Ninguna" junto con otras condiciones.');
            }
        });
    }

    public function attributes(): array
    {
        return ['condiciones.*' => 'condición'];
    }
}
