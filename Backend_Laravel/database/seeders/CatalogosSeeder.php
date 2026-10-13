<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Catálogos fijos de MigraSense. Es repetible (upsert): se puede ejecutar varias veces sin duplicar.
 *   php artisan db:seed --database=mysql_admin      (o:  php artisan db:seed --class=CatalogosSeeder --database=mysql_admin)
 */
class CatalogosSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('condicion_medica')->upsert([
            ['id_condicion' => 1, 'codigo' => 'ansiedad_depresion', 'condicion_medica' => 'Ansiedad o depresión'],
            ['id_condicion' => 2, 'codigo' => 'hipertension', 'condicion_medica' => 'Hipertensión'],
            ['id_condicion' => 3, 'codigo' => 'trastornos_sueno', 'condicion_medica' => 'Trastornos del sueño'],
            ['id_condicion' => 4, 'codigo' => 'hormonal', 'condicion_medica' => 'Alteraciones hormonales o ciclo irregular'],
            ['id_condicion' => 5, 'codigo' => 'alergias_sinusitis', 'condicion_medica' => 'Alergias o sinusitis crónica'],
            ['id_condicion' => 6, 'codigo' => 'ninguna', 'condicion_medica' => 'Ninguna'],
            ['id_condicion' => 7, 'codigo' => 'otra', 'condicion_medica' => 'Otra'],
        ], ['id_condicion'], ['codigo', 'condicion_medica']);

        DB::table('consumo_cafeina')->upsert([
            ['id_consumo_cafeina' => 1, 'consume_cafeina' => 'no', 'cantidad_cafeina' => null],
            ['id_consumo_cafeina' => 2, 'consume_cafeina' => 'si', 'cantidad_cafeina' => '1'],
            ['id_consumo_cafeina' => 3, 'consume_cafeina' => 'si', 'cantidad_cafeina' => '2_3'],
            ['id_consumo_cafeina' => 4, 'consume_cafeina' => 'si', 'cantidad_cafeina' => '4_mas'],
        ], ['id_consumo_cafeina'], ['consume_cafeina', 'cantidad_cafeina']);

        DB::table('patron_aparicion')->upsert([
            ['id_patron' => 1, 'patron_aparicion' => 'no', 'orden_aparicion' => null],
            ['id_patron' => 2, 'patron_aparicion' => 'si', 'orden_aparicion' => '2_3_meses'],
            ['id_patron' => 3, 'patron_aparicion' => 'si', 'orden_aparicion' => '4_5_meses'],
            ['id_patron' => 4, 'patron_aparicion' => 'si', 'orden_aparicion' => '6_7_meses'],
            ['id_patron' => 5, 'patron_aparicion' => 'si', 'orden_aparicion' => '8_9_meses'],
            ['id_patron' => 6, 'patron_aparicion' => 'si', 'orden_aparicion' => '9_10_meses'],
        ], ['id_patron'], ['patron_aparicion', 'orden_aparicion']);

        $desenc = [
            'estres' => 'Estrés', 'sueno' => 'Falta de sueño', 'alimentos' => 'Ciertos alimentos',
            'hormonal' => 'Cambios hormonales', 'deshidratacion' => 'Deshidratación',
            'clima' => 'Cambios de clima/presión', 'pantallas' => 'Luces o pantallas',
            'ayuno' => 'Ayuno prolongado', 'alcohol' => 'Alcohol', 'olores' => 'Olores fuertes',
            'ejercicio' => 'Ejercicio intenso', 'ruido' => 'Ruido excesivo',
        ];
        $i = 0;
        $filas = [];
        foreach ($desenc as $codigo => $nombre) {
            $filas[] = ['id_desencadenante' => ++$i, 'codigo' => $codigo, 'nombre' => $nombre, 'activo' => 1];
        }
        DB::table('desencadenante')->upsert($filas, ['id_desencadenante'], ['codigo', 'nombre', 'activo']);

        // Los 16 síntomas que usa el frontend (idéntico a database/08_catalogo_sintomas.sql).
        $sint = [
            ['hiperactividad', 'Hiperactividad', 'Energía inusualmente alta o inquietud: sientes necesidad de moverte o hacer muchas cosas, más de lo normal en ti.'],
            ['hipoactividad', 'Hipoactividad', 'Lentitud o ganas de no moverte: te cuesta arrancar y prefieres quedarte quieto/a más de lo habitual.'],
            ['depresion', 'Depresión', 'Tristeza, desánimo o pérdida de interés que aparece sin una causa clara, horas o días antes del dolor.'],
            ['avidez_alimentos', 'Avidez por determinados alimentos', 'Antojo intenso por comidas concretas (dulces, chocolate, salados). A veces es parte del aviso del cuerpo y no la causa de la migraña.'],
            ['bostezos_repetidos', 'Bostezos repetidos', 'Bostezar muchas veces seguidas sin tener sueño ni aburrimiento.'],
            ['astenia', 'Astenia', 'Debilidad o falta de fuerzas general: el cuerpo se siente pesado aunque no hayas hecho esfuerzo.'],
            ['cervical', 'Dolor o rigidez cervical', 'Tensión, molestia o dificultad para mover el cuello y la nuca.'],
            ['cansancio', 'Cansancio', 'Fatiga o sueño mayor al habitual, incluso después de haber descansado.'],
            ['animo_exaltado', 'Ánimo exaltado', 'Euforia, irritabilidad o humor inusualmente elevado, con cambios bruscos de ánimo.'],
            ['hambre_atipica', 'Hambre atípica', 'Hambre fuera de horario o mucho mayor (o menor) a la que sueles tener.'],
            ['concentracion', 'Dificultad para concentrarse', 'Te cuesta mantener la atención, leer o seguir una conversación; sensación de "mente nublada".'],
            ['luz', 'Sensibilidad a la luz', 'La luz normal (pantallas, focos, sol) te molesta o te incomoda más de lo habitual.'],
            ['ruido', 'Sensibilidad al ruido', 'Los sonidos cotidianos te resultan demasiado fuertes o irritantes.'],
            ['nauseas', 'Náuseas', 'Malestar en el estómago o ganas de vomitar, con o sin vómito.'],
            ['vision', 'Visión borrosa', 'Ves las cosas poco nítidas, con destellos o manchas (puede ser un aviso de aura).'],
            ['bostezos_palidez', 'Bostezos o palidez', 'Bostezos acompañados de una piel más pálida de lo normal en el rostro.'],
        ];
        $filas = array_map(fn ($s) => ['codigo' => $s[0], 'nombre_sintoma' => $s[1], 'descripcion' => $s[2], 'activo' => 1], $sint);
        DB::table('sintoma_prodromico')->upsert($filas, ['codigo'], ['nombre_sintoma', 'descripcion', 'activo']);
        DB::table('sintoma_prodromico')->whereNotIn('codigo', array_column($sint, 0))->update(['activo' => 0]);
    }
}
