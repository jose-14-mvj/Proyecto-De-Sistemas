<?php

namespace App\Http\Requests\Concerns;

/**
 * "Ningún síntoma" es una opción de la pantalla, no un síntoma del catálogo:
 * se quita de la lista antes de validar (una lista vacía = sin síntomas).
 */
trait LimpiaSintomas
{
    protected function prepareForValidation(): void
    {
        $s = $this->input('sintomas');
        if (is_array($s)) {
            $this->merge(['sintomas' => array_values(array_filter($s,
                fn ($x) => ! (is_string($x) && in_array(mb_strtolower(trim($x)), ['ninguno', 'ningun', 'ningún síntoma', 'ninguno de estos'], true))))]);
        }
    }
}
