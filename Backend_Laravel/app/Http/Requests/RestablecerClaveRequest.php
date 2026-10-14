<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RestablecerClaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->email)) {
            $this->merge(['email' => mb_strtolower(trim($this->email))]);
        }
        if (is_string($this->codigo)) {
            $this->merge(['codigo' => preg_replace('/\D/', '', $this->codigo)]);   // acepta "123 456"
        }
    }

    public function rules(): array
    {
        return [
            'email'    => ['required', 'string', 'max:60', 'not_regex:/[\r\n\x00]/', 'email:rfc'],
            'codigo'   => ['required', 'string', 'regex:/^\d{6}$/'],
            // Misma regla que el registro: mínimo 10, mayúscula, minúscula y número
            'password' => ['required', 'string', 'max:128', 'confirmed', Password::min(10)->mixedCase()->numbers()],
        ];
    }

    public function messages(): array
    {
        return [
            'email.email'     => 'El correo no es válido.',
            'email.not_regex' => 'El correo no es válido.',
            'codigo.regex'    => 'El código tiene 6 números.',
        ];
    }
}
