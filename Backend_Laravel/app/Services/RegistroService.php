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
