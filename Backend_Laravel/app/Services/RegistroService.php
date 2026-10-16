<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Registros de síntomas (prodrómicos) y episodios de migraña.
 * Lectura: vista v_historial y catálogos. Escritura: procedimientos almacenados (transaccionales).
 * TODAS las operaciones reciben el id del usuario autenticado: nunca se confía en ids del cliente.
 */
class RegistroService
{
    public const TIPOS = ['prodromico', 'episodio'];

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

        return $filas->map(fn ($r) => $this->aFrontend($r))->values()->all();
    }

    public function obtener(int $idUsuario, string $tipo, int $id): ?array
    {
        // El id no es único entre tipos (prodrómico 1 y episodio 1): se filtra el tipo en PHP (ver nota de listar)
        $r = DB::table('v_historial')->where('id_usuario', $idUsuario)->where('id', $id)->get()
            ->first(fn ($x) => $x->tipo === $tipo);

        return $r ? $this->aFrontend($r) : null;
    }

    public function registrarProdromico(int $idUsuario, array $d, Carbon $inicio): int
    {
        return $this->crearProdromico([
            $idUsuario, $d['intensidad'], $d['notas'] ?? null,
            implode(',', $d['sintomas']), $inicio->format('Y-m-d H:i:s'),
        ]);
    }

    public function registrarEpisodio(int $idUsuario, array $d, Carbon $inicio): int
    {
        $conMedicacion = ! empty($d['medicacion']);
        return $this->crearEpisodio([
            $idUsuario, $d['intensidad'], $d['duracion'], $inicio->format('Y-m-d H:i:s'),
            $conMedicacion ? 1 : 0,
            $conMedicacion ? ($d['medicamento'] ?? null) : null,
            $d['notas'] ?? null,
            implode(',', $d['sintomas'] ?? []),
            implode(',', $d['disparadores'] ?? []),
        ]);
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
    public function editarCompleto(int $idUsuario, string $tipo, int $id, array $d, ?Carbon $inicio): void
    {
        DB::transaction(function () use ($idUsuario, $tipo, $id, $d, $inicio) {
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
