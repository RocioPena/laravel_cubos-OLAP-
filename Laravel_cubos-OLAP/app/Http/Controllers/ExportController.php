<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function exportarExcel(Request $request)
    {
        $datos = json_decode($request->input('datos'), true);

        // Colores personalizados desde el modal
        $colorFondoEncabezado = $request->input('colorFondoEncabezado', '#800080');
        $colorTextoEncabezado = $request->input('colorTextoEncabezado', '#FFFFFF');
        $colorFondoContenido = $request->input('colorFondoContenido', '#F5F5DC');
        $colorTextoContenido = $request->input('colorTextoContenido', '#000000');

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Encabezados base
        $baseHeaders = ['CLUES', 'ENTIDAD', 'JURISDICCIÓN', 'MUNICIPIO', 'UNIDAD MÉDICA'];
        $variables = [];

        if (!empty($datos[0]['resultados'])) {
            foreach ($datos[0]['resultados'] as $res) {
                $variables[] = $res['variable'];
            }
        }

        $encabezados = array_merge($baseHeaders, $variables);
        $sheet->fromArray($encabezados, null, 'A1');

        // Aplicar estilo a encabezados
        $colFinal = chr(65 + count($encabezados) - 1); // Asume menos de 26 columnas (A-Z)
        $sheet->getStyle("A1:{$colFinal}1")->applyFromArray([
            'font' => [
                'color' => ['rgb' => ltrim($colorTextoEncabezado, '#')],
                'bold' => true
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => ltrim($colorFondoEncabezado, '#')]
            ]
        ]);

        // Contenido de las filas
        $fila = 2;
        foreach ($datos as $unidad) {
            $base = [
                $unidad['clues'],
                $unidad['unidad']['entidad'] ?? '',
                $unidad['unidad']['jurisdiccion'] ?? '',
                $unidad['unidad']['municipio'] ?? '',
                $unidad['unidad']['unidad_medica'] ?? '',
            ];

            foreach ($variables as $var) {
                $valor = collect($unidad['resultados'])->firstWhere('variable', $var)['total_pacientes'] ?? null;
                $base[] = $valor;
            }

            $sheet->fromArray($base, null, 'A' . $fila);

            // Aplicar color a contenido
            $rango = "A{$fila}:{$colFinal}{$fila}";
            $sheet->getStyle($rango)->applyFromArray([
                'font' => ['color' => ['rgb' => ltrim($colorTextoContenido, '#')]],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => ltrim($colorFondoContenido, '#')]
                ]
            ]);

            $fila++;
        }

        // Preparar respuesta como archivo descargable
        $writer = new Xlsx($spreadsheet);

        return new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            "Content-Type" => "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
            "Content-Disposition" => "attachment; filename=reporte_personalizado.xlsx",
        ]);
    }
}
