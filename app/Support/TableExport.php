<?php

namespace App\Support;

use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * One layout for every export (Sales Records, Sales Report, Inventory Report), as PDF or Excel.
 *
 * $d = [
 *   'title'       => 'Inventory Report',
 *   'filename'    => 'treklite-inventory-20261015-0731',      // no extension
 *   'subtitle'    => ['line 1', 'line 2'],                    // small grey lines under the title
 *   'summary'     => [['Units on hand', 450, 'int'], ...],    // [label, value, type]
 *   'columns'     => [['key' => 'product', 'label' => 'Product', 'type' => 'text', 'align' => 'left', 'width' => 24, 'pdf' => true], ...],
 *   'rows'        => [['product' => 'Bagtas', ...], ...],     // raw values, keyed by column key
 *   'muted'       => [3, 7],                                  // row numbers to grey out + strike through (e.g. voided sales)
 *   'totals'      => ['label' => 'Total', 'values' => ['quantity' => 450]],   // or null
 *   'footnote'    => 'text',                                  // or null
 *   'orientation' => 'landscape' | 'portrait',
 * ]
 *
 * Column types: text, int, dash (0 shows as a dash), signed (+/-, 0 as a dash), money, datetime.
 */
class TableExport
{
    private const GREEN = '1E5B31';

    public static function download(array $d, string $format)
    {
        $d += ['subtitle' => [], 'summary' => [], 'muted' => [], 'totals' => null, 'footnote' => null, 'orientation' => 'portrait'];

        return $format === 'pdf' ? self::pdf($d) : self::xlsx($d);
    }

    /** Text as it prints in the PDF (same look as the screen). */
    public static function text(array $col, $v): string
    {
        if ($v === null || $v === '') {
            return '';
        }

        return match ($col['type'] ?? 'text') {
            'money'    => '₱' . number_format((float) $v, 2),
            'int'      => number_format((float) $v),
            'dash'     => (float) $v == 0 ? '–' : number_format((float) $v),
            'signed'   => (float) $v == 0 ? '–' : (((float) $v > 0 ? '+' : '') . number_format((float) $v)),
            'datetime' => $v instanceof \DateTimeInterface ? $v->format('M d, Y h:i A') : (string) $v,
            default    => (string) $v,
        };
    }

    // ------------------------------------------------------------------ PDF

    private static function pdf(array $d)
    {
        @set_time_limit(120);

        // Only the columns meant for paper (the Excel file can carry extra detail columns).
        $d['columns'] = array_values(array_filter($d['columns'], fn ($c) => $c['pdf'] ?? true));

        return Pdf::loadView('exports.table', ['d' => $d])
            ->setPaper('a4', $d['orientation'])
            ->download($d['filename'] . '.pdf');
    }

    // ---------------------------------------------------------------- Excel

    private static function xlsx(array $d)
    {
        @set_time_limit(120);

        $book = new Spreadsheet();
        $book->getProperties()->setCreator('Treklite Outdoor')->setTitle($d['title']);
        $sheet = $book->getActiveSheet();
        $sheet->setTitle(substr(preg_replace('/[\\\\\/?*\[\]:]/', '', $d['title']), 0, 31) ?: 'Report');

        $cols    = $d['columns'];
        $colCnt  = count($cols);
        $lastCol = Coordinate::stringFromColumnIndex($colCnt);
        $letter  = fn (int $i) => Coordinate::stringFromColumnIndex($i + 1);

        $formats = [
            'money'    => '"₱"#,##0.00',
            'int'      => '#,##0',
            'dash'     => '#,##0;-#,##0;"–"',
            'signed'   => '+#,##0;-#,##0;"–"',
            'datetime' => 'mmm dd, yyyy h:mm AM/PM',
        ];

        // Title + small grey lines
        $r = 1;
        $sheet->setCellValueExplicit("A{$r}", 'Treklite Outdoor — ' . $d['title'], DataType::TYPE_STRING);
        $sheet->getStyle("A{$r}")->getFont()->setBold(true)->setSize(14);
        foreach ($d['subtitle'] as $line) {
            $r++;
            $sheet->setCellValueExplicit("A{$r}", (string) $line, DataType::TYPE_STRING);
            $sheet->getStyle("A{$r}")->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF6B7A72'));
        }
        $r++;
        $sheet->setCellValueExplicit("A{$r}", 'Generated ' . now()->format('M d, Y h:i A'), DataType::TYPE_STRING);
        $sheet->getStyle("A{$r}")->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF6B7A72'));

        // Summary block: label (columns A:B merged) and value (column C)
        if (! empty($d['summary']) && $colCnt >= 3) {
            $r++;
            foreach ($d['summary'] as [$label, $value, $type]) {
                $r++;
                $sheet->mergeCells("A{$r}:B{$r}");
                $sheet->setCellValueExplicit("A{$r}", (string) $label, DataType::TYPE_STRING);
                $sheet->getStyle("A{$r}")->getFont()->setBold(true);
                if (is_numeric($value)) {
                    $sheet->setCellValueExplicit("C{$r}", (float) $value, DataType::TYPE_NUMERIC);
                    if (isset($formats[$type])) {
                        $sheet->getStyle("C{$r}")->getNumberFormat()->setFormatCode($formats[$type]);
                    }
                } else {
                    $sheet->setCellValueExplicit("C{$r}", (string) $value, DataType::TYPE_STRING);
                }
                $sheet->getStyle("C{$r}")->getAlignment()->setHorizontal('left');
            }
        }

        // Table header
        $r += 2;
        $headRow = $r;
        foreach ($cols as $i => $col) {
            $sheet->setCellValueExplicit($letter($i) . $r, $col['label'], DataType::TYPE_STRING);
            $sheet->getColumnDimension($letter($i))->setWidth($col['width'] ?? 15);
        }
        $head = $sheet->getStyle("A{$r}:{$lastCol}{$r}");
        $head->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $head->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::GREEN);
        $head->getAlignment()->setVertical('center')->setWrapText(true);
        $sheet->getRowDimension($r)->setRowHeight(22);
        foreach ($cols as $i => $col) {
            $sheet->getStyle($letter($i) . $r)->getAlignment()->setHorizontal($col['align'] ?? 'left');
        }

        // Rows (text is always stored as plain text, so a cell like "=1+1" can never run as a formula)
        $first = $r + 1;
        foreach ($d['rows'] as $n => $row) {
            $r++;
            foreach ($cols as $i => $col) {
                $v = $row[$col['key']] ?? null;
                if ($v === null || $v === '') {
                    continue;
                }
                $type  = $col['type'] ?? 'text';
                $coord = $letter($i) . $r;
                if ($type === 'datetime' && $v instanceof \DateTimeInterface) {
                    $sheet->setCellValueExplicit($coord, Date::PHPToExcel($v), DataType::TYPE_NUMERIC);
                } elseif (isset($formats[$type]) && is_numeric($v)) {
                    $sheet->setCellValueExplicit($coord, (float) $v, DataType::TYPE_NUMERIC);
                } else {
                    $sheet->setCellValueExplicit($coord, (string) $v, DataType::TYPE_STRING);
                }
            }
            if (in_array($n, $d['muted'], true)) {
                $mute = $sheet->getStyle("A{$r}:{$lastCol}{$r}")->getFont();
                $mute->setStrikethrough(true)->getColor()->setRGB('8A948E');
            }
        }
        $last = $r;

        // Column look: alignment + number format over the whole column at once
        if ($last >= $first) {
            foreach ($cols as $i => $col) {
                $range = $letter($i) . "{$first}:" . $letter($i) . $last;
                $sheet->getStyle($range)->getAlignment()->setHorizontal($col['align'] ?? 'left')->setVertical('center');
                $type = $col['type'] ?? 'text';
                if (isset($formats[$type])) {
                    $sheet->getStyle($range)->getNumberFormat()->setFormatCode($formats[$type]);
                }
            }
        } else {
            $r++;
            $sheet->mergeCells("A{$r}:{$lastCol}{$r}");
            $sheet->setCellValueExplicit("A{$r}", 'Nothing to show.', DataType::TYPE_STRING);
            $sheet->getStyle("A{$r}")->getAlignment()->setHorizontal('center');
            $last = $r;
        }

        // Totals row
        if (! empty($d['totals'])) {
            $r++;
            $sheet->setCellValueExplicit("A{$r}", (string) $d['totals']['label'], DataType::TYPE_STRING);
            foreach ($cols as $i => $col) {
                $v = $d['totals']['values'][$col['key']] ?? null;
                if ($v === null || ! is_numeric($v)) {
                    continue;
                }
                $type  = $col['type'] ?? 'int';
                $coord = $letter($i) . $r;
                $sheet->setCellValueExplicit($coord, (float) $v, DataType::TYPE_NUMERIC);
                if (isset($formats[$type])) {
                    $sheet->getStyle($coord)->getNumberFormat()->setFormatCode($formats[$type]);
                }
                $sheet->getStyle($coord)->getAlignment()->setHorizontal($col['align'] ?? 'right');
            }
            $tot = $sheet->getStyle("A{$r}:{$lastCol}{$r}");
            $tot->getFont()->setBold(true);
            $tot->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E6F4EA');
            $last = $r;
        }

        // Thin grey grid around the whole table
        $sheet->getStyle("A{$headRow}:{$lastCol}{$last}")->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('D3DBD6');

        if ($d['footnote']) {
            $r += 2;
            $sheet->setCellValueExplicit("A{$r}", (string) $d['footnote'], DataType::TYPE_STRING);
            $sheet->getStyle("A{$r}")->getFont()->setItalic(true)->getColor()->setRGB('6B7A72');
        }

        // Nice-to-have extras. If any of these is not available, the file is still delivered.
        try {
            if (count($d['rows']) > 0) {
                $sheet->setAutoFilter("A{$headRow}:{$lastCol}" . ($headRow + count($d['rows'])));
            }
            $setup = $sheet->getPageSetup();
            $setup->setPaperSize(PageSetup::PAPERSIZE_A4);
            $setup->setOrientation($d['orientation'] === 'landscape' ? PageSetup::ORIENTATION_LANDSCAPE : PageSetup::ORIENTATION_PORTRAIT);
            $setup->setFitToWidth(1);
            $setup->setFitToHeight(0);
            $setup->setFitToPage(true);
            $setup->setRowsToRepeatAtTopByStartAndEnd($headRow, $headRow);
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->streamDownload(function () use ($book) {
            $writer = new Xlsx($book);
            $writer->setPreCalculateFormulas(false);
            $writer->save('php://output');
            $book->disconnectWorksheets();
        }, $d['filename'] . '.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
