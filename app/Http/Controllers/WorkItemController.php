<?php

namespace App\Http\Controllers;

use App\Models\WorkItem;
use App\Models\AuditLog;
use App\Models\Comment; // ✅ เรียกใช้ Model Comment
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Pagination\LengthAwarePaginator; // ✅ ใช้สำหรับแบ่งหน้า
use Inertia\Inertia;
use Carbon\Carbon;

class WorkItemController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'parent_id' => 'nullable|exists:work_items,id',
            'name' => 'required|string|max:255',
            'type' => 'required|string',
            'status' => 'nullable|string',
            'progress' => 'nullable|numeric|min:0|max:100',
            'budget' => 'nullable|numeric',
            'planned_start_date' => 'nullable|date',
            'planned_end_date' => 'nullable|date',
        ]);

        $validated['progress'] = (int) ($validated['progress'] ?? 0);
        $validated['budget'] = $validated['budget'] ?? 0;
        $validated['status'] = $validated['status'] ?? 'pending';
        $validated['responsible_user_id'] = auth()->id();

        WorkItem::create($validated);

        return back()->with('success', 'เพิ่มงานเรียบร้อย');
    }

    public function update(Request $request, WorkItem $workItem)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'progress' => 'nullable|numeric|min:0|max:100',
            'status' => 'required|string',
            'budget' => 'nullable|numeric',
            'planned_start_date' => 'nullable|date',
            'planned_end_date' => 'nullable|date',
            'type' => 'required|string',
        ]);

        if (isset($validated['progress'])) {
            $validated['progress'] = (int) $validated['progress'];
        } else {
            $validated['progress'] = 0;
        }

        $workItem->update($validated);

        if ($workItem->parent) {
            $workItem->parent->recalculateProgress();
        }

        return back()->with('success', 'อัปเดตข้อมูลสำเร็จ');
    }

    public function destroy(WorkItem $workItem)
    {
        $workItem->delete();
        return back()->with('success', 'ลบงานสำเร็จ');
    }

    public function show(WorkItem $workItem)
    {
        // 1. โหลดข้อมูล (รวมลูกหลาน)
        $workItem->load([
            'parent.parent.parent',
            'attachments.uploader',
            'issues.user',
            'children' => function($q) {
                $q->orderBy('order_index')->with('attachments');
            },
            'children.children' => function($q) { $q->orderBy('order_index'); },
            'children.children.children'
        ]);

        // 2. คำนวณ S-Curve (Smart Budget Logic)
        $months = [];
        $plannedData = [];
        $actualData = [];
        $startDate = $workItem->planned_start_date ? $workItem->planned_start_date->copy()->startOfMonth() : now()->startOfYear();
        $endDate = $workItem->planned_end_date ? $workItem->planned_end_date->copy()->endOfMonth() : now()->endOfYear();
        if ($endDate->lt($startDate)) $endDate = $startDate->copy()->addMonths(1);

        // Flatten รวบรวม Item ทั้งหมดในโปรเจกต์นี้
        $allChildren = collect([$workItem]);
        $tempQueue = [$workItem];
        while(count($tempQueue) > 0) {
            $current = array_shift($tempQueue);
            if($current->children) {
                foreach($current->children as $child) {
                    $allChildren->push($child);
                    $tempQueue[] = $child;
                }
            }
        }

        // คัดเลือกเฉพาะ Item ที่มีงบและไม่ซ้ำซ้อน
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
            $pvPercent = ($pvMoney / $totalProjectBudget) * 100;
            $plannedData[] = round($pvPercent, 2);

            if ($calcDate->lte(now()->endOfMonth())) {
                $evMoney = $budgetItems->sum(fn($item) => $item->budget * ($item->progress / 100));
                $evPercent = ($evMoney / $totalProjectBudget) * 100;
                $actualData[] = round($evPercent, 2);
            }
            $currentDate->addMonth();
        }

        // 3. Timeline รวมศูนย์ (แก้ไขใหม่ ดึงลูกหลานด้วย + แก้บั๊ก SQL Comment)
        $relatedIds = $allChildren->pluck('id')->unique()->toArray();

        // 3.1 Audit Logs
        $logs = AuditLog::with('user')
            ->where(function($q) use ($relatedIds) {
                $q->where('model_type', 'WorkItem')->whereIn('model_id', $relatedIds);
            })
            ->orWhere(function($q) use ($workItem) {
                 $q->whereIn('model_type', ['Attachment', 'Issue'])
                   ->where(function($sq) use ($workItem) {
                       $sq->where('changes->work_item_id', $workItem->id)
                          ->orWhere('changes->after->work_item_id', $workItem->id);
                   });
            })
            ->get()
            ->map(function ($item) use ($allChildren) {
                $item->timeline_type = 'log';
                if ($item->model_type === 'WorkItem') {
                    $target = $allChildren->firstWhere('id', $item->model_id);
                    $item->target_name = $target ? $target->name : 'รายการที่ถูกลบ';
                }
                return $item;
            });

        // 3.2 Comments (✅ แก้ไขจุดที่ Error: ใช้ work_item_id แทน)
        $comments = Comment::with('user')
            ->whereIn('work_item_id', $relatedIds)
            ->get()
            ->map(function ($item) use ($allChildren) {
                $item->timeline_type = 'comment';
                $target = $allChildren->firstWhere('id', $item->work_item_id);
                $item->target_name = $target ? $target->name : '';
                return $item;
            });

        $timeline = $logs->concat($comments)->sortByDesc('created_at')->values();

        // 4. Pagination (Manual Paginator)
        $page = request()->get('page', 1);
        $perPage = 10; // ✅ แสดงหน้าละ 10 รายการ
        $total = $timeline->count();
        $paginatedItems = $timeline->slice(($page - 1) * $perPage, $perPage)->values();

        $paginatedTimeline = new LengthAwarePaginator(
            $paginatedItems,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return Inertia::render('Project/Detail', [
            'item' => $workItem,
            'historyLogs' => $paginatedTimeline,
            'chartData' => [
                'categories' => $months,
                'planned' => $plannedData,
                'actual' => $actualData
            ]
        ]);
    }

    public function list(Request $request, $type)
    {
        $query = WorkItem::where('type', $type)->with('issues');
        if ($request->filled('search')) $query->where('name', 'ilike', '%' . $request->search . '%');
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('year')) $query->whereYear('planned_start_date', $request->year);
        $sortField = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
        if(in_array($sortField, ['name', 'budget', 'progress', 'planned_start_date', 'created_at'])) {
            $query->orderBy($sortField, $sortDir);
        }
        $items = $query->paginate(10)->withQueryString();
        return Inertia::render('WorkItem/List', [
            'type' => $type,
            'items' => $items,
            'filters' => $request->all(['search', 'status', 'year', 'sort_by', 'sort_dir']),
        ]);
    }
}
