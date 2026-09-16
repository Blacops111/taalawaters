<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class V2SalesReportExport
{
    public function __construct(private array $reportData)
    {
    }

    public function build(): Spreadsheet
    {
        $filters = $this->reportData['filters'];
        $summary = $this->reportData['summary'];
        $totalUnitsSold = $this->reportData['totalUnitsSold'];
        $productBreakdown = $this->reportData['productBreakdown'];

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('Taala Crystal')
            ->setTitle('V2 Sales Data Report')
            ->setSubject('Completed V2 sales summary and finished-product breakdown');

        $summarySheet = $spreadsheet->getActiveSheet();
        $summarySheet->setTitle('Summary');
        $summarySheet->mergeCells('A1:D1');
        $summarySheet->setCellValue('A1', 'Taala Crystal - V2 Sales Data Report');
        $summarySheet->mergeCells('A2:D2');
        $summarySheet->setCellValue('A2', 'Uholo Fresh Springs Co. Ltd | P.O. Box 277-40606, Ugunja | +254 724 293 226');
        $summarySheet->setCellValue('A3', 'Date From');
        $summarySheet->setCellValue('B3', $filters['date_from'] ?? 'Beginning');
        $summarySheet->setCellValue('A4', 'Date To');
        $summarySheet->setCellValue('B4', $filters['date_to'] ?? 'Latest');
        $summarySheet->setCellValue('A6', 'Completed Sales');
        $summarySheet->setCellValue('B6', (int) $summary->completed_sales_count);
        $summarySheet->setCellValue('A7', 'Total Sales Value (KES)');
        $summarySheet->setCellValue('B7', (float) $summary->total_sales_value);
        $summarySheet->setCellValue('A8', 'Total Units Sold');
        $summarySheet->setCellValue('B8', (float) $totalUnitsSold);
        $summarySheet->setCellValue('A9', 'Walk-in Sales (KES)');
        $summarySheet->setCellValue('B9', (float) $summary->walk_in_total);
        $summarySheet->setCellValue('A10', 'Business Sales (KES)');
        $summarySheet->setCellValue('B10', (float) $summary->business_total);

        $summarySheet->getStyle('A1:D1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 16,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0B4F78'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $summarySheet->getRowDimension(1)->setRowHeight(26);

        $summarySheet->getStyle('A2:D2')->applyFromArray([
            'font' => [
                'italic' => true,
                'color' => ['rgb' => '4D6B78'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'EAF6F8'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        $summarySheet->getStyle('A3:A10')->getFont()->setBold(true);
        $summarySheet->getStyle('A6:B10')->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'D6E6EA'],
                ],
            ],
        ]);
        $summarySheet->getStyle('B7')->getNumberFormat()->setFormatCode('#,##0.00');
        $summarySheet->getStyle('B8')->getNumberFormat()->setFormatCode('#,##0');
        $summarySheet->getStyle('B9:B10')->getNumberFormat()->setFormatCode('#,##0.00');
        $summarySheet->getColumnDimension('A')->setWidth(28);
        $summarySheet->getColumnDimension('B')->setWidth(20);
        $summarySheet->getColumnDimension('C')->setWidth(18);
        $summarySheet->getColumnDimension('D')->setWidth(18);

        $productSheet = $spreadsheet->createSheet();
        $productSheet->setTitle('Product Breakdown');
        $productSheet->fromArray([
            ['SKU', 'Finished Product', 'Units Sold', 'Sales Value (KES)'],
        ], null, 'A1');

        $row = 2;
        foreach ($productBreakdown as $product) {
            $productSheet->setCellValue('A'.$row, $product->sku);
            $productSheet->setCellValue('B'.$row, $product->name);
            $productSheet->setCellValue('C'.$row, (float) $product->units_sold);
            $productSheet->setCellValue('D'.$row, (float) $product->sales_value);
            $row++;
        }

        $lastRow = max(2, $row - 1);
        $lastColumn = Coordinate::stringFromColumnIndex(4);

        $productSheet->getStyle('A1:'.$lastColumn.'1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '2D8796'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $productSheet->getStyle('C2:C'.$lastRow)->getNumberFormat()->setFormatCode('#,##0');
        $productSheet->getStyle('D2:D'.$lastRow)->getNumberFormat()->setFormatCode('#,##0.00');
        $productSheet->getStyle('A1:D'.$lastRow)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'D6E6EA'],
                ],
            ],
        ]);
        $productSheet->freezePane('A2');
        $productSheet->setAutoFilter('A1:D'.$lastRow);
        $productSheet->getColumnDimension('A')->setWidth(20);
        $productSheet->getColumnDimension('B')->setWidth(36);
        $productSheet->getColumnDimension('C')->setWidth(16);
        $productSheet->getColumnDimension('D')->setWidth(20);

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }
}
