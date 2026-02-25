<?php

namespace App\Http\Controllers;

use App\Models\WorkItem;
use App\Models\WorkItemType; // ✅ เพิ่ม
use App\Models\Milestone; // ✅ เพิ่ม
use App\Models\AuditLog;
use App\Models\Comment;
use App\Models\WorkItemLink;
use App\Models\User;
use App\Models\Division;
use App\Models\Department;
use App\Models\Attachment;
use App\Services\LineService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

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

    // --- 3. หน้างานของฉัน (My Works) ---
    public function myWorks(Request $request)
    {
        $query = WorkItem::with(['issues', 'parent', 'division', 'department', 'projectManager'])
            ->where('project_manager_id', auth()->id());

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('name', 'ilike', '%' . $search . '%');
        }
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('year')) $query->whereYear('planned_start_date', $request->year);

        $sortField = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
        $query->orderBy($sortField, $sortDir);

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
        // ✅ ดึงประเภทงานไปเตรียมไว้ให้หน้าบ้าน
        $workItemTypes = WorkItemType::orderBy('level_order')->get();

        return Inertia::render('WorkItem/List', [
            'type' => 'my-work',
            'items' => $items,
            'filters' => $request->all(['search', 'status', 'year', 'sort_by', 'sort_dir']),
            'parentOptions' => $parentOptions,
            'divisions' => $divisions,
            'workItemTypes' => $workItemTypes // ✅ ส่งไปเตรียมไว้
        ]);
    }

    // --- ฟังก์ชันกลางสำหรับดึงข้อมูลและ Render หน้า List ---
    private function renderList(Request $request, $type = null) // ✅ อนุญาตให้ $type เป็น null ได้ (default view)
    {
        // ✅ ดึงประเภทงานทั้งหมดมาก่อน เพื่อหา Default Type (ถ้าไม่ส่ง $type มา)
        $workItemTypes = WorkItemType::orderBy('level_order')->get();

        // ถ้าไม่มีการระบุ type มา ให้ใช้ type ของ level 2 เป็นค่าเริ่มต้น (เพราะ Level 1 อยู่หน้า Tree View แล้ว)
        // แต่ถ้าอยากให้เริ่มที่ Level 1 เลยก็แก้เป็น $workItemTypes->first()->key
        if (!$type && $workItemTypes->count() > 1) {
            $type = $workItemTypes[1]->key;
        } elseif (!$type) {
            $type = $workItemTypes->first()->key ?? 'plan';
        }

        // ✅ Query ข้อมูลตาม Type ที่เลือก (รองรับ Dynamic Key)
        $query = WorkItem::where('type', $type)
            ->with(['issues', 'parent', 'division', 'department', 'projectManager', 'workType']); // เพิ่ม workType

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

        $divisions = Division::with('departments')->orderBy('name')->get();

        // ✅ ดึง parentOptions แบบ Dynamic ตามประเภทงาน
        $parentOptions = WorkItem::with('workType')
            ->select('id', 'name', 'type', 'work_item_type_id')
            ->get()
            ->map(function($item) {
                $item->type_label = $item->workType ? $item->workType->name : $item->type;
                return $item;
            })
            ->sortBy('type_label'); // หรือเรียงตาม level_order ถ้า join table

        return Inertia::render('WorkItem/List', [
            'type' => $type,
            'items' => $items,
            'filters' => $request->all(['search', 'status', 'year', 'sort_by', 'sort_dir', 'division_id', 'department_id']),
            'parentOptions' => $parentOptions,
            'divisions' => $divisions,
            'workItemTypes' => $workItemTypes // ✅ ส่งประเภทงานทั้งหมดไปสร้าง Tabs
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
            'description' => 'nullable|string',
            'type' => 'required|string',
            'status' => 'nullable|string',
            'progress' => 'nullable|numeric|min:0|max:100',
            'budget' => 'nullable|numeric',
            'planned_start_date' => 'nullable|date',
            'planned_end_date' => 'nullable|date',
            'division_id' => 'required|exists:divisions,id',
            'department_id' => 'nullable|exists:departments,id',
            'project_manager_id' => 'nullable|exists:users,id',
            'weight' => 'nullable|numeric|min:0',
        ]);

        $validated['progress'] = (int) ($validated['progress'] ?? 0);
        $validated['budget'] = $validated['budget'] ?? 0;
        $validated['status'] = $validated['status'] ?? 'in_active';
        $validated['is_active'] = $validated['status'] !== 'cancelled';
        $validated['weight'] = $validated['weight'] ?? 1;

        $workItem = WorkItem::create($validated);

        $this->logActivity('CREATE', $workItem, [], $validated);

        if ($workItem->parent) {
            $workItem->parent->recalculateProgress();
        }

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
            'description' => 'nullable|string',
            'progress' => 'nullable|numeric|min:0|max:100',
            'status' => 'required|string',
            'budget' => 'nullable|numeric',
            'planned_start_date' => 'nullable|date',
            'planned_end_date' => 'nullable|date',
            'type' => 'required|string',
            'parent_id' => 'nullable|exists:work_items,id',
            'division_id' => 'required|exists:divisions,id',
            'department_id' => 'nullable|exists:departments,id',
            'project_manager_id' => 'nullable|exists:users,id',
            'weight' => 'nullable|numeric|min:0',
        ]);

        if (isset($validated['progress'])) {
            if ($workItem->children()->count() > 0) {
                unset($validated['progress']);
            } else {
                $validated['progress'] = (int) $validated['progress'];
            }
        }

        $validated['is_active'] = $validated['status'] !== 'cancelled';

        $oldData = $workItem->getOriginal();

        $workItem->update($validated);

        if ($workItem->wasChanged('status')) {
            $this->cascadeStatus($workItem, $workItem->status);
        }

        if ($workItem->wasChanged('weight') || $workItem->wasChanged('parent_id') || $workItem->wasChanged('progress')) {
            if ($workItem->parent) {
                $workItem->parent->recalculateProgress();
            }
        }

        $this->logActivity('UPDATE', $workItem, $oldData, $workItem->getChanges());
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

    public function updateProgress(Request $request, WorkItem $workItem)
    {
        // ✅ เพิ่มการจำกัดนามสกุลไฟล์
        $request->validate([
            'progress' => 'required|integer|min:0|max:100',
            'comment' => 'required|string',
            'attachments.*' => 'nullable|file|mimes:pdf,doc,docx,ppt,pptx,jpg,jpeg,png|max:20480',
        ], [
            'attachments.*.mimes' => 'ไฟล์แนบต้องเป็น PDF, Word, Excel, PowerPoint หรือรูปภาพเท่านั้น'
        ]);

        if ($workItem->children()->count() > 0) {
            return back()->withErrors(['progress' => 'รายการนี้มีการคำนวณความคืบหน้าอัตโนมัติจากงานย่อย ไม่สามารถแก้ไขด้วยตนเองได้']);
        }

        $oldProgress = $workItem->progress;
        $newProgress = $request->progress;

        $workItem->update(['progress' => $newProgress]);

        $commentBody = $request->comment . "\n(ปรับความคืบหน้า: {$oldProgress}% ➝ {$newProgress}%)";
        $workItem->comments()->create([
            'user_id' => auth()->id(),
            'body' => $commentBody,
        ]);

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('attachments', 'public');
                $workItem->attachments()->create([
                    'user_id' => auth()->id(),
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'file_type' => $file->getClientMimeType(),
                    'file_size' => $file->getSize(),
                    'category' => 'progress_update'
                ]);
            }
        }

        $workItem->recalculateProgress();

        if ($workItem->parent) {
            $workItem->parent->recalculateProgress();
        }

        $this->logActivity('UPDATE_PROGRESS', $workItem, ['progress' => $oldProgress], ['progress' => $newProgress]);
        $this->clearRelatedCache($workItem);

        return back()->with('success', 'อัปเดตความคืบหน้าเรียบร้อยแล้ว');
    }

    public function move(Request $request, WorkItem $workItem)
    {
        $validated = $request->validate(['parent_id' => 'nullable|exists:work_items,id']);
        $newParentId = $validated['parent_id'];

        if ($newParentId == $workItem->id) return back()->withErrors(['parent_id' => 'ไม่สามารถย้ายงานไปหาตัวเองได้']);
        if ($newParentId && in_array($newParentId, $this->getDescendantIds($workItem))) return back()->withErrors(['parent_id' => 'ไม่สามารถย้ายไปอยู่ใต้ลูกหลานตัวเองได้']);

        $oldParent = $workItem->parent;
        $oldData = $workItem->getOriginal();

        // อัปเดตและเซฟ
        $workItem->update(['parent_id' => $newParentId]);
        $workItem->refresh(); // ✅ ดึงข้อมูลล่าสุดจาก DB ทันที

        $this->logActivity('MOVE', $workItem, $oldData, $workItem->getChanges());

        $this->clearRelatedCache($workItem);
        if ($oldParent) {
            $this->clearRelatedCache($oldParent);
            $oldParent->recalculateProgress(); // ✅ กระตุ้นแม่เดิม
        }
        if ($workItem->parent) {
            $this->clearRelatedCache($workItem->parent);
            $workItem->parent->recalculateProgress(); // ✅ กระตุ้นแม่ใหม่
        }

        return back()->with('success', 'ย้ายงานเรียบร้อยแล้ว (อัปเดต % ทันที)');
    }

    public function getTree()
    {
        $recursiveLoad = function ($q) {
            $q->orderBy('name', 'asc');
        };

        $relations = [];
        $depth = 'children';
        for ($i = 0; $i < 10; $i++) {
            $relations[$depth] = $recursiveLoad;
            $depth .= '.children';
        }

        $tree = WorkItem::where('type', 'strategy')
            ->with($relations)
            ->orderBy('name', 'asc')
            ->get();

        return $tree->sortBy('name', SORT_NATURAL)->values();
    }

    public function destroy(WorkItem $workItem)
    {
        $parent = $workItem->parent;

        $this->logActivity('DELETE', $workItem, $workItem->toArray(), []);
        $this->clearRelatedCache($workItem);

        // ✅ ลบงานย่อยที่อยู่ภายใต้ทั้งหมด (Recursive)
        $this->cascadeDelete($workItem);

        if ($parent) {
            $parent->recalculateProgress();
        }

        return back()->with('success', 'ลบรายการและงานย่อยทั้งหมดสำเร็จ');
    }

    private function cascadeDelete($item)
    {
        foreach ($item->children as $child) {
            $this->cascadeDelete($child);
        }
        $item->delete(); // Soft Delete
    }

    private function clearRelatedCache($workItem)
    {
        Cache::forget('dashboard_hierarchy');
        Cache::forget('dashboard_s_curve');

        // ✅ เพิ่ม 2 บรรทัดนี้เพื่อให้แน่ใจว่าล้าง Cache ต้นไม้ทั้งหมดทิ้งเวลาอัปเดต/ย้าย
        Cache::forget('strategies_index');
        Cache::forget('strategies_index_v2');

        Cache::forget("work_item_{$workItem->id}_s_curve");
        if ($workItem->parent_id) {
            Cache::forget("work_item_{$workItem->parent_id}_s_curve");
        }
    }

    private function logActivity($action, $model, $oldData = [], $changes = [])
    {
        // โค้ด logActivity เดิม...
        $relationMap = [
            'project_manager_id' => ['model' => User::class, 'label' => 'ผู้ดูแล (PM)'],
            'parent_id' => ['model' => WorkItem::class, 'label' => 'งานภายใต้'],
            'division_id' => ['model' => Division::class, 'label' => 'กอง'],
            'department_id' => ['model' => Department::class, 'label' => 'แผนก'],
        ];

        $fieldLabels = [
            'name' => 'ชื่อรายการ',
            'description' => 'รายละเอียด',
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
            foreach ($changes as $key => $newValue) {
                if ($key === 'updated_at') continue;

                $oldValue = $oldData[$key] ?? null;
                $label = $fieldLabels[$key] ?? $key;

                if (array_key_exists($key, $relationMap)) {
                    $config = $relationMap[$key];
                    $label = $config['label'];
                    $oldName = '-';
                    if ($oldValue) {
                        $oldModel = $config['model']::find($oldValue);
                        $oldName = $oldModel ? $oldModel->name : $oldValue;
                    }
                    $newName = '-';
                    if ($newValue) {
                        $newModel = $config['model']::find($newValue);
                        $newName = $newModel ? $newModel->name : $newValue;
                    }
                    $logChanges['before'][$label] = $oldName;
                    $logChanges['after'][$label] = $newName;
                } else {
                    $logChanges['before'][$label] = $oldValue;
                    $logChanges['after'][$label] = $newValue;
                }
            }
        }

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

    private function cascadeStatus($item, $newStatus)
    {
        $isActive = $newStatus !== 'cancelled';
        $children = $item->children;

        foreach ($children as $child) {
            $child->update([
                'status' => $newStatus,
                'is_active' => $isActive
            ]);
            $this->cascadeStatus($child, $newStatus);
        }
    }

    private function getDescendantIds($item)
    {
        $ids = [];
        $children = $item->children;
        foreach ($children as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $this->getDescendantIds($child));
        }
        return $ids;
    }

    public function show(WorkItem $workItem)
    {
        // ✅ โหลด Relations ตัวใหม่ที่เพิ่งสร้าง (milestones, workType)
        $workItem->load([
            'parent.parent.parent',
            'attachments.uploader',
            'issues.user',
            'milestones', // 👈 เพิ่มตรงนี้
            'workType',   // 👈 เพิ่มตรงนี้
            'children' => function($q) {
                $q->orderBy('order_index')->with(['attachments', 'projectManager']);
            },
            'children.children' => function($q) { $q->orderBy('order_index'); },
            'children.children.children',
            'division',
            'department',
            'projectManager'
        ]);

        $relatedIds = collect([$workItem->id])->merge(collect($workItem->children)->pluck('id'))->unique()->toArray();
        $logs = AuditLog::with('user')
            ->where(function($q) use ($relatedIds) { $q->where('model_type', 'WorkItem')->whereIn('model_id', $relatedIds); })
            ->orWhere(function($q) use ($workItem) { $q->whereIn('model_type', ['Attachment', 'Issue'])->where(function($sq) use ($workItem) { $sq->where('changes->work_item_id', $workItem->id)->orWhere('changes->after->work_item_id', $workItem->id); }); })
            ->get()
            ->map(function ($item) {
                $item->timeline_type = 'log';
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
        $paginatedTimeline->withQueryString();

        $divisions = Division::with('departments')->orderBy('name')->get();
        $workItemTypes = WorkItemType::orderBy('level_order')->get();

        return Inertia::render('Project/Detail', [
            'item' => $workItem,
            'historyLogs' => $paginatedTimeline,
            'divisions' => $divisions,
            'workItemTypes' => $workItemTypes // ✅ ส่งให้ Vue เผื่อไว้ใช้
        ]);
    }

    public function list(Request $request, $type) { return $this->renderList($request, $type); }
    public function index(Request $request) {
        return $this->renderList($request, $request->query('type'));
    }
        public function strategies() {
        // 🚀 ดึงข้อมูล Tree View
        $strategies = Cache::remember('strategies_index_v2', 3600, function () {
            $recursiveLoad = function ($q) {
                $q->orderBy('name', 'asc')
                  ->with('workType')
                  ->withCount(['issues as issue_count' => function($i) {
                      $i->where('status', '!=', 'resolved');
                  }]);
            };

            $relations = ['workType'];
            $depth = 'children';
            for ($i = 0; $i < 10; $i++) {
                $relations[$depth] = $recursiveLoad;
                $depth .= '.children';
            }

            $rawStrategies = WorkItem::whereNull('parent_id')
                ->with($relations)
                ->withCount(['issues as strategy_issue_count' => function($i) {
                     $i->where('status', '!=', 'resolved');
                }])
                ->get();

            return $rawStrategies->sortBy(function($item) {
                return $item->name;
            }, SORT_NATURAL)->values();
        });

        // ✅ ดึงข้อมูลประกอบสำหรับ Modal แบบเต็ม
        $workItemTypes = \App\Models\WorkItemType::orderBy('level_order')->get();
        $divisions = Division::with('departments')->orderBy('name')->get();

        $parentOptions = WorkItem::with('workType')
            ->select('id', 'name', 'type', 'work_item_type_id')
            ->get()
            ->map(function($item) {
                $item->type_label = $item->workType ? $item->workType->name : $item->type;
                return $item;
            })
            ->sortBy('type_label')->values();

        return Inertia::render('Strategy/Index', [
            'strategies' => $strategies,
            'workItemTypes' => $workItemTypes,
            'divisions' => $divisions,         // ส่งไปให้ Modal
            'parentOptions' => $parentOptions  // ส่งไปให้ Modal
        ]);
    }

    public function ganttData(WorkItem $workItem) {
        try {
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

        return User::where('name', 'ilike', "%{$search}%")
            ->where(function($q) {
                $q->where('is_pm', true)
                  ->orWhereIn('role', ['pm', 'project_manager']);
            })
            ->limit(10)
            ->get(['id', 'name']);
    }

    // -------------------------------------------------------------------------
    // ✨ ฟังก์ชันสำหรับรับค่าจาก Checkbox (Bulk Action)
    // -------------------------------------------------------------------------
    public function bulkAction(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:work_items,id',
            'action' => 'required|string|in:delete,update_progress,update_general', // ✅ เพิ่ม update_general

            // Validation สำหรับการแก้ไขข้อมูล (Nullable เพื่อรองรับการแก้บางค่า)
            'description' => 'nullable|string',
            'division_id' => 'nullable|exists:divisions,id',
            'department_id' => 'nullable|exists:departments,id',
            'project_manager_id' => 'nullable|exists:users,id',
            'type' => 'nullable|string',
            'weight' => 'nullable|numeric|min:0',
            'bulk_status_mode' => 'nullable|in:no_change,active,cancelled', // โหมดเปลี่ยนสถานะ

            // Validation สำหรับ Progress
            'progress' => 'nullable|integer|min:0|max:100',
            'comment' => 'nullable|string'
        ]);

        $items = WorkItem::whereIn('id', $request->ids)->get();
        $parentsToUpdate = collect();

        foreach ($items as $item) {
            if ($item->parent_id) $parentsToUpdate->push($item->parent_id);

            // 1. ลบรายการ
            if ($request->action === 'delete') {
                $this->cascadeDelete($item);
            }
            // 2. อัปเดตข้อมูลทั่วไป (Bulk Edit)
            elseif ($request->action === 'update_general') {
                $data = [];
                // เช็คว่ามีการส่งค่ามาไหม ถ้าส่งมาให้อัปเดต
                if ($request->filled('description')) $data['description'] = $request->description;
                if ($request->filled('division_id')) $data['division_id'] = $request->division_id;
                if ($request->filled('department_id')) $data['department_id'] = $request->department_id;
                if ($request->filled('project_manager_id')) $data['project_manager_id'] = $request->project_manager_id;
                if ($request->filled('type')) $data['type'] = $request->type;
                if ($request->filled('weight')) $data['weight'] = $request->weight;

                // จัดการสถานะ (Active / Cancelled)
                if ($request->bulk_status_mode === 'cancelled') {
                    $data['status'] = 'cancelled';
                    $data['is_active'] = false;
                    $this->cascadeStatus($item, 'cancelled');
                } elseif ($request->bulk_status_mode === 'active') {
                    $data['status'] = 'in_active'; // รีเซ็ตเป็นรอก่อน เดี๋ยว Auto Calc จะปรับให้เอง
                    $data['is_active'] = true;
                    $this->cascadeStatus($item, 'in_active');
                }

                if (!empty($data)) {
                    $item->update($data);
                }
            }
            // 3. อัปเดตความคืบหน้า (เฉพาะรายการที่ไม่มีลูก)
            elseif ($request->action === 'update_progress' && isset($request->progress)) {
                if ($item->children()->count() == 0) {
                    $oldProgress = $item->progress;
                    $item->update(['progress' => $request->progress]);

                    if ($request->comment) {
                        $item->comments()->create([
                            'user_id' => auth()->id(),
                            'body' => $request->comment . "\n(ปรับความคืบหน้าแบบกลุ่ม: {$oldProgress}% ➝ {$request->progress}%)",
                        ]);
                    }
                }
            }
        }

        // กระตุ้นการคำนวณ % ให้แม่ของทุกรายการที่ถูกแก้
        foreach ($parentsToUpdate->unique() as $parentId) {
            $parent = WorkItem::find($parentId);
            if ($parent) $parent->recalculateProgress();
        }

        return back()->with('success', 'จัดการข้อมูลที่เลือกเรียบร้อยแล้ว');
    }
}
