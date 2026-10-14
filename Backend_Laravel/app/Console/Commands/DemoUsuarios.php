<?php

namespace App\Console\Commands;

use App\Models\Usuario;
use App\Services\PerfilService;
use App\Services\RegistroService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Crea usuarios de DEMOSTRACIÓN con historial realista para mostrar el aprendizaje del ML.
 *
 *   php artisan migrasense:demo-usuarios                 -> 8 personas con 90 días de historial
 *   php artisan migrasense:demo-usuarios --cantidad=12 --dias=120
 *   php artisan migrasense:demo-usuarios --borrar        -> borra SOLO las cuentas demo
 *
 * Cada persona tiene su propio ritmo (migraña cada 5-15 días) y sus propios síntomas de
 * aviso, que anota entre 4 y 24 h ANTES de la mayoría de sus episodios (como describen los
 * estudios). También hay avisos "falsos" para que no sea demasiado fácil.
 * Correos: demoN@migrasense.demo (no se puede iniciar sesión con ellas: clave aleatoria).
 */
class DemoUsuarios extends Command
{
    protected $signature = 'migrasense:demo-usuarios
                            {--cantidad=8 : Cuántas personas crear (1-30)}
                            {--dias=90 : Días de historial por persona (14-365)}
                            {--borrar : Borra las cuentas demo (y todos sus registros)}';

    protected $description = 'Crea (o borra) usuarios de demostración con historial realista de migraña';

    private const DOMINIO = '@migrasense.demo';
    private const SINTOMAS = ['hiperactividad', 'hipoactividad', 'depresion', 'avidez_alimentos', 'bostezos_repetidos',
        'astenia', 'cervical', 'cansancio', 'animo_exaltado', 'hambre_atipica', 'concentracion', 'luz', 'ruido',
        'nauseas', 'vision', 'bostezos_palidez'];
    private const DISPARADORES = ['estres', 'sueno', 'alimentos', 'hormonal', 'deshidratacion', 'clima', 'pantallas',
        'ayuno', 'alcohol', 'olores', 'ejercicio', 'ruido'];

    public function handle(RegistroService $registros, PerfilService $perfiles): int
    {
        if ($this->option('borrar')) {
            $n = 0;
            Usuario::where('correo', 'like', '%'.self::DOMINIO)->get()->each(function ($u) use (&$n) {
                $u->tokens()->delete();
                $u->delete();                       // ON DELETE CASCADE borra sus registros
                $n++;
            });
            $this->info("Cuentas demo borradas: $n");
            return self::SUCCESS;
        }

        $cantidad = max(1, min(30, (int) $this->option('cantidad')));
        $dias = max(14, min(365, (int) $this->option('dias')));
        $ahora = Carbon::now();
        $totalEp = $totalReg = 0;

        for ($i = 1; $i <= $cantidad; $i++) {
            $n = 1;
            while (Usuario::where('correo', "demo$n".self::DOMINIO)->exists()) {
                $n++;
            }
            $u = Usuario::create([
                'nombres'    => 'Persona Demo '.$this->letras($n),
                'correo'     => "demo$n".self::DOMINIO,
                'contrasena' => Hash::make(Str::random(40)),
            ]);
            $u->refresh();

            $cada = random_int(5, 15);                                       // su ritmo (días)
            $perfiles->guardar($u->id_usuario, $this->perfil($cada));
            $avisos = $this->elegir(self::SINTOMAS, random_int(2, 4));      // SUS síntomas de aviso
            $gatillos = $this->elegir(self::DISPARADORES, random_int(1, 3));
            $antelacion = random_int(6, 20);                                 // horas típicas entre aviso y dolor

            $t = $ahora->copy()->subDays($dias)->addHours(random_int(0, 72));
            while (true) {
                $t->addHours((int) round(($cada + $this->normal() * $cada * 0.25) * 24));
                $t->setTime(random_int(6, 22), random_int(0, 59));
                if ($t->gte($ahora->copy()->subHour())) {
                    break;
                }
                // 75 % de los episodios tienen aviso previo anotado
                if (random_int(1, 100) <= 75) {
                    $h = max(2, $antelacion + (int) round($this->normal() * 4));
                    $registros->registrarProdromico($u->id_usuario, [
                        'intensidad' => random_int(4, 8),
                        'sintomas'   => $this->elegir($avisos, random_int(1, count($avisos))),
                        'notas'      => null,
                    ], $t->copy()->subHours($h));
                    $totalReg++;
                }
                $registros->registrarEpisodio($u->id_usuario, [
                    'intensidad'   => random_int(5, 9),
                    'duracion'     => ['menos_4h', '4_24h', '4_24h', '1_3d'][random_int(0, 3)],
                    'sintomas'     => $this->elegir(['luz', 'ruido', 'nauseas', 'vision'], random_int(1, 2)),
                    'disparadores' => $this->elegir($gatillos, random_int(1, count($gatillos))),
                    'medicacion'   => random_int(0, 1) === 1,
                    'medicamento'  => 'Ibuprofeno',
                    'notas'        => null,
                ], $t->copy());
                $totalEp++;
            }

            // Avisos que NO terminaron en migraña (≈ 1 cada 12 días)
            for ($k = 0, $m = intdiv($dias, 12); $k < $m; $k++) {
                $f = $ahora->copy()->subDays(random_int(1, $dias))->setTime(random_int(7, 21), random_int(0, 59));
                $registros->registrarProdromico($u->id_usuario, [
                    'intensidad' => random_int(2, 5), 'sintomas' => $this->elegir(self::SINTOMAS, 1), 'notas' => null,
                ], $f);
                $totalReg++;
            }
            $this->line("  ✔ {$u->correo}: migraña cada ~$cada días, avisos: ".implode(', ', $avisos));
        }

        $this->info("Listo: $cantidad persona(s), $totalEp episodios y $totalReg registros de síntomas.");
        $this->line('Para que el ML aprenda ya: php artisan migrasense:ml-reentrenar');
        return self::SUCCESS;
    }

    private function perfil(int $cada): array
    {
        $r = fn (array $o) => $o[array_rand($o)];
        $cafe = $r(['si', 'no']);
        return [
            'genero' => $r(['femenino', 'femenino', 'masculino']), 'ocupacion' => $r(['estudiante', 'oficina_virtual', 'fisico_campo']),
            'tiempoMigrana' => $r(['1_3_anios', '4_10_anios', 'mas_10_anios']),
            'frecuencia' => $cada <= 7 ? '1_2_semana' : '1_3_mes',
            'tieneOrden' => 'no', 'ordenAparicion' => null,
            'momentoDia' => $r(['manana', 'tarde', 'noche', 'sin_horario']), 'duracion' => $r(['4_12h', '12_24h', '1_3d']),
            'intensidad' => $r(['moderada', 'intensa']), 'conocesProdromica' => $r(['si', 'no']),
            'sueno' => $r(['menos_5', '5_6', '7_8']), 'estres' => $r(['moderado', 'alto', 'muy_alto']),
            'cafeina' => $cafe, 'porcionesCafeina' => $cafe === 'si' ? $r(['1', '2_3', '4_mas']) : null,
            'condiciones' => [$r(['ninguna', 'ansiedad_depresion', 'trastornos_sueno', 'hormonal'])],
        ];
    }

    private function elegir(array $de, int $n): array
    {
        $copia = $de;
        shuffle($copia);
        return array_slice($copia, 0, max(1, min($n, count($copia))));
    }

    /** Número aleatorio con distribución normal (media 0, desvío 1) */
    private function normal(): float
    {
        $u = max(1e-9, mt_rand() / mt_getrandmax());
        return sqrt(-2 * log($u)) * cos(2 * M_PI * (mt_rand() / mt_getrandmax()));
    }

    /** 1 → A, 2 → B, 27 → AA (el nombre solo admite letras) */
    private function letras(int $n): string
    {
        $s = '';
        for (; $n > 0; $n = intdiv($n - 1, 26)) {
            $s = chr(65 + ($n - 1) % 26).$s;
        }
        return $s;
    }
}
