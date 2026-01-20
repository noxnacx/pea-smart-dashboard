<?php

namespace App\Http\Controllers;

use App\Models\WorkItem;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Carbon\Carbon;

class WorkItemController extends Controller
{
    public function index()
    {
        $strategies = WorkItem::whereNull('parent_id')
            ->with('allChildren')
            ->orderBy('order_index')
            ->get();

        return Inertia::render('Dashboard/AdminDashboard', [
            'hierarchy' => $strategies,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'parent_id' => 'nullable|exists:work_items,id',
            'name' => 'required|string|max:255',
            'type' => 'required|string',
            'status' => 'nullable|string',
            'progress' => 'nullable|integer|min:0|max:100',
            'budget' => 'nullable|numeric',
            'planned_start_date' => 'nullable|date',
            'planned_end_date' => 'nullable|date',
        ]);

        $validated['progress'] = $validated['progress'] ?? 0;
        $validated['budget'] = $validated['budget'] ?? 0;
        $validated['status'] = $validated['status'] ?? 'pending';
        $validated['responsible_user_id'] = auth()->id();

        WorkItem::create($validated);

        return Redirect::route('dashboard')->with('success', 'เพิ่มงานเรียบร้อย');
    }

    public function update(Request $request, WorkItem $workItem)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'progress' => 'nullable|integer|min:0|max:100',
            'status' => 'required|string',
            'budget' => 'nullable|numeric',
            'planned_start_date' => 'nullable|date',
            'planned_end_date' => 'nullable|date',
            'type' => 'required|string',
        ]);

        if (isset($validated['progress']) && is_null($validated['progress'])) {
            $validated['progress'] = 0;
        }

        // Observer จะบันทึก Log อัตโนมัติ
        $workItem->update($validated);

        if ($workItem->parent) {
            $workItem->parent->recalculateProgress();
        }

        return Redirect::route('dashboard')->with('success', 'อัปเดตข้อมูลสำเร็จ');
    }

    public function destroy(WorkItem $workItem)
    {
        $workItem->delete();
        return Redirect::route('dashboard')->with('success', 'ลบงานสำเร็จ');
    }

    public function show(WorkItem $workItem)
    {
        // 1. โหลดข้อมูลความสัมพันธ์
        $workItem->load([
            'parent',
            'attachments.uploader',
            'children' => function($q) { $q->orderBy('order_index')->with('attachments'); },
            'children.children' => function($q) { $q->orderBy('order_index'); },
            'children.children.children'
        ]);

        // 2. คำนวณ S-Curve (เหมือนเดิม)
        $months = [];
        $plannedData = [];
        $actualData = [];

        $startDate = $workItem->planned_start_date
            ? $workItem->planned_start_date->copy()->startOfMonth()
            : now()->startOfYear();

        $endDate = $workItem->planned_end_date
            ? $workItem->planned_end_date->copy()->endOfMonth()
            : now()->endOfYear();

        if ($endDate->lt($startDate)) $endDate = $startDate->copy()->addMonths(1);

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

        $currentDate = $startDate->copy();
        while ($currentDate->lte($endDate)) {
            $thaiMonths = [1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.', 4 => 'เม.ย.', 5 => 'พ.ค.', 6 => 'มิ.ย.', 7 => 'ก.ค.', 8 => 'ส.ค.', 9 => 'ก.ย.', 10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.'];
            $months[] = $thaiMonths[$currentDate->month] . ' ' . substr($currentDate->year + 543, -2);
            $calcDate = $currentDate->copy()->endOfMonth();

            $pv = $allChildren->sum(function($item) use ($calcDate) {
                if (!$item->planned_start_date || !$item->planned_end_date || $item->budget <= 0) return 0;
                if ($calcDate->lt($item->planned_start_date)) return 0;
                if ($calcDate->gt($item->planned_end_date)) return $item->budget;
                $totalDays = $item->planned_start_date->diffInDays($item->planned_end_date) + 1;
                $passedDays = $item->planned_start_date->diffInDays($calcDate) + 1;
                return $item->budget * ($passedDays / max(1, $totalDays));
            });
            $plannedData[] = round($pv, 2);

            if ($calcDate->lte(now()->endOfMonth())) {
                $ev = $allChildren->sum(fn($item) => $item->budget * ($item->progress / 100));
                $actualData[] = round($ev, 2);
            }
            $currentDate->addMonth();
        }

        // 3. *** ส่วนที่แก้ไข: Timeline รวมศูนย์ (Logs + Comments) ***

        // 3.1 ดึง Audit Logs
        $logs = AuditLog::with('user')
            ->where(function($q) use ($workItem) {
                $q->where('model_type', 'WorkItem')->where('model_id', $workItem->id);
            })
            ->orWhere(function($q) use ($workItem) {
                $q->where('model_type', 'Attachment')
                  ->where(function($sq) use ($workItem) {
                      $sq->where('changes->work_item_id', $workItem->id)
                         ->orWhere('changes->after->work_item_id', $workItem->id);
                  });
            })
            ->get()
            ->map(function ($item) {
                $item->timeline_type = 'log'; // แปะป้ายว่าเป็น Log
                return $item;
            });

        // 3.2 ดึง Comments
        $comments = $workItem->comments()
            ->with('user')
            ->get()
            ->map(function ($item) {
                $item->timeline_type = 'comment'; // แปะป้ายว่าเป็น Comment
                return $item;
            });

        // 3.3 รวมร่างและเรียงตามเวลา (ใหม่ล่าสุดขึ้นก่อน)
        $timeline = $logs->concat($comments)->sortByDesc('created_at')->values();

        return Inertia::render('Project/Detail', [
            'item' => $workItem,
            'historyLogs' => $timeline, // ส่ง Timeline รวมไป
            'chartData' => [
                'categories' => $months,
                'planned' => $plannedData,
                'actual' => $actualData
            ]
        ]);
    }

    public function list(Request $request, $type)
    {
        $query = WorkItem::where('type', $type);

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
