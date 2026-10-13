<?php

namespace App\Http\Requests\Concerns;

use Carbon\Carbon;

/**
 * Convierte la fecha enviada por el cliente a hora de Bolivia.
 * Acepta "2026-10-12T18:30" (datetime-local, hora local) o ISO con zona ("...Z").
 */
trait ValidaFechaRegistro
{
    public function inicioCarbon(): Carbon
    {
        $v = $this->input('inicio');
        return $v ? Carbon::parse($v)->setTimezone(config('app.timezone')) : Carbon::now();
    }

    protected function reglaFecha(): array
    {
        return ['nullable', 'date', function ($attr, $valor, $fail) {
            try {
                $t = Carbon::parse($valor)->setTimezone(config('app.timezone'));
            } catch (\Throwable) {
                return $fail('La fecha no es válida.');
            }
            if ($t->gt(Carbon::now()->addMinutes(10))) {
                $fail('La fecha no puede estar en el futuro.');
            } elseif ($t->lt(Carbon::now()->subYears(10))) {
                $fail('La fecha es demasiado antigua.');
            }
        }];
    }
}
