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
        $targetDate = $this->resolveTargetDate($request->input('time', 'now'));
        $focusId = $request->input('focus_id'); // ✅ รับค่าจุดโฟกัส

        // 1. ดึงโครงสร้าง Hierarchy (รองรับ Time Machine)
        $strategies = $this->getHierarchy($targetDate);

        // ✅ 2. จัดการ Focus Mode (ดึง ID ของงานที่เลือก และลูกหลานทั้งหมด)
        $allowedIds = [];
        $focusedItem = null;

        if ($focusId) {
            $focusedItem = WorkItem::find($focusId);
            if ($focusedItem) {
                $allowedIds = $this->getAllDescendantIds($focusedItem->id);
                $allowedIds[] = $focusedItem->id; // รวมตัวเองเข้าไปด้วย
            }
        }

        // 3. ดึงข้อมูล Projects, Plans และ Issues
        $projectsQuery = WorkItem::where('type', 'project')->with('projectManager');
        $plansQuery = WorkItem::where('type', 'plan');
        $issuesQuery = Issue::with(['user', 'workItem'])->orderBy('severity', 'desc');

        // ✅ 4. Apply Focus Filter (ถ้ามีการเลือกโฟกัส)
        if (!empty($allowedIds)) {
            $projectsQuery->whereIn('id', $allowedIds);
            $plansQuery->whereIn('id', $allowedIds);
            $issuesQuery->whereIn('work_item_id', $allowedIds);
        }

        // 5. Apply Time Machine Filter
        if ($targetDate) {
            $projectsQuery->where('created_at', '<=', $targetDate);
            $plansQuery->where('created_at', '<=', $targetDate);
            $issuesQuery->where('created_at', '<=', $targetDate);
        }

        $projects = $projectsQuery->get();
        $plansCount = $plansQuery->count();
        $allActiveIssues = $issuesQuery->where('status', '!=', 'resolved')->get();

        // 6. จำลองข้อมูลโครงการตามเวลา (Time Machine)
        if ($targetDate && $projects->isNotEmpty()) {
            $projects = $this->applyTimeMachineToProjects($projects, $targetDate);
        }

        // 7. สรุปสถิติ (Stats Cards)
        $activeProjectsForAvg = $projects->where('status', '!=', 'cancelled');
        $stats = [
            'total_projects' => $projects->count(),
            'total_plans' => $plansCount,
            'total_budget' => $projects->sum('budget'),
            'active_projects' => $projects->whereNotIn('status', ['completed', 'cancelled'])->count(),
            'avg_progress' => $activeProjectsForAvg->count() > 0 ? round($activeProjectsForAvg->avg('progress'), 2) : 0,
            'open_issues' => $allActiveIssues->where('type', 'issue')->count(),
            'active_risks' => $allActiveIssues->where('type', 'risk')->count(),
            'critical_items' => $allActiveIssues->where('severity', 'critical')->count(),
        ];

        // 8. ข้อมูลกราฟโดนัท
        $statusCounts = $projects->groupBy('status')->map->count();
        $projectChart = [
            'series' => [
                $statusCounts['completed'] ?? 0,
                $statusCounts['in_progress'] ?? 0,
                $statusCounts['delayed'] ?? 0,
                $statusCounts['in_active'] ?? 0,
                $statusCounts['cancelled'] ?? 0,
            ],
            'labels' => ['completed', 'in_progress', 'delayed', 'in_active', 'cancelled'],
            'colors' => ['#10B981', '#3B82F6', '#EF4444', '#9CA3AF', '#4B5563']
        ];

        // 9. โครงการที่ต้องจับตา
        $watchProjects = $this->getWatchProjects($projects, $targetDate);

        return Inertia::render('Dashboard/AdminDashboard', [
            'hierarchy' => $strategies,
            'stats' => $stats,
            'projectChart' => $projectChart,
            'watchProjects' => $watchProjects,
            'activeIssues' => $allActiveIssues,
            'focusedItem' => $focusedItem ? ['id' => $focusedItem->id, 'name' => $focusedItem->name, 'type' => $focusedItem->type] : null // ✅ ส่งข้อมูลจุดโฟกัสไปให้ Vue
        ]);
    }

    /* =========================================================================
       🛠️ Helper Methods
       ========================================================================= */

    /**
     * ค้นหา ID ของงานย่อยทั้งหมดแบบไม่จำกัดชั้น (Infinite Layers)
     */
    private function getAllDescendantIds($parentId)
    {
        $ids = [];
        $itemsToCheck = [$parentId];

        while (!empty($itemsToCheck)) {
            $currentId = array_shift($itemsToCheck);
            $childrenIds = WorkItem::where('parent_id', $currentId)->pluck('id')->toArray();

            if (!empty($childrenIds)) {
                $ids = array_merge($ids, $childrenIds);
                $itemsToCheck = array_merge($itemsToCheck, $childrenIds);
            }
        }
        return $ids;
    }

    private function resolveTargetDate(string $time): ?Carbon
    {
        return match ($time) {
            '1m' => now()->subMonth()->endOfDay(),
            '3m' => now()->subMonths(3)->endOfDay(),
            '1y' => now()->subYear()->endOfDay(),
            default => preg_match('/^\d{4}-\d{2}-\d{2}$/', $time) ? Carbon::parse($time)->endOfDay() : null,
        };
    }

    private function getHierarchy(?Carbon $targetDate)
    {
        $strategies = Cache::remember('dashboard_hierarchy', 3600, function () {
            $recursiveLoad = function ($q) {
                $q->orderBy('name', 'asc')->withCount(['issues as issue_count' => fn($i) => $i->where('status', '!=', 'resolved')]);
            };

            $relations = [];
            $depth = 'children';
            for ($i = 0; $i < 10; $i++) {
                $relations[$depth] = $recursiveLoad;
                $depth .= '.children';
            }

            return WorkItem::whereNull('parent_id') // ดึงจาก Root เท่านั้น
                ->with($relations)
                ->withCount(['issues as strategy_issue_count' => fn($i) => $i->where('status', '!=', 'resolved')])
                ->get()
                ->sortBy('name', SORT_NATURAL)
                ->map(function ($strategy) {
                    $strategy->isOpen = false;
                    return $strategy;
                })->values();
        });

        if (!$targetDate) return $strategies;

        $strategies = unserialize(serialize($strategies));
        $histories = ProgressHistory::where('created_at', '<=', $targetDate)
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('work_item_id');

        return $this->applyTimeMachineToTree($strategies, $targetDate, $histories);
    }

    private function applyTimeMachineToTree($items, Carbon $targetDate, $histories)
    {
        return $items->filter(function($item) use ($targetDate) {
            return Carbon::parse($item->created_at)->lte($targetDate);
        })->map(function($item) use ($targetDate, $histories) {

            $item->progress = $histories->has($item->id) ? $histories->get($item->id)->first()->progress : 0;

            if ($item->status !== 'cancelled') {
                $item->status = $this->simulateStatus($item, $targetDate);
            }

            if ($item->children) {
                $item->setRelation('children', $this->applyTimeMachineToTree($item->children, $targetDate, $histories));
            }
            return $item;
        })->values();
    }

    private function applyTimeMachineToProjects($projects, Carbon $targetDate)
    {
        $histories = ProgressHistory::whereIn('work_item_id', $projects->pluck('id'))
            ->where('created_at', '<=', $targetDate)
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('work_item_id');

        return $projects->map(function($p) use ($targetDate, $histories) {
            $p->progress = $histories->has($p->id) ? $histories->get($p->id)->first()->progress : 0;

            if ($p->status !== 'cancelled') {
                $p->status = $this->simulateStatus($p, $targetDate);
            }
            return $p;
        });
    }

    private function simulateStatus($item, Carbon $targetDate): string
    {
        if ($item->progress >= 100) return 'completed';
        if ($item->planned_end_date && $targetDate > Carbon::parse($item->planned_end_date)->endOfDay() && $item->progress < 100) {
            return 'delayed';
        }
        return $item->progress > 0 ? 'in_progress' : 'in_active';
    }

    private function getWatchProjects($projects, ?Carbon $targetDate)
    {
        return $projects->filter(function($p) use ($targetDate) {
            if (in_array($p->status, ['completed', 'cancelled'])) return false;

            $compareDate = $targetDate ? clone $targetDate : now();
            $isDelayed = $p->status === 'delayed';
            $isEndingSoon = $p->planned_end_date && Carbon::parse($p->planned_end_date)->lte($compareDate->copy()->addDays(30));

            return $isDelayed || $isEndingSoon;
        })
        ->sortBy(fn($p) => $p->status === 'delayed' ? 1 : 2)
        ->take(5)
        ->map(function($p) use ($targetDate) {
            $compareDate = $targetDate ? clone $targetDate : now();
            return [
                'id' => $p->id,
                'name' => $p->name,
                'pm_name' => $p->projectManager->name ?? 'ไม่ระบุ PM',
                'progress' => $p->progress,
                'status' => $p->status,
                'due_date' => $p->planned_end_date ? Carbon::parse($p->planned_end_date)->format('d/m/Y') : '-',
                'is_urgent' => $p->status === 'delayed' || ($p->planned_end_date && Carbon::parse($p->planned_end_date)->lte($compareDate->copy()->addDays(7))),
            ];
        })->values();
    }
}
