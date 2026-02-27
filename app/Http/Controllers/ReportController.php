<?php

namespace App\Http\Controllers;

use App\Models\WorkItem;
use App\Models\Issue;
use App\Models\AuditLog;
use App\Models\Division;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use App\Jobs\GenerateProgressReportJob;
use App\Exports\ProjectProgressExport;
use App\Exports\IssueRiskExport;
use App\Exports\ExecutiveSummaryExport;
use Carbon\Carbon;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Illuminate\Support\Facades\Cache;

class ReportController extends Controller
{
    public function index()
    {
        $divisions = Division::orderBy('name')->get();
        $strategies = WorkItem::whereNull('parent_id')->orderBy('name')->get();
        $projects = WorkItem::where('type', 'project')->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Report/Index', [
            'divisions' => $divisions,
            'strategies' => $strategies,
            'projects' => $projects
        ]);
    }

    public function getDescendantIds($item)
    {
        $ids = [];
        $children = WorkItem::where('parent_id', $item->id)->get();
        foreach ($children as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $this->getDescendantIds($child));
        }
        return $ids;
    }

    // =========================================================================
    // 1. รายงานความก้าวหน้า (Progress Report)
    // =========================================================================
    public function exportProgressPdf(Request $request)
    {
        $cacheKey = 'report_progress_' . md5(json_encode($request->all()));

        $data = Cache::remember($cacheKey, 300, function () use ($request) {
            $query = WorkItem::whereNull('parent_id')
                ->with(['children' => function($q) use ($request) {
                    if ($request->division_id) $q->where('division_id', $request->division_id);
                    $q->with(['children' => function($sq) use ($request) {
                        if ($request->division_id) $sq->where('division_id', $request->division_id);
                    }]);
                }]);

            if ($request->strategy_id) {
                $query->where('id', $request->strategy_id);
            }

            $strategies = $query->get()->sortBy('name', SORT_NATURAL)->values();

            $statsQuery = WorkItem::where('type', 'project');
            if ($request->division_id) $statsQuery->where('division_id', $request->division_id);
            if ($request->strategy_id) {
                $strategy = WorkItem::find($request->strategy_id);
                $descendants = $strategy ? $this->getDescendantIds($strategy) : [];
                $statsQuery->whereIn('id', $descendants);
            }

            $stats = [
                'total' => $statsQuery->count(),
                'budget' => $statsQuery->sum('budget'),
                'completed' => (clone $statsQuery)->where('progress', 100)->count(),
            ];

            return compact('strategies', 'stats');
        });

        $fileName = 'progress-report-' . now()->format('Ymd-His') . '.pdf';
        $pdf = Pdf::loadView('reports.progress_pdf', [
            'strategies' => $data['strategies'],
            'stats' => $data['stats'],
            'date' => now()->format('d/m/Y')
        ])->setPaper('a4', 'landscape');

        $this->logExport('รายงานความก้าวหน้า (PDF)', $fileName);
        return $pdf->stream($fileName);
    }

    public function exportProgressExcel(Request $request) {
        if ($request->strategy_id) {
            $strategy = WorkItem::find($request->strategy_id);
            $request->merge(['filtered_item_ids' => $this->getDescendantIds($strategy)]);
        }
        $fileName = 'progress-report-' . now()->format('Ymd-His') . '.xlsx';
        $this->logExport('รายงานความก้าวหน้า (Excel)', $fileName);
        return Excel::download(new ProjectProgressExport($request), $fileName);
    }

    public function exportProgressCsv(Request $request) {
        if ($request->strategy_id) {
            $strategy = WorkItem::find($request->strategy_id);
            $request->merge(['filtered_item_ids' => $this->getDescendantIds($strategy)]);
        }
        $fileName = 'progress-report-' . now()->format('Ymd-His') . '.csv';
        $this->logExport('รายงานความก้าวหน้า (CSV)', $fileName);
        return Excel::download(new ProjectProgressExport($request), $fileName, ExcelFormat::CSV);
    }

    // =========================================================================
    // 2. รายงานปัญหา (Issues Report)
    // =========================================================================
    public function exportIssuesPdf(Request $request)
    {
        $query = Issue::with('workItem')->orderBy('severity');

        if ($request->type) $query->where('type', $request->type);
        if ($request->status) $query->where('status', $request->status);
        if ($request->start_date) $query->whereDate('created_at', '>=', $request->start_date);
        if ($request->end_date) $query->whereDate('created_at', '<=', $request->end_date);

        $issues = $query->get();
        $fileName = 'issues-report-' . now()->format('Ymd-His') . '.pdf';

        $pdf = Pdf::loadView('reports.issues_pdf', [
            'issues' => $issues,
            'date' => now()->format('d/m/Y')
        ])->setPaper('a4', 'portrait');

        $this->logExport('รายงานปัญหา (PDF)', $fileName);
        return $pdf->stream($fileName);
    }

    public function exportIssuesExcel(Request $request) {
        $fileName = 'issues-report-' . now()->format('Ymd-His') . '.xlsx';
        $this->logExport('รายงานปัญหา (Excel)', $fileName);
        return Excel::download(new IssueRiskExport($request), $fileName);
    }

    public function exportIssuesCsv(Request $request) {
        $fileName = 'issues-report-' . now()->format('Ymd-His') . '.csv';
        $this->logExport('รายงานปัญหา (CSV)', $fileName);
        return Excel::download(new IssueRiskExport($request), $fileName, ExcelFormat::CSV);
    }

    // =========================================================================
    // 3. รายงานผู้บริหาร (Executive Report) (✅ มี Validation ป้องกันพัง)
    // =========================================================================
    public function exportExecutivePdf(Request $request)
    {
        // 🛡️ ป้องกันระบบล่มด้วยการจำกัดสูงสุด 20 โครงการ
        $request->validate([
            'project_ids' => 'nullable|array|max:20'
        ], [
            'project_ids.max' => 'เลือกโครงการได้สูงสุด 20 รายการต่อการดาวน์โหลด 1 ครั้ง'
        ]);

        $projectIds = $request->project_ids ?? [];
        $cacheKey = 'report_executive_' . md5(json_encode($request->all()));

        $data = Cache::remember($cacheKey, 300, function () use ($projectIds) {
            $stats = [
                'total' => WorkItem::where('type', 'project')->count(),
                'budget' => WorkItem::where('type', 'project')->sum('budget'),
                'critical_issues' => Issue::where('severity', 'critical')->where('status', '!=', 'resolved')->count(),
            ];

            $topProjectsQuery = WorkItem::where('type', 'project');

            if (!empty($projectIds)) {
                $topProjectsQuery->whereIn('id', $projectIds)->take(20); // ล็อค Take 20 อีกชั้น
            } else {
                $topProjectsQuery->orderByDesc('budget')->take(5);
            }

            $topProjects = $topProjectsQuery->get();

            return compact('stats', 'topProjects');
        });

        $fileName = 'executive-report-' . now()->format('Ymd-His') . '.pdf';

        $pdf = Pdf::loadView('reports.executive_pdf', [
            'stats' => $data['stats'],
            'topProjects' => $data['topProjects'],
            'date' => now()->format('d/m/Y')
        ])->setPaper('a4', 'portrait');

        $this->logExport('รายงานผู้บริหาร (PDF)', $fileName);
        return $pdf->stream($fileName);
    }

    public function exportExecutiveExcel(Request $request) {
        $request->validate(['project_ids' => 'nullable|array|max:20']);
        $fileName = 'executive-report-' . now()->format('Ymd-His') . '.xlsx';
        $this->logExport('รายงานผู้บริหาร (Excel)', $fileName);
        return Excel::download(new ExecutiveSummaryExport($request), $fileName);
    }

    public function exportExecutiveCsv(Request $request) {
        $request->validate(['project_ids' => 'nullable|array|max:20']);
        $fileName = 'executive-report-' . now()->format('Ymd-His') . '.csv';
        $this->logExport('รายงานผู้บริหาร (CSV)', $fileName);
        return Excel::download(new ExecutiveSummaryExport($request), $fileName, ExcelFormat::CSV);
    }

    // =========================================================================
    // 4. รายงานโครงสร้างยุทธศาสตร์ (Tree View)
    // =========================================================================
    public function exportTreePdf(Request $request)
    {
        $cacheKey = 'report_tree_' . md5(json_encode($request->all()));

        $strategies = Cache::remember($cacheKey, 300, function () use ($request) {
            $recursiveLoad = function ($q) { $q->orderBy('name', 'asc')->with('projectManager'); };
            $relations = [];
            $depth = 'children';
            for ($i = 0; $i < 6; $i++) {
                $relations[$depth] = $recursiveLoad;
                $depth .= '.children';
            }

            $query = WorkItem::whereNull('parent_id')->with($relations);

            if ($request->strategy_id) {
                $query->where('id', $request->strategy_id);
            }

            return $query->get()->sortBy('name', SORT_NATURAL)->values();
        });

        $fileName = 'strategy-tree-report-' . now()->format('Ymd-His') . '.pdf';

        $pdf = Pdf::loadView('reports.tree-view', [
            'strategies' => $strategies,
            'date' => now()->format('d/m/Y')
        ])->setPaper('a4', 'landscape');

        $this->logExport('รายงานโครงสร้างยุทธศาสตร์ (PDF)', $fileName);
        return $pdf->stream($fileName);
    }

    public function exportTreeExcel(Request $request) {
        $fileName = 'strategy-tree-report-' . now()->format('Ymd-His') . '.xlsx';
        $this->logExport('รายงานโครงสร้างยุทธศาสตร์ (Excel)', $fileName);
        return Excel::download(new \App\Exports\StrategyTreeExport($request), $fileName);
    }

    public function exportTreeCsv(Request $request) {
        $fileName = 'strategy-tree-report-' . now()->format('Ymd-His') . '.csv';
        $this->logExport('รายงานโครงสร้างยุทธศาสตร์ (CSV)', $fileName);
        return Excel::download(new \App\Exports\StrategyTreeExport($request), $fileName, ExcelFormat::CSV);
    }

    // --- Single Item & Timeline ---
    public function exportWorkItemPdf($id)
    {
        $workItem = Cache::remember("report_project_{$id}", 60, function () use ($id) {
            return WorkItem::with(['children', 'issues', 'attachments', 'parent'])->findOrFail($id);
        });
        $fileName = 'project-' . $workItem->id . '-' . now()->format('Ymd') . '.pdf';
        $pdf = Pdf::loadView('reports.work_item_detail', ['item' => $workItem, 'date' => now()->format('d/m/Y')])->setPaper('a4', 'portrait');
        $this->logExport('WorkItem Detail (PDF)', $fileName, $workItem->id, $workItem->name);
        return $pdf->stream($fileName);
    }

    public function exportMilestonePdf($id)
    {
        $workItem = WorkItem::with(['children.children.children'])->findOrFail($id);
        $tasks = collect();
        $extractTasks = function ($items) use (&$extractTasks, &$tasks) {
            foreach ($items as $item) {
                if ($item->planned_end_date && $item->status !== 'cancelled') $tasks->push($item);
                if ($item->children->count() > 0) $extractTasks($item->children);
            }
        };
        $extractTasks($workItem->children);

        $groupedMilestones = $tasks->sortBy('planned_end_date')->groupBy(function ($task) {
            return Carbon::parse($task->planned_end_date)->format('Y-m');
        })->map(function ($group) {
            $date = Carbon::parse($group->first()->planned_end_date);
            return [
                'label' => $date->locale('th')->translatedFormat('F Y'),
                'timestamp' => $date->timestamp,
                'tasks' => $group->map(function($t) { return ['name' => $t->name, 'description' => $t->description, 'status' => $t->status, 'progress' => $t->progress]; })->toArray()
            ];
        })->sortBy('timestamp')->values();

        $chunkedMilestones = $groupedMilestones->chunk(4);
        $fileName = 'milestone-timeline-' . $workItem->id . '-' . now()->format('Ymd') . '.pdf';

        $pdf = Pdf::loadView('reports.milestone_pdf', ['item' => $workItem, 'chunkedMilestones' => $chunkedMilestones, 'date' => now()->format('d/m/Y')])->setPaper('a4', 'landscape');
        $this->logExport('รายงานไทม์ไลน์เป้าหมาย (PDF)', $fileName, $workItem->id, $workItem->name);
        return $pdf->stream($fileName);
    }

    private function logExport($type, $fileName, $modelId = 0, $targetName = null)
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'EXPORT',
            'model_type' => $type,
            'model_id' => $modelId,
            'ip_address' => request()->ip(),
            'target_name' => $targetName ?? $type,
            'changes' => ['ชื่อไฟล์' => $fileName],
        ]);
    }

}
