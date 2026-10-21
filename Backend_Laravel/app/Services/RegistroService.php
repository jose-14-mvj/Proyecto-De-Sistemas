<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registros de síntomas (prodrómicos) y episodios de migraña.
 * Lectura: vista v_historial y catálogos. Escritura: procedimientos almacenados (transaccionales).
 * TODAS las operaciones reciben el id del usuario autenticado: nunca se confía en ids del cliente.
 */
class RegistroService
{
    public const TIPOS = ['prodromico', 'episodio'];

    /** Respuestas a "¿Es una migraña con aura?" */
    public const AURA = ['si', 'no', 'no_se'];

    /** Rango de duración que guarda la BD a partir de los minutos exactos */
    public static function duracionDesdeMinutos(int $minutos): string
    {
        return $minutos < 240 ? 'menos_4h' : ($minutos <= 1440 ? '4_24h' : ($minutos <= 4320 ? '1_3d' : 'mas_3d'));
    }

    /** Catálogos activos con el formato del frontend. */
    public function catalogos(): array
    {
        return [
            'sintomas' => DB::table('sintoma_prodromico')->where('activo', 1)->orderBy('id_sintoma')
                ->get(['codigo', 'nombre_sintoma', 'descripcion'])
                ->map(fn ($s) => ['id' => $s->codigo, 'label' => $s->nombre_sintoma, 'info' => $s->descripcion])->all(),
            'desencadenantes' => DB::table('desencadenante')->where('activo', 1)->orderBy('id_desencadenante')
                ->get(['codigo', 'nombre'])
                ->map(fn ($d) => ['id' => $d->codigo, 'label' => $d->nombre])->all(),
        ];
    }

    /**
     * Historial del usuario, más reciente primero. $tipo: todos|prodromico|episodio, $dias: 0 = sin límite.
     * NOTA: el filtro por `tipo` se hace en PHP y no en SQL: `tipo` es una constante de la vista (UNION) y
     * compararla en MySQL/MariaDB puede dar "Illegal mix of collations" según cómo se importó la vista.
     */
    public function listar(int $idUsuario, string $tipo = 'todos', int $dias = 0, int $limite = 500): array
    {
        $q = DB::table('v_historial')->where('id_usuario', $idUsuario);
        if ($dias > 0) {
            $q->where('fecha_hora', '>=', Carbon::now()->subDays($dias)->format('Y-m-d H:i:s'));
        }
        $q->orderByDesc('fecha_hora');

        $filas = in_array($tipo, self::TIPOS, true)
            ? $q->get()->filter(fn ($r) => $r->tipo === $tipo)->take($limite)
            : $q->limit($limite)->get();

        return $this->conExtras($idUsuario, $filas->values()->all());
    }

    public function obtener(int $idUsuario, string $tipo, int $id): ?array
    {
        // El id no es único entre tipos (prodrómico 1 y episodio 1): se filtra el tipo en PHP (ver nota de listar)
        $r = DB::table('v_historial')->where('id_usuario', $idUsuario)->where('id', $id)->get()
            ->first(fn ($x) => $x->tipo === $tipo);

        return $r ? $this->conExtras($idUsuario, [$r])[0] : null;
    }

    /** Migraña que la persona marcó como "Aún en curso" (como mucho una). */
    public function enCurso(int $idUsuario): ?array
    {
        $id = DB::table('episodio_migrana')->where('id_usuario', $idUsuario)->where('en_curso', 1)
            ->orderByDesc('id_episodio')->value('id_episodio');
        return $id ? $this->obtener($idUsuario, 'episodio', (int) $id) : null;
    }

    /**
     * "La migraña terminó": guarda la hora de fin (ahora, o la que indique la persona),
     * quita el "en curso" y calcula la duración. Devuelve false si no estaba en curso.
     */
    public function terminar(int $idUsuario, int $id, ?Carbon $fin = null): bool
    {
        return DB::transaction(function () use ($idUsuario, $id, $fin) {
            $ep = DB::table('episodio_migrana')->where('id_episodio', $id)->where('id_usuario', $idUsuario)
                ->lockForUpdate()->first(['fecha_inicio', 'hora_inicio', 'en_curso']);
            if (! $ep || ! (int) $ep->en_curso) {
                return false;
            }
            $inicio = Carbon::parse($ep->fecha_inicio.' '.$ep->hora_inicio);
            $fin = $fin ?? Carbon::now();
            if ($fin->lt($inicio)) {
                throw ValidationException::withMessages(['fin' => 'La hora de fin no puede ser anterior al inicio de la migraña.']);
            }
            DB::table('episodio_migrana')->where('id_episodio', $id)->where('id_usuario', $idUsuario)->update([
                'en_curso'  => 0,
                'fecha_fin' => $fin->format('Y-m-d H:i:s'),
                'duracion'  => self::duracionDesdeMinutos((int) $inicio->diffInMinutes($fin)),
            ]);
            return true;
        });
    }

    /** Solo puede haber una migraña "en curso" a la vez */
    private function asegurarSinOtraEnCurso(int $idUsuario, ?int $excepto = null): void
    {
        $q = DB::table('episodio_migrana')->where('id_usuario', $idUsuario)->where('en_curso', 1);
        if ($excepto) {
            $q->where('id_episodio', '<>', $excepto);
        }
        if ($q->exists()) {
            throw ValidationException::withMessages(['en_curso' => 'Ya tienes una migraña en curso. Márcala como terminada en Inicio antes de registrar otra en curso.']);
        }
    }

    public function registrarProdromico(int $idUsuario, array $d, Carbon $inicio): int
    {
        return $this->crearProdromico([
            $idUsuario, $d['intensidad'], $d['notas'] ?? null,
            implode(',', $d['sintomas']), $inicio->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * $fin = hora exacta de fin (si se conoce). Con "en_curso" la duración se calcula al terminar;
     * mientras tanto se guarda el rango del tiempo transcurrido (la columna no admite vacío).
     */
    public function registrarEpisodio(int $idUsuario, array $d, Carbon $inicio, ?Carbon $fin = null): int
    {
        $enCurso = ! empty($d['en_curso']);
        if ($enCurso) {
            $this->asegurarSinOtraEnCurso($idUsuario);
            $fin = null;
            $duracion = self::duracionDesdeMinutos(max(0, (int) $inicio->diffInMinutes(Carbon::now(), false)));
        } elseif ($fin) {
            $duracion = self::duracionDesdeMinutos((int) $inicio->diffInMinutes($fin));
        } else {
            $duracion = $d['duracion'];
        }

        $conMedicacion = ! empty($d['medicacion']);
        $id = $this->crearEpisodio([
            $idUsuario, $d['intensidad'], $duracion, $inicio->format('Y-m-d H:i:s'),
            $conMedicacion ? 1 : 0,
            $conMedicacion ? ($d['medicamento'] ?? null) : null,
            $d['notas'] ?? null,
            implode(',', $d['sintomas'] ?? []),
            implode(',', $d['disparadores'] ?? []),
        ]);

        $extras = [];
        if ($enCurso) {
            $extras['en_curso'] = 1;
        }
        if ($fin) {
            $extras['fecha_fin'] = $fin->format('Y-m-d H:i:s');
        }
        if (! empty($d['aura'])) {
            $extras['con_aura'] = $d['aura'];
        }
        if ($extras) {
            DB::table('episodio_migrana')->where('id_episodio', $id)->where('id_usuario', $idUsuario)->update($extras);
        }
        return $id;
    }

    /** Edita intensidad, notas y (episodios) medicamento. El SP verifica que el registro sea del usuario. */
    public function editar(int $idUsuario, string $tipo, int $id, array $d): void
    {
        DB::statement('CALL sp_editar_registro(?,?,?,?,?,?)', [
            $idUsuario, $tipo, $id, $d['intensidad'], $d['notas'] ?? null,
            $tipo === 'episodio' ? ($d['medicamento'] ?? null) : null,
        ]);
    }

    /**
     * Edición completa (desde el formulario de registro). Solo cambia lo que viene en $d;
     * todo en una transacción y siempre filtrando por el dueño del registro.
     * Los códigos ya fueron validados contra el catálogo activo (EditarRegistroRequest).
     */
    public function editarCompleto(int $idUsuario, string $tipo, int $id, array $d, ?Carbon $inicio, ?Carbon $fin = null): void
    {
        DB::transaction(function () use ($idUsuario, $tipo, $id, $d, $inicio, $fin) {
            $notas = array_key_exists('notas', $d) ? (trim((string) $d['notas']) === '' ? null : trim((string) $d['notas'])) : false;

            if ($tipo === 'prodromico') {
                $cambios = ['intensidad' => $d['intensidad']];
                if ($notas !== false) {
                    $cambios['observacion'] = $notas;
                }
                if ($inicio) {
                    $cambios['fecha'] = $inicio->toDateString();
                    $cambios['hora'] = $inicio->format('H:i:s');
                }
                if (! DB::table('registro_sintoma')->where('id_registro', $id)->where('id_usuario', $idUsuario)->exists()) {
                    throw new \RuntimeException('Registro no encontrado');
                }
                DB::table('registro_sintoma')->where('id_registro', $id)->where('id_usuario', $idUsuario)->update($cambios);
                if (array_key_exists('sintomas', $d)) {
                    DB::table('detalle_registro_sintoma')->where('id_registro', $id)->delete();
                    foreach ($this->idsCatalogo('sintoma_prodromico', 'id_sintoma', $d['sintomas'] ?? []) as $s) {
                        DB::table('detalle_registro_sintoma')->insert(['id_registro' => $id, 'id_sintoma' => $s]);
                    }
                }
                return;
            }

            // episodio
            if (! DB::table('episodio_migrana')->where('id_episodio', $id)->where('id_usuario', $idUsuario)->exists()) {
                throw new \RuntimeException('Registro no encontrado');
            }
            $cambios = ['nivel_dolor' => $d['intensidad']];
            if ($notas !== false) {
                $cambios['notas'] = $notas;
            }
            if (! empty($d['duracion'])) {
                $cambios['duracion'] = $d['duracion'];
            }
            if ($inicio) {
                $cambios['fecha_inicio'] = $inicio->toDateString();
                $cambios['hora_inicio'] = $inicio->format('H:i:s');
            }
            // Fin exacto / "Aún en curso" (la duración se recalcula con las horas reales)
            $actual = DB::table('episodio_migrana')->where('id_episodio', $id)->first(['fecha_inicio', 'hora_inicio']);
            $ini = $inicio ?? Carbon::parse($actual->fecha_inicio.' '.$actual->hora_inicio);
            if (! empty($d['en_curso'])) {
                $this->asegurarSinOtraEnCurso($idUsuario, $id);
                $cambios['en_curso'] = 1;
                $cambios['fecha_fin'] = null;
                $cambios['duracion'] = self::duracionDesdeMinutos(max(0, (int) $ini->diffInMinutes(Carbon::now(), false)));
            } elseif ($fin) {
                if ($fin->lt($ini)) {
                    throw ValidationException::withMessages(['fin' => 'La hora de fin no puede ser anterior al inicio.']);
                }
                $cambios['en_curso'] = 0;
                $cambios['fecha_fin'] = $fin->format('Y-m-d H:i:s');
                $cambios['duracion'] = self::duracionDesdeMinutos((int) $ini->diffInMinutes($fin));
            } elseif (array_key_exists('en_curso', $d)) {
                $cambios['en_curso'] = 0;
            }
            if (array_key_exists('aura', $d)) {
                $cambios['con_aura'] = in_array($d['aura'], self::AURA, true) ? $d['aura'] : null;
            }
            DB::table('episodio_migrana')->where('id_episodio', $id)->where('id_usuario', $idUsuario)->update($cambios);

            // Medicación: "medicacion" manda; si no viene, se usa el criterio de siempre (medicamento vacío = no tomó)
            $medicamento = trim((string) ($d['medicamento'] ?? ''));
            $tomo = array_key_exists('medicacion', $d) ? (bool) $d['medicacion'] : (array_key_exists('medicamento', $d) ? $medicamento !== '' : null);
            if ($tomo !== null) {
                DB::table('episodio_medicacion')->where('id_episodio', $id)->delete();
                if ($tomo) {
                    DB::table('episodio_medicacion')->insert(['id_episodio' => $id, 'medicamento' => $medicamento === '' ? null : $medicamento]);
                }
            }

            if (array_key_exists('sintomas', $d)) {
                DB::table('episodio_sintoma')->where('id_episodio', $id)->delete();
                foreach ($this->idsCatalogo('sintoma_prodromico', 'id_sintoma', $d['sintomas'] ?? []) as $s) {
                    DB::table('episodio_sintoma')->insert(['id_episodio' => $id, 'id_sintoma' => $s]);
                }
            }
            if (array_key_exists('disparadores', $d)) {
                DB::table('episodio_desencadenante')->where('id_episodio', $id)->delete();
                foreach ($this->idsCatalogo('desencadenante', 'id_desencadenante', $d['disparadores'] ?? []) as $x) {
                    DB::table('episodio_desencadenante')->insert(['id_episodio' => $id, 'id_desencadenante' => $x]);
                }
            }
        });
    }

    /** ids del catálogo activo para una lista de códigos */
    private function idsCatalogo(string $tabla, string $pk, array $codigos): array
    {
        $codigos = array_values(array_unique(array_filter($codigos, 'is_string')));
        return $codigos ? DB::table($tabla)->whereIn('codigo', $codigos)->where('activo', 1)->pluck($pk)->all() : [];
    }

    public function eliminar(int $idUsuario, string $tipo, int $id): void
    {
        DB::statement('CALL sp_eliminar_registro(?,?,?)', [$idUsuario, $tipo, $id]);
    }

    /* ---- Llamadas a SP con parámetro OUT (separadas para poder probarlas sin MySQL) ---- */

    protected function crearProdromico(array $p): int
    {
        DB::statement('CALL sp_registrar_prodromico(?,?,?,?,?,@id_registro)', $p);
        return (int) DB::selectOne('SELECT @id_registro AS id')->id;
    }

    protected function crearEpisodio(array $p): int
    {
        DB::statement('CALL sp_registrar_episodio(?,?,?,?,?,?,?,?,?,@id_episodio)', $p);
        return (int) DB::selectOne('SELECT @id_episodio AS id')->id;
    }

    /**
     * Agrega a los episodios los datos que la vista v_historial no trae (fin exacto, en curso, aura).
     * Una sola consulta por lista, siempre filtrada por el dueño.
     */
    private function conExtras(int $idUsuario, array $filas): array
    {
        $ids = array_map(fn ($r) => (int) $r->id, array_filter($filas, fn ($r) => $r->tipo === 'episodio'));
        $extras = $ids
            ? DB::table('episodio_migrana')->where('id_usuario', $idUsuario)->whereIn('id_episodio', $ids)
                ->get(['id_episodio', 'fecha_fin', 'en_curso', 'con_aura'])->keyBy('id_episodio')
            : collect();

        return array_map(function ($r) use ($extras) {
            $reg = $this->aFrontend($r);
            if ($r->tipo !== 'episodio') {
                return $reg;
            }
            $x = $extras->get((int) $r->id);
            $inicio = Carbon::parse($r->fecha_hora);
            $enCurso = $x && (int) $x->en_curso === 1;
            $fin = $x && $x->fecha_fin ? Carbon::parse($x->fecha_fin) : null;
            $reg['enCurso'] = $enCurso;
            $reg['fin'] = $fin ? $fin->format('Y-m-d\TH:i:s') : null;
            $reg['aura'] = $x->con_aura ?? null;
            $reg['duracionMinutos'] = $fin ? (int) $inicio->diffInMinutes($fin) : null;
            if ($enCurso) {   // la duración "va creciendo" mientras sigue en curso
                $reg['duracion'] = self::duracionDesdeMinutos(max(0, (int) $inicio->diffInMinutes(Carbon::now(), false)));
            }
            return $reg;
        }, $filas);
    }

    /** Fila de v_historial → formato que ya usa el frontend (js/registro.js / historial.js). */
    private function aFrontend(object $r): array
    {
        $fecha = Carbon::parse($r->fecha_hora)->format('Y-m-d\TH:i:s');   // hora local de Bolivia, sin zona
        $lista = fn (?string $s) => $s === null || $s === '' ? [] : explode(', ', $s);

        $reg = [
            'id'         => (int) $r->id,
            'type'       => $r->tipo,
            'label'      => $r->etiqueta,
            'intensidad' => (int) $r->intensidad,
            'inicio'     => $fecha,
            'date'       => $fecha,
            'sintomas'   => $lista($r->sintomas),
            'notas'      => $r->notas ?? '',
        ];

        if ($r->tipo === 'episodio') {
            $reg += [
                'duracion'     => $r->duracion,
                'disparadores' => $lista($r->desencadenantes),
                'medicacion'   => (int) $r->medicacion > 0,
                'medicamento'  => $r->medicamento ?? '',
            ];
        }

        return $reg;
    }
}
