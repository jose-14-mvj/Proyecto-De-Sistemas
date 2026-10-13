<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ActualizarCuentaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Solo se normalizan los campos que llegaron (merge con null rompería la regla "sometimes")
        if (is_string($this->name)) {
            $this->merge(['name' => trim(preg_replace('/\s+/u', ' ', $this->name))]);
        }
        if (is_string($this->email)) {
            $this->merge(['email' => mb_strtolower(trim($this->email))]);
        }
    }

    public function rules(): array
    {
        $usuario = $this->user();
        $cambiaCorreo = is_string($this->email) && $this->email !== $usuario->correo;

        return [
            'name'  => ['required', 'string', 'min:2', 'max:60', 'regex:/^[\pL ]+$/u'],
            'email' => ['sometimes', 'string', 'not_regex:/[\r\n\x00]/', 'email:rfc', 'max:60',
                        Rule::unique('usuario', 'correo')->ignore($usuario->id_usuario, 'id_usuario')],
            // Cambiar el correo exige confirmar con la contraseña actual
            'current_password' => [Rule::requiredIf($cambiaCorreo), 'string', 'max:128',
                function ($attr, $valor, $fail) use ($usuario, $cambiaCorreo) {
                    if ($cambiaCorreo && ! Hash::check((string) $valor, $usuario->contrasena)) {
                        $fail('La contraseña actual no es correcta.');
                    }
                }],
            'photo' => ['sometimes', 'nullable', 'string', 'max:450000', function ($attr, $valor, $fail) {
                if ($valor === null) {
                    return;
                }
                if (! preg_match('#^data:image/(jpeg|png|webp);base64,#', substr($valor, 0, 30), $m)) {
                    return $fail('La foto debe ser JPG, PNG o WebP.');
                }
                $bin = base64_decode(substr($valor, strlen($m[0])), true);   // estricto
                if ($bin === false || strlen($bin) > 300 * 1024) {
                    return $fail('La foto no es válida o pesa más de 300 KB.');
                }
                $info = @getimagesizefromstring($bin);      // comprueba que de verdad sea una imagen
                if (! $info || ! in_array($info['mime'], ['image/jpeg', 'image/png', 'image/webp'], true)) {
                    $fail('El archivo no es una imagen válida.');
                }
            }],
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex'   => 'El nombre solo puede tener letras y espacios.',
            'email.unique' => 'Ese correo ya está en uso.',
            'email.email'  => 'El correo no es válido.',
            'current_password.required' => 'Para cambiar el correo escribe tu contraseña actual.',
        ];
    }
}
