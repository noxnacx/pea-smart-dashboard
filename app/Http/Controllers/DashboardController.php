<?php

namespace App\Http\Controllers;

use App\Models\WorkItem;
use App\Models\Issue;
use App\Models\ProgressHistory;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function adminDashboard(Request $request)
    {
        // 🚀 1. รับค่า Time Machine จาก URL (รองรับทั้ง 1m, 3m, 1y และ วันที่แบบเจาะจง)
        $time = $request->input('time', 'now');
        $targetDate = null;

        if ($time === '1m') {
            $targetDate = now()->subMonth()->endOfDay();
        } elseif ($time === '3m') {
            $targetDate = now()->subMonths(3)->endOfDay();
        } elseif ($time === '1y') {
            $targetDate = now()->subYear()->endOfDay();
        } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $time)) { // ✅ เช็คว่าเป็นรูปแบบวันที่ YYYY-MM-DD หรือไม่
            $targetDate = Carbon::parse($time)->endOfDay();
        }

        // ==================================================================================
        // 2. Hierarchy (Strategy -> Plan -> Project ...) | 🚀 CACHED & TIME MACHINE
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

        // ⏳ ถ้าผู้ใช้ดูข้อมูลย้อนหลัง ต้องแปลงค่า Progress ในโครงสร้าง Tree ใหม่
        if ($targetDate) {
            $strategies = unserialize(serialize($strategies)); // Deep Clone กัน Cache พัง

            $histories = ProgressHistory::where('created_at', '<=', $targetDate)
                ->orderBy('created_at', 'desc')
                ->get()
                ->groupBy('work_item_id');

            $adjustTree = function($items) use (&$adjustTree, $targetDate, $histories) {
                return $items->filter(function($item) use ($targetDate) {
                    return clone $item->created_at <= $targetDate; // ตัดงานที่ยังไม่เกิดในอดีตทิ้ง
                })->map(function($item) use (&$adjustTree, $targetDate, $histories) {

                    // แปลงค่า Progress เป็นอดีต
                    if ($histories->has($item->id)) {
                        $item->progress = $histories->get($item->id)->first()->progress;
                    } else {
                        $item->progress = 0;
                    }

                    // คำนวณ Status ในอดีตกลับมา
                    if ($item->progress >= 100) {
                        $item->status = 'completed';
                    } elseif ($item->planned_end_date && clone $targetDate > Carbon::parse($item->planned_end_date)->endOfDay() && $item->progress < 100) {
                        $item->status = 'delayed';
                    } elseif ($item->progress > 0) {
                        $item->status = 'in_progress';
                    } else {
                        $item->status = 'in_active';
                    }

                    if ($item->children) {
                        $item->setRelation('children', $adjustTree($item->children));
                    }
                    return $item;
                })->values();
            };

            $strategies = $adjustTree($strategies);
        }

        // ==================================================================================
        // 3. ข้อมูล Projects & Plans & Issues (รองรับ Time Machine)
        // ==================================================================================
        $projectsQuery = WorkItem::where('type', 'project');
        $plansQuery = WorkItem::where('type', 'plan');
        $issuesQuery = Issue::with(['user', 'workItem'])->orderBy('severity', 'desc');

        if ($targetDate) {
            $projectsQuery->where('created_at', '<=', $targetDate);
            $plansQuery->where('created_at', '<=', $targetDate);
            $issuesQuery->where('created_at', '<=', $targetDate);
        }

        $projects = $projectsQuery->with('projectManager')->get();
        $plansCount = $plansQuery->count();

        // ⏳ จำลอง Progress และ Status กลับไปในอดีตสำหรับ List ของโปรเจกต์
        if ($targetDate && $projects->count() > 0) {
            $projectHistories = ProgressHistory::whereIn('work_item_id', $projects->pluck('id'))
                ->where('created_at', '<=', $targetDate)
                ->orderBy('created_at', 'desc')
                ->get()
                ->groupBy('work_item_id');

            $projects = $projects->map(function($p) use ($targetDate, $projectHistories) {
                if ($projectHistories->has($p->id)) {
                    $p->progress = $projectHistories->get($p->id)->first()->progress;
                } else {
                    $p->progress = 0;
                }

                if ($p->progress >= 100) {
                    $p->status = 'completed';
                } elseif ($p->planned_end_date && clone $targetDate > Carbon::parse($p->planned_end_date)->endOfDay() && $p->progress < 100) {
                    $p->status = 'delayed';
                } elseif ($p->progress > 0) {
                    $p->status = 'in_progress';
                } else {
                    $p->status = 'in_active';
                }
                return $p;
            });
        }

        $allActiveIssues = $issuesQuery->where('status', '!=', 'resolved')->get();

        // ==================================================================================
        // 4. Stats Cards (✅ แก้บัค: ลบคำว่า clone ออก เพราะ sum และ count คืนค่าเป็นตัวเลขธรรมดา)
        // ==================================================================================
        $stats = [
            'total_projects' => $projects->count(),
            'total_plans' => $plansCount,
            'total_budget' => $projects->sum('budget'),
            'active_projects' => $projects->whereNotIn('status', ['completed', 'cancelled'])->count(),
            'avg_progress' => $projects->count() > 0 ? round($projects->avg('progress'), 2) : 0,
            'open_issues' => $allActiveIssues->where('type', 'issue')->count(),
            'active_risks' => $allActiveIssues->where('type', 'risk')->count(),
            'critical_items' => $allActiveIssues->where('severity', 'critical')->count(),
        ];

        // ==================================================================================
        // 5. Project Chart Data (✅ แก้บัค: ลบคำว่า clone ออก)
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
            // ✅ ส่งเป็นคีย์ภาษาอังกฤษ เพื่อให้เวลากดที่กราฟ ระบบจะได้ค้นหาสถานะเจอ
            'labels' => ['completed', 'in_progress', 'delayed', 'in_active', 'cancelled'],
            'colors' => ['#10B981', '#3B82F6', '#EF4444', '#9CA3AF', '#4B5563']
        ];

        // ==================================================================================
        // 6. โครงการที่ต้องจับตา (Watch List)
        // ==================================================================================
        $watchProjects = $projects->filter(function($p) use ($targetDate) {
            if (in_array($p->status, ['completed', 'cancelled'])) return false;
            $isDelayed = $p->status === 'delayed';

            $isEndingSoon = false;
            if ($p->planned_end_date) {
                $compareDate = $targetDate ? clone $targetDate : now();
                $isEndingSoon = Carbon::parse($p->planned_end_date)->lte($compareDate->addDays(30));
            }
            return $isDelayed || $isEndingSoon;
        })
        ->sortBy(function($p) {
            return $p->status === 'delayed' ? 1 : 2;
        })
        ->take(5)
        ->map(function($p) use ($targetDate) {
            $compareDate = $targetDate ? clone $targetDate : now();
            return [
                'id' => $p->id,
                'name' => $p->name,
                'pm_name' => $p->projectManager ? $p->projectManager->name : 'ไม่ระบุ PM',
                'progress' => $p->progress,
                'status' => $p->status,
                'due_date' => $p->planned_end_date ? Carbon::parse($p->planned_end_date)->format('d/m/Y') : '-',
                'is_urgent' => $p->status === 'delayed' || ($p->planned_end_date && Carbon::parse($p->planned_end_date)->lte($compareDate->addDays(7))),
            ];
        })->values();

        return Inertia::render('Dashboard/AdminDashboard', [
            'hierarchy' => $strategies,
            'stats' => $stats,
            'projectChart' => $projectChart,
            'watchProjects' => $watchProjects,
            'activeIssues' => $allActiveIssues
        ]);
    }
}
