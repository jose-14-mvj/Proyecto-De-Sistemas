<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OlvideClaveRequest extends FormRequest
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
    }

    public function rules(): array
    {
        return [
            // not_regex: sin saltos de línea (mitiga GHSA-5vg9-5847-vvmq, CRLF en la regla email de Laravel 9)
            'email' => ['required', 'string', 'max:60', 'not_regex:/[\r\n\x00]/', 'email:rfc'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.email'     => 'El correo no es válido.',
            'email.not_regex' => 'El correo no es válido.',
        ];
    }
}
