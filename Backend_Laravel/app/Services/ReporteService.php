<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Reportes clínicos. El PDF lo dibuja el navegador (js/reporte-pdf.js con jsPDF);
 * el backend registra el reporte con sp_generar_reporte (que guarda qué registros
 * y episodios incluye) y devuelve los datos del periodo en el formato del frontend.
 */
class ReporteService
{
    public function __construct(private RegistroService $registros)
    {
    }

    /** Crea el reporte y devuelve su id. $dias = 0 → todo el historial. */
    public function generar(int $idUsuario, int $dias, string $formato): int
    {
        return $this->llamarSp([$idUsuario, $dias, $formato]);
    }

    /** Lista de reportes del usuario, el más reciente primero. */
    public function listar(int $idUsuario, int $limite = 50): array
    {
        return DB::table('reporte as r')
            ->where('r.id_usuario', $idUsuario)
            ->select('r.*')
            ->selectSub(fn ($q) => $q->from('reporte_registro')->whereColumn('id_reporte', 'r.id_reporte')->selectRaw('COUNT(*)'), 'n_registros')
            ->selectSub(fn ($q) => $q->from('reporte_episodio')->whereColumn('id_reporte', 'r.id_reporte')->selectRaw('COUNT(*)'), 'n_episodios')
            ->orderByDesc('r.fecha_generacion')->orderByDesc('r.id_reporte')
            ->limit($limite)->get()
            ->map(fn ($r) => $this->cabecera($r) + [
                'prodromicos' => (int) $r->n_registros,
                'episodios'   => (int) $r->n_episodios,
            ])->all();
    }

    /** Cabecera de un reporte del usuario, o null si no existe o es de otro usuario. */
    public function obtener(int $idUsuario, int $id): ?array
    {
        $r = DB::table('reporte')->where('id_reporte', $id)->where('id_usuario', $idUsuario)->first();
        return $r ? $this->cabecera($r) : null;
    }

    /**
     * Registros incluidos en el reporte (formato de /api/registros), del más antiguo al más reciente,
     * que es el orden en que js/reporte-pdf.js arma la tabla.
     */
    public function registrosDe(int $idUsuario, int $id): array
    {
        $prod = DB::table('reporte_registro')->where('id_reporte', $id)->pluck('id_registro')->map(fn ($v) => (int) $v)->flip();
        $epis = DB::table('reporte_episodio')->where('id_reporte', $id)->pluck('id_episodio')->map(fn ($v) => (int) $v)->flip();
        if ($prod->isEmpty() && $epis->isEmpty()) {
            return [];
        }

        // El id de v_historial no es único entre tipos: se cruza por (tipo, id)
        $todos = $this->registros->listar($idUsuario, 'todos', 0, 100000);
        $incluidos = array_filter($todos, fn ($r) => $r['type'] === 'episodio' ? $epis->has($r['id']) : $prod->has($r['id']));

        usort($incluidos, fn ($a, $b) => strcmp($a['date'], $b['date']) ?: ($a['id'] <=> $b['id']));
        return array_values($incluidos);
    }

    public function eliminar(int $idUsuario, int $id): void
    {
        // reporte_registro y reporte_episodio se borran en cascada (FK ON DELETE CASCADE)
        DB::table('reporte')->where('id_reporte', $id)->where('id_usuario', $idUsuario)->delete();
    }

    /* ---- Llamada al SP con parámetro OUT (separada para poder probarla sin MySQL) ---- */

    protected function llamarSp(array $p): int
    {
        DB::statement('CALL sp_generar_reporte(?,?,?,@id_reporte)', $p);
        return (int) DB::selectOne('SELECT @id_reporte AS id')->id;
    }

    private function cabecera(object $r): array
    {
        $desde = Carbon::parse($r->periodo_inicio);
        $hasta = Carbon::parse($r->periodo_fin);

        return [
            'id'       => (int) $r->id_reporte,
            'etiqueta' => $r->etiqueta,
            'formato'  => $r->formato,
            'desde'    => $desde->toDateString(),
            'hasta'    => $hasta->toDateString(),
            'dias'     => $r->etiqueta === 'Todo el historial' ? 0 : (int) $desde->diffInDays($hasta),
            'fecha'    => Carbon::parse($r->fecha_generacion)->format('Y-m-d\TH:i:s'),
        ];
    }
}
