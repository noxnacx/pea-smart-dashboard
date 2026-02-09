<?php

namespace App\Http\Controllers;

use App\Models\WorkItem;
use App\Models\AuditLog;
use App\Models\Comment;
use App\Models\WorkItemLink;
use App\Models\ProjectManager;
use App\Models\Division;
use App\Models\Department;
use App\Services\LineService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache; // ✅ เพิ่ม Cache Facade

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
        $validated['is_active'] = $validated['status'] !== 'cancelled';

        $workItem = WorkItem::create($validated);

        // ✅ บันทึก Log สร้างใหม่
        $this->logActivity('CREATE', $workItem, [], $validated);

        if ($workItem->parent) {
            $workItem->parent->recalculateProgress();
        }

        // 🚀 Clear Cache เพื่อให้ข้อมูลอัปเดตทันที
        $this->clearRelatedCache($workItem);

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

        $validated['is_active'] = $validated['status'] !== 'cancelled';

        // ✅ เก็บค่าเก่าก่อนอัปเดต
        $oldData = $workItem->getOriginal();

        $workItem->update($validated);

        // ✅ บันทึก Log แก้ไข
        $this->logActivity('UPDATE', $workItem, $oldData, $workItem->getChanges());

        // 🚀 Clear Cache เพื่อให้ข้อมูลอัปเดตทันที
        $this->clearRelatedCache($workItem);

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

        // ✅ บันทึก Log ลบ (ก่อนลบจริง)
        $this->logActivity('DELETE', $workItem, $workItem->toArray(), []);

        // 🚀 Clear Cache ก่อนลบ (หรือหลังลบก็ได้ แต่ต้องเคลียร์)
        $this->clearRelatedCache($workItem);

        $workItem->delete();

        if ($parent) {
            $parent->recalculateProgress();
        }

        return back()->with('success', 'ลบงานสำเร็จ');
    }

    // --- Helper Function สำหรับเคลียร์ Cache ---
    private function clearRelatedCache($workItem)
    {
        // 1. เคลียร์ Dashboard (Hierarchy + S-Curve)
        Cache::forget('dashboard_hierarchy');
        Cache::forget('dashboard_s_curve');

        // 2. เคลียร์หน้ายุทธศาสตร์
        Cache::forget('strategies_index');

        // 3. เคลียร์ S-Curve ของตัวเอง และของ Parent (ถ้ามี)
        Cache::forget("work_item_{$workItem->id}_s_curve");
        if ($workItem->parent_id) {
            Cache::forget("work_item_{$workItem->parent_id}_s_curve");
        }
    }

    // --- Helper Function สำหรับบันทึก Audit Log ---
    private function logActivity($action, $model, $oldData = [], $changes = [])
    {
        // รายการฟิลด์ที่จะแปลง ID เป็นชื่อ เพื่อให้อ่านง่าย
        $relationMap = [
            'project_manager_id' => ['model' => ProjectManager::class, 'label' => 'ผู้ดูแล (PM)'],
            'parent_id' => ['model' => WorkItem::class, 'label' => 'งานภายใต้'],
            'division_id' => ['model' => Division::class, 'label' => 'กอง'],
            'department_id' => ['model' => Department::class, 'label' => 'แผนก'],
        ];

        // รายการฟิลด์ทั่วไปที่จะเปลี่ยนชื่อ Key ให้อ่านง่าย
        $fieldLabels = [
            'name' => 'ชื่อรายการ',
            'status' => 'สถานะ',
            'progress' => 'ความคืบหน้า',
            'budget' => 'งบประมาณ',
            'planned_start_date' => 'วันเริ่ม',
            'planned_end_date' => 'วันสิ้นสุด',
            'weight' => 'น้ำหนักงาน',
            'is_active' => 'สถานะ Active'
        ];

        $logChanges = ['before' => [], 'after' => []];

        if ($action === 'CREATE') {
            $logChanges['after'] = $changes;
        } elseif ($action === 'DELETE') {
            $logChanges['before'] = $oldData;
        } else {
            // กรณี UPDATE: วนลูปสิ่งที่เปลี่ยนไป
            foreach ($changes as $key => $newValue) {
                if ($key === 'updated_at') continue; // ข้าม timestamp

                $oldValue = $oldData[$key] ?? null;
                $label = $fieldLabels[$key] ?? $key;

                // 1. ถ้าเป็น Relation ID ให้ไปหาชื่อมาใส่แทน
                if (array_key_exists($key, $relationMap)) {
                    $config = $relationMap[$key];
                    $label = $config['label'];

                    // หาชื่อเก่า
                    $oldName = '-';
                    if ($oldValue) {
                        $oldModel = $config['model']::find($oldValue);
                        $oldName = $oldModel ? $oldModel->name : $oldValue;
                    }

                    // หาชื่อใหม่
                    $newName = '-';
                    if ($newValue) {
                        $newModel = $config['model']::find($newValue);
                        $newName = $newModel ? $newModel->name : $newValue;
                    }

                    $logChanges['before'][$label] = $oldName;
                    $logChanges['after'][$label] = $newName;
                }
                // 2. ข้อมูลปกติ
                else {
                    $logChanges['before'][$label] = $oldValue;
                    $logChanges['after'][$label] = $newValue;
                }
            }
        }

        // บันทึกลง Database ถ้ามีความเปลี่ยนแปลง
        if (!empty($logChanges['after']) || !empty($logChanges['before']) || $action === 'DELETE') {
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => $action,
                'model_type' => 'WorkItem',
                'model_id' => $model->id,
                'target_name' => $model->name,
                'changes' => $logChanges,
                'ip_address' => request()->ip(),
            ]);
        }
    }

    public function show(WorkItem $workItem)
    {
        $workItem->load([
            'parent.parent.parent',
            'attachments.uploader',
            'issues.user',
            'children' => function($q) {
                $q->orderBy('order_index')->with(['attachments', 'projectManager']);
            },
            'children.children' => function($q) { $q->orderBy('order_index'); },
            'children.children.children',
            'division',
            'department',
            'projectManager'
        ]);

        // ==========================================
        // 🚀 S-Curve Logic (CACHED)
        // ==========================================
        $chartData = Cache::remember("work_item_{$workItem->id}_s_curve", 3600, function () use ($workItem) {
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

            return ['categories' => $months, 'planned' => $plannedData, 'actual' => $actualData];
        });

        // Timeline Logic
        $relatedIds = collect([$workItem->id])->merge(collect($workItem->children)->pluck('id'))->unique()->toArray();
        $logs = AuditLog::with('user')
            ->where(function($q) use ($relatedIds) { $q->where('model_type', 'WorkItem')->whereIn('model_id', $relatedIds); })
            ->orWhere(function($q) use ($workItem) { $q->whereIn('model_type', ['Attachment', 'Issue'])->where(function($sq) use ($workItem) { $sq->where('changes->work_item_id', $workItem->id)->orWhere('changes->after->work_item_id', $workItem->id); }); })
            ->get()
            ->map(function ($item) {
                $item->timeline_type = 'log';
                // (Optional) Map target name logic here if needed
                return $item;
            });

        $comments = Comment::with('user')->whereIn('work_item_id', $relatedIds)->get()->map(function ($item) {
            $item->timeline_type = 'comment';
            return $item;
        });

        $timeline = $logs->concat($comments)->sortByDesc('created_at')->values();
        $page = request()->get('page', 1);
        $perPage = 10;
        $total = $timeline->count();
        $paginatedItems = $timeline->slice(($page - 1) * $perPage, $perPage)->values();
        $paginatedTimeline = new LengthAwarePaginator($paginatedItems, $total, $perPage, $page, ['path' => request()->url(), 'query' => request()->query()]);

        // ✅ เพิ่มตรงนี้เพื่อให้ Pagination จำ Query String (เช่น ?tab=logs)
        $paginatedTimeline->withQueryString();

        $divisions = Division::with('departments')->orderBy('name')->get();

        return Inertia::render('Project/Detail', [
            'item' => $workItem,
            'historyLogs' => $paginatedTimeline,
            'chartData' => $chartData, // ✅ ส่งข้อมูลที่ Cache แล้วไป
            'divisions' => $divisions,
        ]);
    }

    public function list(Request $request, $type) { return $this->renderList($request, $type); }
    public function index(Request $request) { return $this->projects($request); }

    public function strategies() {
        // ==========================================
        // 🚀 Strategies Tree (CACHED) with Recursive Children
        // ==========================================
        $strategies = Cache::remember('strategies_index', 3600, function () {

            // Closure สำหรับจัดเรียงและนับ Issue ในทุกระดับชั้น (Reused for every level)
            $recursiveLoad = function ($q) {
                $q->orderBy('order_index')->orderBy('name', 'asc')
                  ->withCount(['issues as issue_count' => function($i) {
                      $i->where('status', '!=', 'resolved');
                  }]);
            };

            // สร้าง Array เพื่อ Eager Load ลึก 10 ชั้น (Strategy -> Plan -> Project -> Sub-Project ...)
            // ex: ['children', 'children.children', 'children.children.children', ...]
            $relations = [];
            $depth = 'children';
            for ($i = 0; $i < 10; $i++) {
                $relations[$depth] = $recursiveLoad;
                $depth .= '.children';
            }

            return WorkItem::where('type', 'strategy')
                ->with($relations) // ✅ โหลด Recursive 10 ชั้นรวดเดียว
                ->withCount(['issues as strategy_issue_count' => function($i) {
                     $i->where('status', '!=', 'resolved');
                }])
                ->orderBy('order_index')
                ->orderBy('name', 'asc')
                ->get();
        });

        return Inertia::render('Strategy/Index', [
            'strategies' => $strategies
        ]);
    }

    public function ganttData(WorkItem $workItem) {
        try {
            // Gantt Chart ควรเป็น Real-time เพื่อความแม่นยำในการลากวาง (ไม่ Cache)
            $allIds = collect([$workItem->id]);
            $workItem->load('children.children.children.children.children');
            $flatten = function ($item) use (&$flatten, &$allIds) { if ($item->children) { foreach ($item->children as $child) { $allIds->push($child->id); $flatten($child); } } };
            $flatten($workItem);

            $tasks = WorkItem::whereIn('id', $allIds)->orderBy('order_index')->get()->map(function ($t) use ($workItem) {
                $start = $t->planned_start_date ? Carbon::parse($t->planned_start_date) : null;
                $end = $t->planned_end_date ? Carbon::parse($t->planned_end_date) : null;

                $color = $t->status === 'delayed' ? '#EF4444' : ($t->progress == 100 ? '#10B981' : '#3B82F6');
                if ($t->status === 'cancelled') {
                    $color = '#9CA3AF';
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
            try { if (class_exists(WorkItemLink::class)) { $links = WorkItemLink::whereIn('source', $allIds)->orWhereIn('target', $allIds)->get()->map(function ($l) { return ['id' => $l->id, 'source' => $l->source, 'target' => $l->target, 'type' => $l->type]; }); } } catch (\Throwable $e) {}

            return response()->json(['data' => $tasks, 'links' => $links]);
        } catch (\Throwable $e) { return response()->json(['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()], 500); }
    }

    public function logExport(Request $request, WorkItem $workItem) {
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
