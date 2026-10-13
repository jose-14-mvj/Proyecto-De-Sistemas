<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** POST /api/reportes — dias: 30, 90, 365 o 0 (= todo el historial); formato: pdf (por defecto) o txt */
class ReporteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'dias'    => ['required', 'integer', 'between:0,3650'],
            'formato' => ['nullable', Rule::in(['pdf', 'txt'])],
        ];
    }

    public function attributes(): array
    {
        return ['dias' => 'periodo (días)'];
    }
}
