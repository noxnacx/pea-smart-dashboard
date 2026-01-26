<?php

namespace App\Exports;

use App\Models\WorkItem;
use App\Models\Issue;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings; // Import

class ExecutiveSummaryExport implements FromArray, WithHeadings, WithStyles, WithCustomCsvSettings // ✨ เพิ่มตรงนี้
{
    public function array(): array
    {
        $totalProjects = WorkItem::where('type', 'project')->count();
        $completedProjects = WorkItem::where('type', 'project')->where('progress', 100)->count();
        $totalBudget = WorkItem::where('type', 'project')->sum('budget');
        $criticalIssues = Issue::where('severity', 'critical')->where('status', '!=', 'resolved')->count();

        return [
            ['จำนวนโครงการทั้งหมด', $totalProjects . ' โครงการ'],
            ['โครงการที่เสร็จสิ้น', $completedProjects . ' โครงการ'],
            ['ความคืบหน้าเฉลี่ย', number_format(WorkItem::where('type', 'project')->avg('progress'), 2) . '%'],
            ['งบประมาณรวม', number_format($totalBudget, 2) . ' บาท'],
            ['ปัญหาวิกฤตที่รอแก้ไข', $criticalIssues . ' รายการ'],
            ['ข้อมูล ณ วันที่', now()->format('d/m/Y H:i')],
        ];
    }

    public function headings(): array
    {
        return ['หัวข้อสรุป', 'ค่าสถิติ'];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 14]],
            'A' => ['font' => ['bold' => true]],
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
