<?php

namespace App\Support;

use Carbon\Carbon;

require_once base_path('resources/lib/fpdf/fpdf.php');

/**
 * PDF del resumen semanal que se envía por correo (FPDF, sin dependencias).
 * Recibe los registros en el formato de RegistroService::listar().
 */
class ReporteSemanalPdf extends \FPDF
{
    private const AZUL = [31, 63, 153];
    private const TINTA = [24, 33, 47];
    private const SUAVE = [79, 91, 108];
    private const LINEA = [226, 230, 236];
    private const FONDO = [246, 247, 249];

    private string $pie = '';

    public static function generar(string $nombre, Carbon $desde, Carbon $hasta, array $registros): string
    {
        $pdf = new self('P', 'mm', 'A4');
        $pdf->SetMargins(16, 16, 16);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->SetTitle(self::t('Resumen semanal de MigraSense'), false);
        $pdf->SetAuthor('MigraSense', false);
        $pdf->pie = self::t('MigraSense · Resumen informativo, no es un diagnóstico médico.');
        $pdf->AddPage();
        $pdf->dibujar($nombre, $desde, $hasta, $registros);
        return $pdf->Output('S');
    }

    private function dibujar(string $nombre, Carbon $desde, Carbon $hasta, array $regs): void
    {
        usort($regs, fn ($a, $b) => strcmp($a['date'], $b['date']));
        $episodios = array_values(array_filter($regs, fn ($r) => $r['type'] === 'episodio'));
        $senales = array_values(array_filter($regs, fn ($r) => $r['type'] !== 'episodio'));
        $dolor = $episodios ? round(array_sum(array_column($episodios, 'intensidad')) / count($episodios), 1) : null;
        $dias = count(array_unique(array_map(fn ($r) => substr($r['date'], 0, 10), $regs)));

        // Encabezado
        $this->SetFont('Helvetica', 'B', 20);
        $this->SetTextColor(...self::AZUL);
        $this->Cell(0, 9, 'MigraSense', 0, 1);
        $this->SetFont('Helvetica', 'B', 14);
        $this->SetTextColor(...self::TINTA);
        $this->Cell(0, 8, self::t('Resumen semanal'), 0, 1);
        $this->SetFont('Helvetica', '', 10);
        $this->SetTextColor(...self::SUAVE);
        $this->Cell(0, 6, self::t($nombre.' · del '.self::fecha($desde).' al '.self::fecha($hasta)), 0, 1);
        $this->Ln(4);

        // Cifras de la semana
        $cajas = [
            [(string) count($episodios), count($episodios) === 1 ? 'migraña' : 'migrañas'],
            [(string) count($senales), count($senales) === 1 ? 'señal registrada' : 'señales registradas'],
            [$dolor === null ? '–' : str_replace('.', ',', (string) $dolor).'/10', 'dolor promedio'],
            [(string) $dias, $dias === 1 ? 'día con registros' : 'días con registros'],
        ];
        $ancho = (210 - 32 - 3 * 4) / 4;
        $y = $this->GetY();
        foreach ($cajas as $i => [$num, $txt]) {
            $x = 16 + $i * ($ancho + 4);
            $this->SetFillColor(...self::FONDO);
            $this->SetDrawColor(...self::LINEA);
            $this->Rect($x, $y, $ancho, 22, 'DF');
            $this->SetXY($x, $y + 3);
            $this->SetFont('Helvetica', 'B', 16);
            $this->SetTextColor(...self::TINTA);
            $this->Cell($ancho, 8, self::t($num), 0, 2, 'C');
            $this->SetFont('Helvetica', '', 8.5);
            $this->SetTextColor(...self::SUAVE);
            $this->Cell($ancho, 5, self::t($txt), 0, 0, 'C');
        }
        $this->SetY($y + 28);

        if (! $regs) {
            $this->parrafo('Esta semana no hay registros. Anotar las señales (bostezos, cansancio, sensibilidad a la luz…) y cada migraña ayuda a que la predicción sea más precisa.');
            return;
        }

        // Lo que más se repitió
        $contar = function (array $lista, string $clave) {
            $n = [];
            foreach ($lista as $r) {
                foreach ($r[$clave] ?? [] as $x) {
                    $n[$x] = ($n[$x] ?? 0) + 1;
                }
            }
            arsort($n);
            return array_slice($n, 0, 5, true);
        };
        $sint = $contar($regs, 'sintomas');
        $desen = $contar($episodios, 'disparadores');
        if ($sint || $desen) {
            $this->titulo('Lo que más se repitió');
            if ($sint) {
                $this->parrafo('Síntomas: '.implode(', ', array_map(fn ($k, $v) => "$k ($v)", array_keys($sint), $sint)).'.');
            }
            if ($desen) {
                $this->parrafo('Posibles desencadenantes: '.implode(', ', array_map(fn ($k, $v) => "$k ($v)", array_keys($desen), $desen)).'.');
            }
            $this->Ln(2);
        }

        // Tabla de registros
        $this->titulo('Registros de la semana');
        $col = [30, 34, 20, 94];
        $this->SetFont('Helvetica', 'B', 9);
        $this->SetFillColor(...self::FONDO);
        $this->SetTextColor(...self::SUAVE);
        foreach (['Fecha', 'Tipo', 'Intensidad', 'Detalle'] as $i => $h) {
            $this->Cell($col[$i], 7, self::t($h), 'B', 0, 'L', true);
        }
        $this->Ln();
        $this->SetFont('Helvetica', '', 9);
        $this->SetTextColor(...self::TINTA);
        foreach ($regs as $r) {
            $detalle = [];
            if ($r['sintomas']) {
                $detalle[] = 'Síntomas: '.implode(', ', $r['sintomas']);
            }
            if ($r['type'] === 'episodio') {
                if (! empty($r['duracion'])) {
                    $detalle[] = 'Duración: '.self::duracion($r['duracion']);
                }
                if (! empty($r['disparadores'])) {
                    $detalle[] = 'Desencadenantes: '.implode(', ', $r['disparadores']);
                }
                $detalle[] = $r['medicacion'] ? 'Medicación: '.($r['medicamento'] ?: 'sí') : 'Sin medicación';
            }
            if (! empty($r['notas'])) {
                $detalle[] = 'Notas: '.$r['notas'];
            }
            $fila = [
                Carbon::parse($r['date'])->format('d/m H:i'),
                $r['type'] === 'episodio' ? 'Migraña' : 'Señal previa',
                $r['intensidad'].'/10',
                implode("\n", $detalle) ?: '—',
            ];
            $this->fila($col, $fila);
        }

        $this->Ln(4);
        $this->SetFont('Helvetica', '', 8.5);
        $this->SetTextColor(...self::SUAVE);
        $this->MultiCell(0, 4.5, self::t('Este resumen es informativo y no reemplaza la consulta médica. Si las migrañas son frecuentes, muy intensas o cambian de forma, consulta con un profesional de salud.'));
    }

    private function titulo(string $t): void
    {
        $this->SetFont('Helvetica', 'B', 11);
        $this->SetTextColor(...self::TINTA);
        $this->Cell(0, 7, self::t($t), 0, 1);
    }

    private function parrafo(string $t): void
    {
        $this->SetFont('Helvetica', '', 10);
        $this->SetTextColor(...self::TINTA);
        $this->MultiCell(0, 5.2, self::t($t));
    }

    /** Fila de tabla con alto variable (la columna de detalle puede tener varias líneas) */
    private function fila(array $col, array $datos): void
    {
        $alto = 0;
        foreach ($datos as $i => $d) {
            $alto = max($alto, $this->lineas($col[$i] - 2, self::t($d)) * 4.6 + 3);
        }
        if ($this->GetY() + $alto > $this->PageBreakTrigger) {
            $this->AddPage();
        }
        $x = $this->GetX();
        $y = $this->GetY();
        foreach ($datos as $i => $d) {
            $this->SetXY($x + 1, $y + 1.5);
            $this->MultiCell($col[$i] - 2, 4.6, self::t($d), 0, 'L');
            $x += $col[$i];
        }
        $this->SetDrawColor(...self::LINEA);
        $this->Line(16, $y + $alto, 210 - 16, $y + $alto);
        $this->SetXY(16, $y + $alto);
    }

    /** Cuántas líneas ocupa un texto en un ancho dado (con la fuente actual) */
    private function lineas(float $ancho, string $txt): int
    {
        $n = 0;
        foreach (explode("\n", $txt) as $parrafo) {
            $linea = '';
            $n++;
            foreach (explode(' ', $parrafo) as $palabra) {
                $prueba = $linea === '' ? $palabra : $linea.' '.$palabra;
                if ($this->GetStringWidth($prueba) > $ancho && $linea !== '') {
                    $n++;
                    $linea = $palabra;
                } else {
                    $linea = $prueba;
                }
            }
        }
        return $n;
    }

    public function Footer(): void
    {
        $this->SetY(-12);
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(...self::SUAVE);
        $this->Cell(0, 5, $this->pie, 0, 0, 'L');
        $this->Cell(0, 5, self::t('Página ').$this->PageNo(), 0, 0, 'R');
    }

    private static function duracion(string $d): string
    {
        return ['menos_4h' => 'menos de 4 h', '4_24h' => 'de 4 a 24 h', '1_3d' => 'de 1 a 3 días', 'mas_3d' => 'más de 3 días'][$d] ?? $d;
    }

    private static function fecha(Carbon $f): string
    {
        $m = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
        return $f->day.' '.$m[$f->month - 1].' '.$f->year;
    }

    /** UTF-8 → Windows-1252 (las fuentes base del PDF) */
    private static function t(string $s): string
    {
        $r = @iconv('UTF-8', 'windows-1252//TRANSLIT', $s);
        return $r === false ? mb_convert_encoding($s, 'Windows-1252', 'UTF-8') : $r;
    }
}
