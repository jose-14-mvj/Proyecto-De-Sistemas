<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Correcciones 4 — episodios de migraña:
 *  - fecha_fin: fecha y hora EXACTA en que terminó (NULL si no se sabe o si sigue en curso).
 *  - en_curso:  1 mientras la persona sigue con la migraña ("Aún en curso").
 *  - con_aura:  respuesta a "¿Es una migraña con aura?" → 'si' | 'no' | 'no_se' (NULL = no respondió).
 *  - "Auras" deja de ser un síntoma: los episodios viejos que lo tenían pasan a con_aura = 'si'
 *    y el síntoma se desactiva en el catálogo (no se borra, por si algún reporte viejo lo nombra).
 * Idempotente: se puede correr dos veces sin dañar nada. No borra episodios.
 */
return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('episodio_migrana')) {
            return;
        }

        Schema::table('episodio_migrana', function (Blueprint $t) {
            if (! Schema::hasColumn('episodio_migrana', 'fecha_fin')) {
                $t->dateTime('fecha_fin')->nullable()->after('duracion');
            }
            if (! Schema::hasColumn('episodio_migrana', 'en_curso')) {
                $t->boolean('en_curso')->default(false)->after('fecha_fin');
            }
            if (! Schema::hasColumn('episodio_migrana', 'con_aura')) {
                $t->string('con_aura', 6)->nullable()->after('en_curso');
            }
        });

        if (! $this->tieneIndice('episodio_migrana', 'idx_ep_usuario_curso')) {
            Schema::table('episodio_migrana', fn (Blueprint $t) => $t->index(['id_usuario', 'en_curso'], 'idx_ep_usuario_curso'));
        }

        // "Auras" marcado como síntoma → con_aura = 'si' (sin perder el dato)
        $aura = DB::table('sintoma_prodromico')->where('codigo', 'auras')->value('id_sintoma');
        if ($aura) {
            DB::transaction(function () use ($aura) {
                $ids = DB::table('episodio_sintoma')->where('id_sintoma', $aura)->pluck('id_episodio')->all();
                foreach (array_chunk($ids, 500) as $lote) {
                    DB::table('episodio_migrana')->whereIn('id_episodio', $lote)->update(['con_aura' => 'si']);
                }
                DB::table('episodio_sintoma')->where('id_sintoma', $aura)->delete();
                DB::table('sintoma_prodromico')->where('id_sintoma', $aura)->update(['activo' => 0]);
            });
        }
    }

    /** Intencionalmente vacío: las columnas ya pueden tener datos de las personas. */
    public function down()
    {
    }

    private function tieneIndice(string $tabla, string $indice): bool
    {
        if (DB::getDriverName() === 'mysql') {
            return DB::table('information_schema.statistics')
                ->where('table_schema', DB::getDatabaseName())->where('table_name', $tabla)
                ->where('index_name', $indice)->exists();
        }
        if (DB::getDriverName() === 'sqlite') {
            return collect(DB::select("PRAGMA index_list('$tabla')"))->contains(fn ($i) => $i->name === $indice);
        }
        return false;
    }
};
