<?php

namespace App\Exports;

use App\Models\Issue;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings; // Import

class IssueRiskExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithCustomCsvSettings // ✨ เพิ่มตรงนี้
{
    public function collection()
    {
        return Issue::with('workItem')->orderBy('severity')->get();
    }

    public function map($issue): array
    {
        return [
            $issue->title,
            $issue->severity, // Critical, High, etc.
            $issue->status,
            $issue->workItem ? $issue->workItem->name : '-',
            $issue->description,
            $issue->end_date,
        ];
    }

    public function headings(): array
    {
        return ['หัวข้อปัญหา', 'ความรุนแรง', 'สถานะ', 'โครงการที่เกี่ยวข้อง', 'รายละเอียด', 'วันครบกำหนดแก้ไข'];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'C62828']]],
        ];
    }

    // ✨ ฟังก์ชันสำหรับตั้งค่า CSV ให้รองรับภาษาไทย
    public function getCsvSettings(): array
    {
        return [
            'use_bom' => true,
            'output_encoding' => 'UTF-8',
        ];
    }
}
