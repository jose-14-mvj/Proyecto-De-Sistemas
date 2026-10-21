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

    /** Hora exacta de fin del episodio (null si no se envió) */
    public function finCarbon(): ?Carbon
    {
        $v = $this->input('fin');
        return $v ? Carbon::parse($v)->setTimezone(config('app.timezone')) : null;
    }

    /**
     * Reglas de un episodio con fin exacto / "Aún en curso" (se llaman desde withValidator).
     * $exigirDuracion: al crear, sin fin ni "en curso" hace falta la duración estimada (compatibilidad).
     */
    protected function validarFinEpisodio($v, bool $exigirDuracion): void
    {
        $v->after(function ($v) use ($exigirDuracion) {
            if ($v->errors()->hasAny(['inicio', 'fin', 'en_curso'])) {
                return;
            }
            $enCurso = filter_var($this->input('en_curso'), FILTER_VALIDATE_BOOLEAN);
            $fin = $this->filled('fin') ? $this->finCarbon() : null;
            if ($enCurso && $fin) {
                $v->errors()->add('fin', 'Una migraña en curso todavía no tiene hora de fin.');
                return;
            }
            if ($exigirDuracion && ! $enCurso && ! $fin && ! $this->filled('duracion')) {
                $v->errors()->add('duracion', 'Indica a qué hora terminó o marca «Aún en curso».');
                return;
            }
            if ($fin && $this->filled('inicio')) {
                $inicio = $this->inicioCarbon();
                if ($fin->lte($inicio)) {
                    $v->errors()->add('fin', 'La hora de fin debe ser posterior a la de inicio.');
                } elseif ($inicio->diffInDays($fin) > 30) {
                    $v->errors()->add('fin', 'Una migraña no puede durar más de 30 días. Revisa las fechas.');
                }
            }
        });
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
