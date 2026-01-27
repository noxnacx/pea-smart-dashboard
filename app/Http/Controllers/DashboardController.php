<?php

namespace App\Http\Controllers;

use App\Models\WorkItem;
use App\Models\Issue;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function adminDashboard()
    {
        // 1. Hierarchy (Strategy -> Plan)
        $strategies = WorkItem::whereNull('parent_id')
            ->with(['children' => function($q) {
                $q->withCount(['children as project_count'])
                  ->withCount(['issues as issue_count' => function($i) {
                      $i->where('status', '!=', 'resolved');
                  }])
                  ->orderBy('name', 'asc');
            }])
            ->withCount(['issues as strategy_issue_count' => function($i) {
                 $i->where('status', '!=', 'resolved');
            }])
            ->orderBy('name', 'asc')
            ->get()
            ->map(function ($strategy) {
                $strategy->isOpen = false; // ปิด (พับเก็บ) ตาม Default
                return $strategy;
            });

        // 2. ข้อมูล Projects & Issues
        $projects = WorkItem::where('type', 'project')->get();

        $allActiveIssues = Issue::where('status', '!=', 'resolved')
            ->with(['user', 'workItem'])
            ->orderBy('severity', 'desc')
            ->get();

        // 3. Stats Cards
        $stats = [
            'total_projects' => $projects->count(),
            'total_budget' => $projects->sum('budget'),
            'avg_progress' => $projects->count() > 0 ? round($projects->avg('progress'), 2) : 0,
            'open_issues' => $allActiveIssues->where('type', 'issue')->count(),
            'active_risks' => $allActiveIssues->where('type', 'risk')->count(),
            'critical_items' => $allActiveIssues->where('severity', 'critical')->count(),
        ];

        // 4. Project Chart Data (ApexCharts)
        $statusCounts = $projects->groupBy('status')->map->count();
        $projectChart = [
            'series' => [
                $statusCounts['completed'] ?? 0,
                $statusCounts['in_progress'] ?? 0,
                $statusCounts['delayed'] ?? 0,
                $statusCounts['pending'] ?? 0,
                $statusCounts['cancelled'] ?? 0,
            ],
            'labels' => ['เสร็จสิ้น', 'กำลังทำ', 'ล่าช้า', 'รอเริ่ม', 'ยกเลิก'],
            'colors' => ['#10B981', '#3B82F6', '#EF4444', '#9CA3AF', '#4B5563']
        ];

        // 5. โครงการที่ต้องจับตา (Watch List) ✅ เปลี่ยนใหม่ตรงนี้
        // เงื่อนไข: สถานะ "ล่าช้า" หรือ "ยังไม่เสร็จและกำหนดส่งภายใน 30 วัน"
        $watchProjects = WorkItem::where('type', 'project')
            ->whereNotIn('status', ['completed', 'cancelled']) // ตัดงานที่เสร็จ/ยกเลิกออก
            ->where(function($q) {
                 $q->where('status', 'delayed') // เงื่อนไข 1: ล่าช้า
                   ->orWhere('planned_end_date', '<=', now()->addDays(30)); // เงื่อนไข 2: เกือบล่าช้า (ส่งใน 30 วัน)
            })
            ->orderByRaw("CASE WHEN status = 'delayed' THEN 1 ELSE 2 END") // เอาล่าช้าขึ้นก่อน
            ->orderBy('planned_end_date', 'asc') // จากนั้นเรียงตามวันส่ง (ใกล้สุดขึ้นก่อน)
            ->take(5)
            ->get()
            ->map(function($p) {
                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'budget' => $p->budget,
                    'progress' => $p->progress,
                    'status' => $p->status,
                    'due_date' => $p->planned_end_date ? Carbon::parse($p->planned_end_date)->format('d/m/Y') : '-',
                    'is_urgent' => $p->status === 'delayed' || Carbon::parse($p->planned_end_date)->lte(now()->addDays(7)),
                ];
            });

        // 6. Global S-Curve
        $globalSCurve = $this->calculateGlobalSCurve($projects);

        return Inertia::render('Dashboard/AdminDashboard', [
            'hierarchy' => $strategies,
            'stats' => $stats,
            'projectChart' => $projectChart,
            'watchProjects' => $watchProjects, // ✅ ส่งตัวแปรชื่อใหม่ไป
            'sCurveChart' => $globalSCurve,
            'activeIssues' => $allActiveIssues
        ]);
    }

    // (ฟังก์ชัน calculateGlobalSCurve คงเดิม)
    private function calculateGlobalSCurve($projects)
    {
        $months = [];
        $plannedData = [];
        $actualData = [];

        $minDate = $projects->min('planned_start_date');
        $maxDate = $projects->max('planned_end_date');

        if (!$minDate || !$maxDate) return ['categories' => [], 'planned' => [], 'actual' => []];

        $startDate = Carbon::parse($minDate)->startOfMonth();
        $endDate = Carbon::parse($maxDate)->endOfMonth();

        if ($endDate->lt(now())) $endDate = now()->addMonths(3)->endOfMonth();

        $totalBudget = $projects->sum('budget');
        if ($totalBudget <= 0) $totalBudget = 1;

        $currentDate = $startDate->copy();
        while ($currentDate->lte($endDate)) {
            $months[] = $currentDate->translatedFormat('M y');
            $calcDate = $currentDate->copy()->endOfMonth();

            $pvMoney = $projects->sum(function($item) use ($calcDate) {
                if (!$item->planned_start_date || !$item->planned_end_date || $item->budget <= 0) return 0;
                $start = Carbon::parse($item->planned_start_date);
                $end = Carbon::parse($item->planned_end_date);

                if ($calcDate->lt($start)) return 0;
                if ($calcDate->gt($end)) return $item->budget;

                $totalDays = $start->diffInDays($end) + 1;
                $passedDays = $start->diffInDays($calcDate) + 1;
                return $item->budget * ($passedDays / max(1, $totalDays));
            });
            $plannedData[] = round(($pvMoney / $totalBudget) * 100, 2);

            if ($calcDate->lte(now()->endOfMonth())) {
                $evMoney = $projects->sum(fn($item) => $item->budget * ($item->progress / 100));
                $actualData[] = round(($evMoney / $totalBudget) * 100, 2);
            }

            $currentDate->addMonth();
        }

        return [
            'categories' => $months,
            'planned' => $plannedData,
            'actual' => $actualData
        ];
    }
}
