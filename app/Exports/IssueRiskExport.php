<?php

namespace App\Exports;

use App\Models\Issue;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;

class IssueRiskExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithCustomCsvSettings
{
    protected $request;

    public function __construct($request) {
        $this->request = $request;
    }

    public function collection()
    {
        $query = Issue::with('workItem')->orderBy('severity');

        if ($this->request->type) $query->where('type', $this->request->type);
        if ($this->request->status) $query->where('status', $this->request->status);
        if ($this->request->start_date) $query->whereDate('created_at', '>=', $this->request->start_date);
        if ($this->request->end_date) $query->whereDate('created_at', '<=', $this->request->end_date);

        return $query->get();
    }

    public function map($issue): array
    {
        return [
            $issue->title, $issue->type, $issue->severity, $issue->status,
            $issue->workItem ? $issue->workItem->name : '-',
            $issue->description, $issue->end_date,
        ];
    }

    public function headings(): array { return ['หัวข้อ', 'ประเภท', 'ความรุนแรง', 'สถานะ', 'โครงการที่เกี่ยวข้อง', 'รายละเอียด', 'วันครบกำหนดแก้ไข']; }
    public function styles(Worksheet $sheet) { return [ 1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'C62828']]] ]; }
    public function getCsvSettings(): array { return [ 'use_bom' => true, 'output_encoding' => 'UTF-8' ]; }
}
