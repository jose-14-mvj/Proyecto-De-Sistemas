<?php

namespace App\Http\Requests\Concerns;

/**
 * "Ningún síntoma" es una opción de la pantalla, no un síntoma del catálogo:
 * se quita de la lista antes de validar (una lista vacía = sin síntomas).
 * "Auras" ya no es un síntoma (Correcciones 4): si una pantalla vieja lo envía,
 * se convierte en la respuesta "¿Es una migraña con aura?" = sí.
 */
trait LimpiaSintomas
{
    protected function prepareForValidation(): void
    {
        $s = $this->input('sintomas');
        if (is_array($s)) {
            $esAura = fn ($x) => is_string($x) && in_array(mb_strtolower(trim($x)), ['auras', 'aura'], true);
            if (count(array_filter($s, $esAura)) && ! $this->filled('aura')) {
                $this->merge(['aura' => 'si']);
            }
            $this->merge(['sintomas' => array_values(array_filter($s,
                fn ($x) => ! $esAura($x) && ! (is_string($x) && in_array(mb_strtolower(trim($x)), ['ninguno', 'ningun', 'ningún síntoma', 'ninguno de estos'], true))))]);
        }
    }
}
