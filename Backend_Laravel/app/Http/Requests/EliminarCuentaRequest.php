<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;

class EliminarCuentaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'max:128', function ($attr, $valor, $fail) {
                if (! Hash::check((string) $valor, $this->user()->contrasena)) {
                    $fail('La contraseña no es correcta.');
                }
            }],
        ];
    }
}
