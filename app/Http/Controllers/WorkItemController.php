<?php

namespace App\Http\Controllers;

use App\Models\WorkItem;
use App\Models\AuditLog;
use App\Models\Comment;
use App\Models\WorkItemLink;
use App\Models\ProjectManager;
use App\Models\Division;
use App\Services\LineService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Carbon\Carbon;

class WorkItemController extends Controller
{
    // --- 1. หน้าแผนงานทั้งหมด (Plans) ---
    public function plans(Request $request)
    {
        return $this->renderList($request, 'plan');
    }

    // --- 2. หน้าโครงการทั้งหมด (Projects) ---
    public function projects(Request $request)
    {
        return $this->renderList($request, 'project');
    }

    // --- ฟังก์ชันกลางสำหรับดึงข้อมูลและ Render หน้า List ---
    private function renderList(Request $request, $type)
    {
        $query = WorkItem::where('type', $type)
            ->with(['issues', 'parent', 'division', 'department', 'projectManager']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'ilike', '%' . $search . '%')
                  ->orWhereHas('projectManager', function($pm) use ($search) {
                      $pm->where('name', 'ilike', '%' . $search . '%');
                  });
            });
        }

        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('year')) $query->whereYear('planned_start_date', $request->year);
        if ($request->filled('division_id')) $query->where('division_id', $request->division_id);
        if ($request->filled('department_id')) $query->where('department_id', $request->department_id);

        $sortField = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
        if(in_array($sortField, ['name', 'budget', 'progress', 'planned_start_date', 'created_at'])) {
            $query->orderBy($sortField, $sortDir);
        }

        $items = $query->paginate(10)->withQueryString();

        $parentOptions = WorkItem::select('id', 'name', 'type')
            ->orderByRaw("CASE WHEN type = 'strategy' THEN 1 WHEN type = 'plan' THEN 2 WHEN type = 'project' THEN 3 ELSE 4 END")
            ->orderBy('name')->get()
            ->map(function($item) {
                $map = ['strategy'=>'ยุทธศาสตร์', 'plan'=>'แผนงาน', 'project'=>'โครงการ', 'task'=>'งานย่อย'];
                $item->type_label = $map[$item->type] ?? $item->type;
                return $item;
            });

        $divisions = Division::with('departments')->orderBy('name')->get();

        return Inertia::render('WorkItem/List', [
            'type' => $type,
            'items' => $items,
            'filters' => $request->all(['search', 'status', 'year', 'sort_by', 'sort_dir', 'division_id', 'department_id']),
            'parentOptions' => $parentOptions,
            'divisions' => $divisions,
        ]);
    }

    // --- CRUD Functions ---

    public function store(Request $request)
    {
        if (empty($request->department_id)) {
            $request->merge(['department_id' => null]);
        }

        $validated = $request->validate([
            'parent_id' => 'nullable|exists:work_items,id',
            'name' => 'required|string|max:255',
            'type' => 'required|string',
            'status' => 'nullable|string',
            'progress' => 'nullable|numeric|min:0|max:100',
            'budget' => 'nullable|numeric',
            'planned_start_date' => 'nullable|date',
            'planned_end_date' => 'nullable|date',
            'division_id' => 'required|exists:divisions,id',
            'department_id' => 'nullable|exists:departments,id',
            'pm_name' => 'nullable|string|max:255',
            'weight' => 'nullable|numeric|min:0',
        ]);

        if ($request->filled('pm_name')) {
            $pm = ProjectManager::firstOrCreate(['name' => trim($request->pm_name)]);
            $validated['project_manager_id'] = $pm->id;
        }
        unset($validated['pm_name']);

        $validated['progress'] = (int) ($validated['progress'] ?? 0);
        $validated['budget'] = $validated['budget'] ?? 0;
        $validated['status'] = $validated['status'] ?? 'pending';

        // ✅ Auto Set Active: ถ้าสถานะไม่ใช่ 'cancelled' ให้ถือว่า Active (เปิดใช้งาน)
        // ถ้าเป็น 'cancelled' ให้ is_active = false (ปิดการคำนวณน้ำหนัก)
        $validated['is_active'] = $validated['status'] !== 'cancelled';

        $workItem = WorkItem::create($validated);

        if ($workItem->parent) {
            $workItem->parent->recalculateProgress();
        }

        try {
            $msg = "✨ สร้างงานใหม่: " . $workItem->name . "\n" .
                   "📌 ประเภท: " . $workItem->type . "\n" .
                   "💰 งบประมาณ: " . number_format($workItem->budget) . " บาท\n" .
                   "🏢 สังกัด: " . ($workItem->division ? $workItem->division->name : '-') . "\n" .
                   "👤 โดย: " . auth()->user()->name;
            LineService::sendPushMessage($msg);
        } catch (\Exception $e) {}

        return back()->with('success', 'บันทึกข้อมูลเรียบร้อยแล้ว');
    }

    public function update(Request $request, WorkItem $workItem)
    {
        if (empty($request->department_id)) {
            $request->merge(['department_id' => null]);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'progress' => 'nullable|numeric|min:0|max:100',
            'status' => 'required|string',
            'budget' => 'nullable|numeric',
            'planned_start_date' => 'nullable|date',
            'planned_end_date' => 'nullable|date',
            'type' => 'required|string',
            'parent_id' => 'nullable|exists:work_items,id',
            'division_id' => 'required|exists:divisions,id',
            'department_id' => 'nullable|exists:departments,id',
            'pm_name' => 'nullable|string|max:255',
            'weight' => 'nullable|numeric|min:0',
        ]);

        if ($request->has('pm_name')) {
            if ($request->filled('pm_name')) {
                $pm = ProjectManager::firstOrCreate(['name' => trim($request->pm_name)]);
                $validated['project_manager_id'] = $pm->id;
            } else {
                $validated['project_manager_id'] = null;
            }
            unset($validated['pm_name']);
        }

        if (isset($validated['progress'])) {
            $validated['progress'] = (int) $validated['progress'];
        } else {
            $validated['progress'] = 0;
        }

        // ✅ Auto Set Active logic เดียวกับ Store
        $validated['is_active'] = $validated['status'] !== 'cancelled';

        $workItem->update($validated);

        if ($workItem->wasChanged('progress') || $workItem->wasChanged('status')) {
            try {
                $msg = "📈 อัปเดตงาน: " . $workItem->name . "\n" .
                       "📊 ความคืบหน้า: " . $workItem->progress . "%" . "\n" .
                       "🚩 สถานะ: " . $workItem->status . "\n" .
                       "👤 แก้ไขโดย: " . auth()->user()->name;
                LineService::sendPushMessage($msg);
            } catch (\Exception $e) {}
        }

        if ($workItem->parent) {
            $workItem->parent->recalculateProgress();
        }

        return back()->with('success', 'บันทึกข้อมูลเรียบร้อยแล้ว');
    }

    public function destroy(WorkItem $workItem)
    {
        $parent = $workItem->parent;
        $workItem->delete();

        if ($parent) {
            $parent->recalculateProgress();
        }

        return back()->with('success', 'ลบงานสำเร็จ');
    }

    public function show(WorkItem $workItem)
    {
        $workItem->load([
            'parent.parent.parent',
            'attachments.uploader',
            'issues.user',
            'children' => function($q) {
                // ดึง PM ของลูกมาด้วย เพื่อให้ตอน Edit Modal ไม่หาย
                $q->orderBy('order_index')->with(['attachments', 'projectManager']);
            },
            'children.children' => function($q) { $q->orderBy('order_index'); },
            'children.children.children',
            'division',
            'department',
            'projectManager'
        ]);

        // S-Curve Logic (ย่อไว้เหมือนเดิม)
        $months = []; $plannedData = []; $actualData = [];
        $startDate = $workItem->planned_start_date ? $workItem->planned_start_date->copy()->startOfMonth() : now()->startOfYear();
        $endDate = $workItem->planned_end_date ? $workItem->planned_end_date->copy()->endOfMonth() : now()->endOfYear();
        if ($endDate->lt($startDate)) $endDate = $startDate->copy()->addMonths(1);
        $allChildren = collect([$workItem]);
        $tempQueue = [$workItem];
        while(count($tempQueue) > 0) {
            $current = array_shift($tempQueue);
            if($current->children) { foreach($current->children as $child) { $allChildren->push($child); $tempQueue[] = $child; } }
        }
        $budgetItems = $allChildren->filter(function($item) {
            if ($item->budget <= 0) return false;
            if ($item->children->isEmpty()) return true;
            $childrenBudget = $item->children->sum('budget');
            if ($childrenBudget > 0) return false;
            return true;
        });
        $totalProjectBudget = $budgetItems->sum('budget');
        if ($totalProjectBudget <= 0) $totalProjectBudget = 1;
        $currentDate = $startDate->copy();
        while ($currentDate->lte($endDate)) {
            $thaiMonths = [1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.', 4 => 'เม.ย.', 5 => 'พ.ค.', 6 => 'มิ.ย.', 7 => 'ก.ค.', 8 => 'ส.ค.', 9 => 'ก.ย.', 10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.'];
            $months[] = $thaiMonths[$currentDate->month] . ' ' . substr($currentDate->year + 543, -2);
            $calcDate = $currentDate->copy()->endOfMonth();
            $pvMoney = $budgetItems->sum(function($item) use ($calcDate) {
                if (!$item->planned_start_date || !$item->planned_end_date) return 0;
                if ($calcDate->lt($item->planned_start_date)) return 0;
                if ($calcDate->gt($item->planned_end_date)) return $item->budget;
                $totalDays = $item->planned_start_date->diffInDays($item->planned_end_date) + 1;
                $passedDays = $item->planned_start_date->diffInDays($calcDate) + 1;
                return $item->budget * ($passedDays / max(1, $totalDays));
            });
            $plannedData[] = round(($pvMoney / $totalProjectBudget) * 100, 2);
            if ($calcDate->lte(now()->endOfMonth())) {
                $evMoney = $budgetItems->sum(fn($item) => $item->budget * ($item->progress / 100));
                $actualData[] = round(($evMoney / $totalProjectBudget) * 100, 2);
            }
            $currentDate->addMonth();
        }

        // Timeline Logic
        $relatedIds = collect([$workItem->id])->merge($allChildren->pluck('id'))->unique()->toArray();
        $logs = AuditLog::with('user')->where(function($q) use ($relatedIds) { $q->where('model_type', 'WorkItem')->whereIn('model_id', $relatedIds); })->orWhere(function($q) use ($workItem) { $q->whereIn('model_type', ['Attachment', 'Issue'])->where(function($sq) use ($workItem) { $sq->where('changes->work_item_id', $workItem->id)->orWhere('changes->after->work_item_id', $workItem->id); }); })->get()->map(function ($item) use ($allChildren) { $item->timeline_type = 'log'; if ($item->model_type === 'WorkItem') { $target = $allChildren->firstWhere('id', $item->model_id); $item->target_name = $target ? $target->name : 'รายการที่ถูกลบ'; } return $item; });
        $comments = Comment::with('user')->whereIn('work_item_id', $relatedIds)->get()->map(function ($item) use ($allChildren) { $item->timeline_type = 'comment'; $target = $allChildren->firstWhere('id', $item->work_item_id); $item->target_name = $target ? $target->name : ''; return $item; });
        $timeline = $logs->concat($comments)->sortByDesc('created_at')->values();
        $page = request()->get('page', 1); $perPage = 10; $total = $timeline->count();
        $paginatedItems = $timeline->slice(($page - 1) * $perPage, $perPage)->values();
        $paginatedTimeline = new LengthAwarePaginator($paginatedItems, $total, $perPage, $page, ['path' => request()->url(), 'query' => request()->query()]);

        $divisions = Division::with('departments')->orderBy('name')->get();

        return Inertia::render('Project/Detail', [
            'item' => $workItem,
            'historyLogs' => $paginatedTimeline,
            'chartData' => ['categories' => $months, 'planned' => $plannedData, 'actual' => $actualData],
            'divisions' => $divisions,
        ]);
    }

    public function list(Request $request, $type)
    {
        return $this->renderList($request, $type);
    }

    public function strategies()
    {
        $strategies = WorkItem::where('type', 'strategy')
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
                $strategy->isOpen = false;
                return $strategy;
            });

        return Inertia::render('Strategy/Index', [
            'strategies' => $strategies
        ]);
    }

    public function index(Request $request)
    {
        return $this->projects($request);
    }

    public function ganttData(WorkItem $workItem)
    {
        try {
            $allIds = collect([$workItem->id]);
            $workItem->load('children.children.children.children.children');

            $flatten = function ($item) use (&$flatten, &$allIds) {
                if ($item->children) {
                    foreach ($item->children as $child) {
                        $allIds->push($child->id);
                        $flatten($child);
                    }
                }
            };
            $flatten($workItem);

            $tasks = WorkItem::whereIn('id', $allIds)
                ->orderBy('order_index')
                ->get()
                ->map(function ($t) use ($workItem) {
                    $start = $t->planned_start_date ? Carbon::parse($t->planned_start_date) : null;
                    $end = $t->planned_end_date ? Carbon::parse($t->planned_end_date) : null;

                    // เช็คสถานะการใช้งาน
                    $isActive = $t->is_active;

                    // ปรับสีถ้า Disabled ให้เป็นสีเทา
                    $color = $t->status === 'delayed' ? '#EF4444' : ($t->progress == 100 ? '#10B981' : '#3B82F6');
                    if (!$isActive) {
                        $color = '#9CA3AF'; // สีเทา
                    }

                    return [
                        'id' => $t->id,
                        'text' => $t->name,
                        'start_date' => $start ? $start->format('Y-m-d') : null,
                        'duration' => ($start && $end) ? $start->diffInDays($end) + 1 : 1,
                        'progress' => (float)$t->progress / 100,
                        'parent' => ($t->id == $workItem->id) ? 0 : $t->parent_id,
                        'open' => true,
                        'type' => $t->type === 'project' ? 'project' : 'task',
                        'color' => $color,
                    ];
                });

            $links = [];
            try {
                if (class_exists(WorkItemLink::class)) {
                    $links = WorkItemLink::whereIn('source', $allIds)
                        ->orWhereIn('target', $allIds)
                        ->get()
                        ->map(function ($l) {
                            return [
                                'id' => $l->id,
                                'source' => $l->source,
                                'target' => $l->target,
                                'type' => $l->type
                            ];
                        });
                }
            } catch (\Throwable $e) {}

            return response()->json([
                'data' => $tasks,
                'links' => $links
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    public function logExport(Request $request, WorkItem $workItem)
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'EXPORT',
            'model_type' => 'Gantt Chart',
            'model_id' => $workItem->id,
            'target_name' => $workItem->name,
            'changes' => ['file_type' => 'PDF', 'note' => 'Exported Gantt Chart'],
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['message' => 'Logged successfully']);
    }

    public function searchProjectManagers(Request $request) {
        $search = $request->input('query');
        if (!$search) return response()->json([]);
        return ProjectManager::where('name', 'ilike', "%{$search}%")->limit(10)->get(['id', 'name']);
    }

}
