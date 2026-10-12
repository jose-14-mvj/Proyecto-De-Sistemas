<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class CambiarClaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'max:128', function ($attr, $valor, $fail) {
                if (! Hash::check((string) $valor, $this->user()->contrasena)) {
                    $fail('La contraseña actual no es correcta.');
                }
            }],
            'password' => ['required', 'string', 'max:128', 'different:current_password',
                           Password::min(10)->mixedCase()->numbers()],
        ];
    }

    public function messages(): array
    {
        return ['password.different' => 'La nueva contraseña debe ser distinta de la actual.'];
    }
}
