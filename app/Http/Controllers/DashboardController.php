<?php

namespace App\Http\Controllers;

use App\Models\WorkItem;
use App\Models\Issue;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function adminDashboard()
    {
        // ==================================================================================
        // 1. Hierarchy (Strategy -> Plan -> Project ...) | 🚀 CACHED (เก็บ 60 นาที)
        // ==================================================================================
        $strategies = Cache::remember('dashboard_hierarchy', 3600, function () {
            $recursiveLoad = function ($q) {
                $q->orderBy('name', 'asc')
                  ->withCount(['issues as issue_count' => function($i) {
                      $i->where('status', '!=', 'resolved');
                  }]);
            };

            $relations = [];
            $depth = 'children';
            for ($i = 0; $i < 10; $i++) {
                $relations[$depth] = $recursiveLoad;
                $depth .= '.children';
            }

            $rawStrategies = WorkItem::where('type', 'strategy')
                ->with($relations)
                ->withCount(['issues as strategy_issue_count' => function($i) {
                     $i->where('status', '!=', 'resolved');
                }])
                ->get();

            return $rawStrategies->sortBy(function($item) {
                return $item->name;
            }, SORT_NATURAL)
            ->map(function ($strategy) {
                $strategy->isOpen = false;
                return $strategy;
            })
            ->values();
        });

        // ==================================================================================
        // 2. ข้อมูล Projects & Plans & Issues (Real-time)
        // ==================================================================================
        $projects = WorkItem::where('type', 'project')->get();
        $plansCount = WorkItem::where('type', 'plan')->count(); // นับแผนงาน

        $allActiveIssues = Issue::where('status', '!=', 'resolved')
            ->with(['user', 'workItem'])
            ->orderBy('severity', 'desc')
            ->get();

        // ==================================================================================
        // 3. Stats Cards
        // ==================================================================================
        $stats = [
            'total_projects' => $projects->count(),
            'total_plans' => $plansCount,
            'avg_progress' => $projects->count() > 0 ? round($projects->avg('progress'), 2) : 0,
            'open_issues' => $allActiveIssues->where('type', 'issue')->count(),
            'active_risks' => $allActiveIssues->where('type', 'risk')->count(),
            'critical_items' => $allActiveIssues->where('severity', 'critical')->count(),
        ];

        // ==================================================================================
        // 4. Project Chart Data
        // ==================================================================================
        $statusCounts = $projects->groupBy('status')->map->count();
        $projectChart = [
            'series' => [
                $statusCounts['completed'] ?? 0,
                $statusCounts['in_progress'] ?? 0,
                $statusCounts['delayed'] ?? 0,
                $statusCounts['in_active'] ?? 0,
                $statusCounts['cancelled'] ?? 0,
            ],
            'labels' => ['เสร็จสิ้น', 'กำลังทำ', 'ล่าช้า', 'รอเริ่ม', 'ยกเลิก'],
            'colors' => ['#10B981', '#3B82F6', '#EF4444', '#9CA3AF', '#4B5563']
        ];

        // ==================================================================================
        // 5. โครงการที่ต้องจับตา (Watch List) | ✅ ปรับปรุงใหม่
        // ==================================================================================
        $watchProjects = WorkItem::where('type', 'project')
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->with('projectManager') // ✅ ดึงข้อมูล PM มาด้วย
            ->where(function($q) {
                 $q->where('status', 'delayed')
                   ->orWhere('planned_end_date', '<=', now()->addDays(30));
            })
            ->orderByRaw("CASE WHEN status = 'delayed' THEN 1 ELSE 2 END")
            ->orderBy('planned_end_date', 'asc')
            ->take(5)
            ->get()
            ->map(function($p) {
                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'pm_name' => $p->projectManager ? $p->projectManager->name : 'ไม่ระบุ PM', // ✅ ส่งชื่อ PM
                    'progress' => $p->progress,
                    'status' => $p->status,
                    'due_date' => $p->planned_end_date ? Carbon::parse($p->planned_end_date)->format('d/m/Y') : '-',
                    'is_urgent' => $p->status === 'delayed' || Carbon::parse($p->planned_end_date)->lte(now()->addDays(7)),
                ];
            });

        return Inertia::render('Dashboard/AdminDashboard', [
            'hierarchy' => $strategies,
            'stats' => $stats,
            'projectChart' => $projectChart,
            'watchProjects' => $watchProjects,
            'activeIssues' => $allActiveIssues
        ]);
    }
}
