<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecordatoriosRequest extends FormRequest
{
    private const HORA = '/^([01]\d|2[0-3]):[0-5]\d$/';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'daily'        => ['sometimes', 'array'],
            'daily.on'     => ['sometimes', 'boolean'],
            'daily.time'   => ['sometimes', 'string', 'regex:'.self::HORA],
            'weekly'       => ['sometimes', 'array'],
            'weekly.on'    => ['sometimes', 'boolean'],
            'weekly.day'   => ['sometimes', 'integer', 'between:0,6'],     // 0 = domingo ... 6 = sábado
            'weekly.time'  => ['sometimes', 'string', 'regex:'.self::HORA],
            'alerta'       => ['sometimes', 'array'],
            'alerta.on'    => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if (! $this->hasAny(['daily', 'weekly', 'alerta'])) {
                $v->errors()->add('daily', 'Envía al menos uno: daily, weekly o alerta.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'daily.time.regex'  => 'La hora debe tener el formato HH:MM (24 horas).',
            'weekly.time.regex' => 'La hora debe tener el formato HH:MM (24 horas).',
            'weekly.day.between' => 'El día debe ir de 0 (domingo) a 6 (sábado).',
        ];
    }
}
