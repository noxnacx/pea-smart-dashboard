<?php

namespace App\Exports;

use App\Models\WorkItem;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class StrategyTreeExport implements FromCollection, WithHeadings, WithMapping
{
    protected $request;

    public function __construct($request) {
        $this->request = $request;
    }

    public function collection()
    {
        $recursiveLoad = function ($q) { $q->orderBy('name', 'asc')->with(['projectManager', 'division']); };
        $relations = [];
        $depth = 'children';
        for ($i = 0; $i < 6; $i++) {
            $relations[$depth] = $recursiveLoad;
            $depth .= '.children';
        }

        $query = WorkItem::whereNull('parent_id')->with(['projectManager', 'division'])->with($relations);

        if ($this->request->strategy_id) {
            $query->where('id', $this->request->strategy_id);
        }

        $strategies = $query->get()->sortBy('name', SORT_NATURAL)->values();

        $flattened = collect();
        $flatten = function ($items, $level) use (&$flatten, &$flattened) {
            foreach ($items as $item) {
                $item->level = $level;
                $flattened->push($item);
                if ($item->children && $item->children->count() > 0) {
                    $flatten($item->children, $level + 1);
                }
            }
        };

        $flatten($strategies, 0);
        return $flattened;
    }

    public function headings(): array { return ['โครงสร้าง (Tree)', 'ชื่อรายการ', 'ประเภท', 'สถานะ', 'ความคืบหน้า (%)', 'ผู้ดูแล (PM)', 'งบประมาณ (บาท)', 'หน่วยงาน (กอง)']; }

    public function map($item): array
    {
        $indent = str_repeat('    ', $item->level);
        $prefix = $item->level > 0 ? '|_ ' : '';
        $typeMap = ['strategy' => 'ยุทธศาสตร์', 'plan' => 'แผนงาน', 'project' => 'โครงการ', 'task' => 'งานย่อย'];
        $statusMap = ['in_active' => 'รอเริ่ม', 'in_progress' => 'กำลังดำเนินการ', 'completed' => 'เสร็จสิ้น', 'delayed' => 'ล่าช้า', 'cancelled' => 'ยกเลิก'];

        return [
            $indent . $prefix . $item->name,
            $item->name,
            $typeMap[$item->type] ?? $item->type,
            $statusMap[$item->status] ?? $item->status,
            $item->progress . '%',
            $item->projectManager ? $item->projectManager->name : '-',
            $item->budget ?? 0,
            $item->division ? $item->division->name : '-'
        ];
    }
}
