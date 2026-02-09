<?php

namespace App\Http\Controllers;

use App\Models\WorkItem;
use App\Models\Issue;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache; // ✅ เพิ่ม Cache Facade

class DashboardController extends Controller
{
    public function adminDashboard()
    {
        // ==================================================================================
        // 1. Hierarchy (Strategy -> Plan -> Project ...) | 🚀 CACHED (เก็บ 60 นาที)
        // ==================================================================================
        $strategies = Cache::remember('dashboard_hierarchy', 3600, function () {

            // Closure สำหรับจัดเรียงและนับ Issue ในทุกระดับชั้น (ใช้ซ้ำได้)
            $recursiveLoad = function ($q) {
                // ✅ แก้ไข: เรียงตามชื่ออย่างเดียว (ตัด order_index ออก)
                $q->orderBy('name', 'asc')
                  ->withCount(['issues as issue_count' => function($i) {
                      $i->where('status', '!=', 'resolved');
                  }]);
            };

            // สร้าง Array เพื่อ Eager Load ลึก 10 ชั้น (Strategy -> Plan -> Project -> Sub-Project ...)
            $relations = [];
            $depth = 'children';
            for ($i = 0; $i < 10; $i++) {
                $relations[$depth] = $recursiveLoad;
                $depth .= '.children';
            }

            // 1. ดึงข้อมูลดิบมาก่อน
            $rawStrategies = WorkItem::where('type', 'strategy')
                ->with($relations) // ✅ โหลด Recursive 10 ชั้นรวดเดียว
                ->withCount(['issues as strategy_issue_count' => function($i) {
                     $i->where('status', '!=', 'resolved');
                }])
                ->get();

            // 2. ✅ เรียงลำดับด้วย PHP (Natural Sort) โดยใช้แค่ "ชื่อ" เท่านั้น
            // แก้ปัญหาเลข 1, 10, 2 และตัดปัญหา order_index ที่อาจผิดเพี้ยนใน DB
            return $rawStrategies->sortBy(function($item) {
                return $item->name;
            }, SORT_NATURAL)
            ->map(function ($strategy) {
                $strategy->isOpen = false; // ปิด (พับเก็บ) ตาม Default
                return $strategy;
            })
            ->values(); // Reset Array Keys สำคัญมาก
        });

        // ==================================================================================
        // 2. ข้อมูล Projects & Issues (Real-time)
        // ==================================================================================
        // ดึงสดเพื่อความแม่นยำของ Stats และ Watch List
        $projects = WorkItem::where('type', 'project')->get();

        $allActiveIssues = Issue::where('status', '!=', 'resolved')
            ->with(['user', 'workItem'])
            ->orderBy('severity', 'desc')
            ->get();

        // ==================================================================================
        // 3. Stats Cards (Real-time)
        // ==================================================================================
        $stats = [
            'total_projects' => $projects->count(),
            'total_budget' => $projects->sum('budget'),
            'avg_progress' => $projects->count() > 0 ? round($projects->avg('progress'), 2) : 0,
            'open_issues' => $allActiveIssues->where('type', 'issue')->count(),
            'active_risks' => $allActiveIssues->where('type', 'risk')->count(),
            'critical_items' => $allActiveIssues->where('severity', 'critical')->count(),
        ];

        // ==================================================================================
        // 4. Project Chart Data (Real-time)
        // ==================================================================================
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

        // ==================================================================================
        // 5. โครงการที่ต้องจับตา (Watch List) | ✅ Logic เดิมที่คุณต้องการ
        // ==================================================================================
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

        // ==================================================================================
        // 6. Global S-Curve | 🚀 CACHED (เก็บ 60 นาที)
        // ==================================================================================
        $globalSCurve = Cache::remember('dashboard_s_curve', 3600, function () {
            // Query ใหม่ภายใน Cache เพื่อความชัวร์ (ไม่ต้องพึ่งตัวแปรข้างนอก)
            $projectsForCurve = WorkItem::where('type', 'project')->get();
            return $this->calculateGlobalSCurve($projectsForCurve);
        });

        return Inertia::render('Dashboard/AdminDashboard', [
            'hierarchy' => $strategies,
            'stats' => $stats,
            'projectChart' => $projectChart,
            'watchProjects' => $watchProjects,
            'sCurveChart' => $globalSCurve,
            'activeIssues' => $allActiveIssues
        ]);
    }

    // ==================================================================================
    // Private Functions (Logic คำนวณ S-Curve คงเดิม 100%)
    // ==================================================================================
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
