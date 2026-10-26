<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidaFechaRegistro;
use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/registros/episodio/{id}/terminar
 * "fin" es opcional (si no se envía, la migraña termina ahora). Usa la misma regla de fecha
 * que los demás registros (ValidaFechaRegistro) en lugar de repetirla en el controlador.
 */
class TerminarEpisodioRequest extends FormRequest
{
    use ValidaFechaRegistro;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['fin' => $this->reglaFecha()];
    }

    public function attributes(): array
    {
        return ['fin' => 'hora de fin'];
    }
}
