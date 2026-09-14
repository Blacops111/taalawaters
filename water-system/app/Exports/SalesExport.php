<?php

namespace App\Exports;

use App\Models\Sale;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class SalesExport
{
    public function export()
    {
        $sales = Sale::with('product')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Headers
        $sheet->setCellValue('A1', 'Product');
        $sheet->setCellValue('B1', 'Price');
        $sheet->setCellValue('C1', 'Quantity');
        $sheet->setCellValue('D1', 'Total');
        $sheet->setCellValue('E1', 'Date');

        $row = 2;

        foreach ($sales as $sale) {
            $sheet->setCellValue('A'.$row, $sale->product->name ?? 'N/A');
            $sheet->setCellValue('B'.$row, $sale->price);
            $sheet->setCellValue('C'.$row, $sale->quantity_sold);
            $sheet->setCellValue('D'.$row, $sale->total_amount);
            $sheet->setCellValue('E'.$row, $sale->sale_date ?? $sale->created_at);

            $row++;
        }

        $writer = new Xlsx($spreadsheet);

        $fileName = 'sales_report.xlsx';
        $filePath = storage_path('app/public/' . $fileName);

        $writer->save($filePath);

        return $fileName;
    }
}
