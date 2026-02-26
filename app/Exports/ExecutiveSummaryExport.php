<?php

namespace App\Exports;

use App\Models\WorkItem;
use App\Models\Issue;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;

class ExecutiveSummaryExport implements FromArray, WithHeadings, WithStyles, WithCustomCsvSettings
{
    protected $request;

    public function __construct($request) {
        $this->request = $request;
    }

    public function array(): array
    {
        $projectIds = $this->request->project_ids ?? [];
        $totalProjects = WorkItem::where('type', 'project')->count();
        $completedProjects = WorkItem::where('type', 'project')->where('progress', 100)->count();
        $totalBudget = WorkItem::where('type', 'project')->sum('budget');
        $criticalIssues = Issue::where('severity', 'critical')->where('status', '!=', 'resolved')->count();

        $rows = [
            ['จำนวนโครงการทั้งหมด', $totalProjects . ' โครงการ'],
            ['โครงการที่เสร็จสิ้น', $completedProjects . ' โครงการ'],
            ['ความคืบหน้าเฉลี่ย', number_format(WorkItem::where('type', 'project')->avg('progress'), 2) . '%'],
            ['งบประมาณรวม', number_format($totalBudget, 2) . ' บาท'],
            ['ปัญหาวิกฤตที่รอแก้ไข', $criticalIssues . ' รายการ'],
            ['ข้อมูล ณ วันที่', now()->format('d/m/Y H:i')],
            ['', ''],
        ];

        $topProjectsQuery = WorkItem::where('type', 'project');
        if (!empty($projectIds)) {
            $topProjectsQuery->whereIn('id', $projectIds);
            $rows[] = ['รายละเอียดโครงการที่เลือก', 'งบประมาณ'];
        } else {
            $topProjectsQuery->orderByDesc('budget')->take(5);
            $rows[] = ['Top 5 โครงการงบสูงสุด', 'งบประมาณ'];
        }

        $topProjects = $topProjectsQuery->get();
        foreach ($topProjects as $pj) {
            $rows[] = [$pj->name, number_format($pj->budget, 2) . ' บาท'];
        }

        return $rows;
    }

    public function headings(): array { return ['หัวข้อสรุป', 'รายละเอียด']; }
    public function styles(Worksheet $sheet) { return [ 1 => ['font' => ['bold' => true, 'size' => 14]], 'A' => ['font' => ['bold' => true]] ]; }
    public function getCsvSettings(): array { return [ 'use_bom' => true, 'output_encoding' => 'UTF-8' ]; }
}
