<?php

namespace App\Exports;

use App\Models\WorkItem;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings; // Import

class ProjectProgressExport implements FromCollection, WithHeadings, WithStyles, WithCustomCsvSettings // ✨ เพิ่มตรงนี้
{
    public function collection()
    {
        // ดึงข้อมูลแผนงานและโครงการทั้งหมด
        return WorkItem::whereIn('type', ['project', 'plan'])
            ->select('type', 'name', 'status', 'progress', 'budget', 'planned_start_date', 'planned_end_date')
            ->orderBy('planned_start_date')
            ->get()
            ->map(function ($item) {
                // แปลงข้อมูลให้สวยงามก่อนลง Excel
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

    public function headings(): array
    {
        return ['ประเภท', 'ชื่อรายการ', 'สถานะ', 'ความคืบหน้า', 'งบประมาณ (บาท)', 'วันเริ่ม', 'วันสิ้นสุด'];
    }

    public function styles(Worksheet $sheet)
    {
        // ทำตัวหนาบรรทัดแรก (Heading)
        return [
            1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '4A148C']]],
        ];
    }

    // ✨ ฟังก์ชันสำหรับตั้งค่า CSV ให้รองรับภาษาไทย
    public function getCsvSettings(): array
    {
        return [
            'use_bom' => true, // สำคัญมาก! ช่วยแก้ปัญหาภาษาต่างดาว
            'output_encoding' => 'UTF-8',
        ];
    }
}
