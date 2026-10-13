<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CrearNotificacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->mensaje)) {
            $this->merge(['mensaje' => trim($this->mensaje)]);
        }
    }

    public function rules(): array
    {
        return [
            // 'sistema' lo crea solo el servidor
            'tipo'            => ['required', Rule::in(['recordatorio_diario', 'resumen_semanal', 'alerta_riesgo'])],
            'mensaje'         => ['required', 'string', 'min:1', 'max:255'],
            // el recordatorio debe ser del propio usuario
            'id_recordatorio' => ['nullable', 'integer',
                                  Rule::exists('recordatorio', 'id_recordatorio')->where('id_usuario', $this->user()->id_usuario)],
        ];
    }
}
