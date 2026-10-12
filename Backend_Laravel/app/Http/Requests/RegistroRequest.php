<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegistroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    // Normaliza antes de validar (el trigger de la BD también pasa el correo a minúsculas)
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name'  => is_string($this->name)  ? trim(preg_replace('/\s+/u', ' ', $this->name)) : $this->name,
            'email' => is_string($this->email) ? mb_strtolower(trim($this->email)) : $this->email,
        ]);
    }

    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'min:2', 'max:60', 'regex:/^[\pL ]+$/u'],
            'email'    => ['required', 'string', 'email:rfc', 'max:60', 'unique:usuario,correo'],
            // Mínimo 10, mayúscula, minúscula y número. Sin máximo bajo (el viejo tope de 25 se elimina).
            'password' => ['required', 'string', 'max:128', Password::min(10)->mixedCase()->numbers()],
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex'   => 'El nombre solo puede tener letras y espacios.',
            'email.unique' => 'El correo ya está registrado.',
            'email.email'  => 'El correo no es válido.',
        ];
    }
}
