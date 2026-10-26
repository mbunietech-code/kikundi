<?php

namespace App\Services\Reports;

use App\Support\Settings;
use Dompdf\Dompdf;
use Dompdf\Options as DompdfOptions;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class ReportExporter
{
    public function pdf(Report $report): Response
    {
        $options = new DompdfOptions;
        $options->setDefaultFont('DejaVu Sans');
        $options->setIsRemoteEnabled(false);
        $options->setChroot(resource_path());

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('reports.pdf', ['report' => $report, 'systemName' => Settings::systemName()])->render());

        // Wide tables (e.g. the payers report) need landscape pages.
        $widest = max(array_map(fn ($s) => count($s['columns']), $report->sections) ?: [0]);
        $dompdf->setPaper('A4', $widest > 6 ? 'landscape' : 'portrait');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$report->filename.'.pdf"',
        ]);
    }

    public function xlsx(Report $report): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'ripoti').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);

        $bold = (new Style)->setFontBold();
        $title = (new Style)->setFontBold()->setFontSize(14);
        $muted = (new Style)->setFontColor(Color::rgb(100, 116, 139));
        $header = (new Style)->setFontBold()->setFontColor(Color::WHITE)->setBackgroundColor(Color::rgb(5, 150, 105));
        $money = (new Style)->setFormat('#,##0');
        $moneyBold = (new Style)->setFontBold()->setFormat('#,##0');

        $writer->addRow(Row::fromValues([$report->title], $title));
        $writer->addRow(Row::fromValues([$report->subtitle], $muted));
        $writer->addRow(Row::fromValues([]));

        foreach ($report->summary as $label => $value) {
            $writer->addRow(new Row([Cell::fromValue($label, $muted), Cell::fromValue($value, $bold)]));
        }

        foreach ($report->sections as $section) {
            $writer->addRow(Row::fromValues([]));
            $writer->addRow(Row::fromValues([$section['title']], $bold));
            $writer->addRow(Row::fromValues(array_column($section['columns'], 0), $header));

            foreach ($section['rows'] as $row) {
                $writer->addRow($this->dataRow($section['columns'], $row, null, $money));
            }

            if ($section['totals']) {
                $writer->addRow($this->dataRow($section['columns'], $section['totals'], $bold, $moneyBold));
            }
        }

        $writer->getOptions()->setColumnWidth(24, 1, 2);
        $writer->getOptions()->setColumnWidthForRange(16, 3, 10);
        $writer->close();

        return response()->download($path, $report->filename.'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    /** Money stays numeric in Excel so totals and formulas still work. */
    private function dataRow(array $columns, array $row, ?Style $text, Style $money): Row
    {
        $cells = [];

        foreach ($columns as $key => [, $type]) {
            $value = $row[$key] ?? '';
            $cells[] = Cell::fromValue($value, $type === 'money' && $value !== '' ? $money : $text);
        }

        return new Row($cells);
    }
}
