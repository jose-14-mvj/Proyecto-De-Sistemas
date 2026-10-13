<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Ejecuta un script .sql (con DELIMITER, como los de database/) usando una
 * conexión de Laravel. Sirve para importar en Aiven sin cliente mysql.
 *
 *   php artisan migrasense:importar-sql database/aiven/migrasense_aiven.sql
 *   php artisan migrasense:importar-sql archivo.sql --database=mysql_admin --simular
 */
class ImportarSql extends Command
{
    protected $signature = 'migrasense:importar-sql
                            {archivo : Ruta del .sql (relativa a Backend_Laravel o absoluta)}
                            {--database=mysql_admin : Conexión de config/database.php}
                            {--simular : Solo cuenta y muestra las sentencias, no ejecuta nada}';

    protected $description = 'Importa un script SQL con DELIMITER (tablas, vistas, SP, triggers)';

    public function handle(): int
    {
        $ruta = $this->argument('archivo');
        if (! is_file($ruta)) {
            $ruta = base_path($ruta);
        }
        if (! is_file($ruta)) {
            $this->error('No existe el archivo: '.$this->argument('archivo'));

            return self::FAILURE;
        }

        $contenido = file_get_contents($ruta);
        if (str_contains($contenido, 'CAMBIAR_ESTA_CLAVE') && ! $this->option('simular')) {
            $this->error('El archivo todavía tiene CAMBIAR_ESTA_CLAVE: pon una clave real antes de ejecutarlo.');

            return self::FAILURE;
        }

        $sentencias = $this->dividir($contenido);
        $this->info(count($sentencias).' sentencias en '.basename($ruta));

        if ($this->option('simular')) {
            foreach ($sentencias as $i => $s) {
                $this->line(sprintf('%3d) %s', $i + 1, $this->resumen($s)));
            }

            return self::SUCCESS;
        }

        $nombre = $this->option('database');
        $conexion = DB::connection($nombre);
        try {
            $conexion->getPdo();
        } catch (Throwable $e) {
            // La base del .env aún no existe (el script la crea con CREATE DATABASE + USE):
            // se conecta al servidor sin base seleccionada.
            if (! str_contains($e->getMessage(), '[1049]')) {
                $this->error('No se pudo conectar: '.$e->getMessage());

                return self::FAILURE;
            }
            $this->warn('La base "'.$conexion->getConfig('database').'" no existe todavía: conectando sin base.');
            DB::purge($nombre);
            config(["database.connections.$nombre.database" => '']);
            $conexion = DB::connection($nombre);
        }
        $this->line('Conexión: '.$nombre.' → '
            .$conexion->getConfig('host').' / '.($conexion->getConfig('database') ?: '(sin base)'));

        foreach ($sentencias as $i => $s) {
            try {
                $conexion->unprepared($s);
            } catch (Throwable $e) {
                $this->newLine();
                $this->error(sprintf('Falló la sentencia %d de %d: %s', $i + 1, count($sentencias), $this->resumen($s)));
                $this->line($e->getPrevious() ? $e->getPrevious()->getMessage() : $e->getMessage());
                $this->warn('Se detuvo aquí. Lo anterior a esta sentencia SÍ quedó aplicado.');

                return self::FAILURE;
            }
            $this->line(sprintf('%3d) OK  %s', $i + 1, $this->resumen($s)));
        }

        $this->info('Importación terminada sin errores.');

        return self::SUCCESS;
    }

    /**
     * Separa el script respetando DELIMITER. Una sentencia termina cuando una
     * línea acaba en el delimitador actual (así un ';' dentro de un SP no corta).
     *
     * @return string[]
     */
    private function dividir(string $sql): array
    {
        $sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql);   // BOM
        $lineas = preg_split('/\R/', $sql);
        $delim = ';';
        $buffer = [];
        $salida = [];

        foreach ($lineas as $linea) {
            $t = trim($linea);

            if (preg_match('/^DELIMITER\s+(\S+)\s*$/i', $t, $m)) {
                $delim = $m[1];
                continue;
            }
            if ($buffer === [] && ($t === '' || str_starts_with($t, '--') || str_starts_with($t, '#'))) {
                continue;
            }

            $buffer[] = $linea;

            if (str_ends_with($t, $delim)) {
                $texto = rtrim(implode("\n", $buffer));
                $texto = trim(substr($texto, 0, -strlen($delim)));
                if ($texto !== '') {
                    $salida[] = $texto;
                }
                $buffer = [];
            }
        }

        $resto = trim(implode("\n", $buffer));
        if ($resto !== '') {
            $salida[] = $resto;
        }

        return $salida;
    }

    private function resumen(string $s): string
    {
        // Quita comentarios de línea para mostrar el inicio real de la sentencia
        $s = preg_replace('/^\s*--.*$/m', '', $s);
        $s = preg_replace('/\s+/', ' ', trim($s));
        // Nunca mostrar claves en la consola
        $s = preg_replace("/(IDENTIFIED\s+(WITH\s+\S+\s+)?BY\s+)'[^']*'/i", "$1'***'", $s);

        return mb_strimwidth($s, 0, 90, '…');
    }
}
