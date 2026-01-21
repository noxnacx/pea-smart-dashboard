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
    // *** ฟังก์ชัน index() ไม่ต้องมีแล้ว เพราะใช้ DashboardController ***

    public function store(Request $request)
    {
        $validated = $request->validate([
            'parent_id' => 'nullable|exists:work_items,id',
            'name' => 'required|string|max:255',
            'type' => 'required|string',
            'status' => 'nullable|string',
            'progress' => 'nullable|numeric|min:0|max:100', // แก้เป็น numeric เพื่อรับค่าทศนิยมได้
            'budget' => 'nullable|numeric',
            'planned_start_date' => 'nullable|date',
            'planned_end_date' => 'nullable|date',
        ]);

        // แปลงเป็น int (จำนวนเต็ม) ก่อนบันทึก แก้ปัญหา PostgreSQL Error
        $validated['progress'] = (int) ($validated['progress'] ?? 0);
        $validated['budget'] = $validated['budget'] ?? 0;
        $validated['status'] = $validated['status'] ?? 'pending';
        $validated['responsible_user_id'] = auth()->id();

        WorkItem::create($validated);

        // ใช้ back() เพื่อให้อยู่หน้าเดิม ไม่เด้งกลับ Dashboard
        return back()->with('success', 'เพิ่มงานเรียบร้อย');
    }

    public function update(Request $request, WorkItem $workItem)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'progress' => 'nullable|numeric|min:0|max:100', // แก้เป็น numeric
            'status' => 'required|string',
            'budget' => 'nullable|numeric',
            'planned_start_date' => 'nullable|date',
            'planned_end_date' => 'nullable|date',
            'type' => 'required|string',
        ]);

        // แปลงเป็น int ก่อนบันทึก
        if (isset($validated['progress'])) {
            $validated['progress'] = (int) $validated['progress'];
        } else {
            $validated['progress'] = 0;
        }

        $workItem->update($validated);

        if ($workItem->parent) {
            $workItem->parent->recalculateProgress();
        }

        // ใช้ back() เพื่อให้อยู่หน้าเดิม
        return back()->with('success', 'อัปเดตข้อมูลสำเร็จ');
    }

    public function destroy(WorkItem $workItem)
    {
        $workItem->delete();
        // ใช้ back() เพื่อให้อยู่หน้าเดิม
        return back()->with('success', 'ลบงานสำเร็จ');
    }

    // หน้า Detail รายละเอียดโครงการ
    public function show(WorkItem $workItem)
    {
        // 1. โหลดข้อมูล
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

        // 2. คำนวณ S-Curve
        $months = [];
        $plannedData = [];
        $actualData = [];
        $startDate = $workItem->planned_start_date ? $workItem->planned_start_date->copy()->startOfMonth() : now()->startOfYear();
        $endDate = $workItem->planned_end_date ? $workItem->planned_end_date->copy()->endOfMonth() : now()->endOfYear();
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

        // 3. Timeline รวมศูนย์
        $logs = AuditLog::with('user')
            ->where(function($q) use ($workItem) {
                $q->where('model_type', 'WorkItem')->where('model_id', $workItem->id);
            })
            ->orWhere(function($q) use ($workItem) {
                 $q->whereIn('model_type', ['Attachment', 'Issue'])
                   ->where(function($sq) use ($workItem) {
                       $sq->where('changes->work_item_id', $workItem->id)
                          ->orWhere('changes->after->work_item_id', $workItem->id);
                   });
            })
            ->get()
            ->map(function ($item) { $item->timeline_type = 'log'; return $item; });

        $comments = $workItem->comments()->with('user')->get()
            ->map(function ($item) { $item->timeline_type = 'comment'; return $item; });

        $timeline = $logs->concat($comments)->sortByDesc('created_at')->values();

        return Inertia::render('Project/Detail', [
            'item' => $workItem,
            'historyLogs' => $timeline,
            'chartData' => [
                'categories' => $months,
                'planned' => $plannedData,
                'actual' => $actualData
            ]
        ]);
    }

    public function list(Request $request, $type)
    {
        $query = WorkItem::where('type', $type)->with('issues'); // เพิ่ม with('issues') สำหรับจุดแจ้งเตือน
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
