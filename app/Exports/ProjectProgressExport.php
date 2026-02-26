<?php

namespace App\Exports;

use App\Models\WorkItem;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;

class ProjectProgressExport implements FromCollection, WithHeadings, WithStyles, WithCustomCsvSettings
{
    protected $request;

    public function __construct($request) {
        $this->request = $request;
    }

    public function collection()
    {
        $query = WorkItem::whereIn('type', ['project', 'plan'])
            ->select('id', 'type', 'name', 'status', 'progress', 'budget', 'planned_start_date', 'planned_end_date', 'division_id')
            ->orderBy('planned_start_date');

        if ($this->request->division_id) {
            $query->where('division_id', $this->request->division_id);
        }

        if ($this->request->has('filtered_item_ids')) {
            $query->whereIn('id', $this->request->filtered_item_ids);
        }

        return $query->get()->map(function ($item) {
            return [
                $item->type === 'plan' ? 'แผนงาน' : 'โครงการ',
                $item->name,
                strtoupper($item->status),
                $item->progress . '%',
                number_format($item->budget, 2),
                $item->planned_start_date,
                $item->planned_end_date,
            ];
        });
    }

    public function headings(): array { return ['ประเภท', 'ชื่อรายการ', 'สถานะ', 'ความคืบหน้า', 'งบประมาณ (บาท)', 'วันเริ่ม', 'วันสิ้นสุด']; }
    public function styles(Worksheet $sheet) { return [ 1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '4A148C']]] ]; }
    public function getCsvSettings(): array { return [ 'use_bom' => true, 'output_encoding' => 'UTF-8' ]; }
}
