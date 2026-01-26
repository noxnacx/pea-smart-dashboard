<?php

namespace App\Http\Controllers;

use App\Models\WorkItem;
use App\Models\Issue;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
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
                $strategy->isOpen = false; // ✅ ปิด Default (พับเก็บ)
                return $strategy;
            });

        // 2. ข้อมูล Projects & Issues
        $projects = WorkItem::where('type', 'project')->get();
        // ดึง Issue พร้อม User และ WorkItem เพื่อแสดงใน Modal
        $allActiveIssues = Issue::where('status', '!=', 'resolved')
            ->with(['user', 'workItem'])
            ->orderBy('severity', 'desc') // เรียงตามความรุนแรง
            ->get();

        $stats = [
            'total_projects' => $projects->count(),
            'avg_progress' => $projects->count() > 0 ? round($projects->avg('progress'), 2) : 0,
            'open_issues' => $allActiveIssues->where('type', 'issue')->count(),
            'active_risks' => $allActiveIssues->where('type', 'risk')->count(),
            'critical_items' => $allActiveIssues->where('severity', 'critical')->count(),
        ];

        // 3. กราฟ 1: สถานะโครงการ
        $statusCounts = $projects->groupBy('status')->map->count();
        $projectChart = [
            'labels' => ['รอดำเนินการ', 'กำลังดำเนินการ', 'เสร็จสิ้น', 'ล่าช้า', 'ยกเลิก'],
            'series' => [
                $statusCounts['pending'] ?? 0,
                $statusCounts['in_progress'] ?? 0,
                $statusCounts['completed'] ?? 0,
                $statusCounts['delayed'] ?? 0,
                $statusCounts['cancelled'] ?? 0,
            ],
            'colors' => ['#9CA3AF', '#FDB913', '#10B981', '#EF4444', '#4B5563']
        ];

        // 4. กราฟ 2: ภาพรวม S-Curve องค์กร (Global S-Curve) ✅ เพิ่มใหม่
        // คำนวณแบบคร่าวๆ จากโครงการทั้งหมด
        $globalSCurve = $this->calculateGlobalSCurve($projects);

        return Inertia::render('Dashboard/AdminDashboard', [
            'hierarchy' => $strategies,
            'stats' => $stats,
            'projectChart' => $projectChart,
            'sCurveChart' => $globalSCurve, // ส่งข้อมูลกราฟ S-Curve ไป
            'activeIssues' => $allActiveIssues // ส่งรายการปัญหาไปแสดงใน Modal
        ]);
    }

    // ฟังก์ชันคำนวณ S-Curve รวม (Aggregate)
    private function calculateGlobalSCurve($projects)
    {
        $months = [];
        $plannedData = [];
        $actualData = [];

        // หาช่วงเวลาของทั้งพอร์ตโฟลิโอ
        $minDate = $projects->min('planned_start_date');
        $maxDate = $projects->max('planned_end_date');

        if (!$minDate || !$maxDate) return ['categories' => [], 'planned' => [], 'actual' => []];

        $startDate = Carbon::parse($minDate)->startOfMonth();
        $endDate = Carbon::parse($maxDate)->endOfMonth();

        // ถ้าจบก่อนปัจจุบัน ให้ยาวมาถึงปัจจุบัน + 3 เดือน
        if ($endDate->lt(now())) $endDate = now()->addMonths(3)->endOfMonth();

        $totalBudget = $projects->sum('budget');
        if ($totalBudget <= 0) $totalBudget = 1;

        $currentDate = $startDate->copy();
        while ($currentDate->lte($endDate)) {
            $months[] = $currentDate->translatedFormat('M y'); // ม.ค. 69
            $calcDate = $currentDate->copy()->endOfMonth();

            // คำนวณ PV รวม
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

            // คำนวณ EV รวม (เฉพาะถึงปัจจุบัน)
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

    public function publicDashboard()
    {
        return Inertia::render('Dashboard/PublicDashboard', [
            'hierarchy' => WorkItem::whereNull('parent_id')->with('children')->get(),
            'stats' => ['total' => WorkItem::count()],
            'canLogin' => \Illuminate\Support\Facades\Route::has('login'),
        ]);
    }
}
