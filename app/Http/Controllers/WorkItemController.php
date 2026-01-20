<?php

namespace App\Http\Controllers;

use App\Models\WorkItem;
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

        // Fix null values
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

        // 1. อัปเดตข้อมูลหลัก
        $workItem->update($validated);

        // 2. บันทึก Log
        if ($request->has('progress')) {
            $workItem->logs()->create([
                'log_date' => now(),
                'progress' => $workItem->progress,
                'actual_cost' => 0,
                'created_by' => auth()->id(),
            ]);
        }

        // 3. สั่งให้คำนวณแม่ใหม่ (ถ้ามีแม่)
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
            'logs.uploader',
            'children' => function($q) { $q->orderBy('order_index')->with('attachments'); },
            'children.children' => function($q) { $q->orderBy('order_index'); },
            'children.children.children'
        ]);

        // 2. เตรียมข้อมูลสำหรับ S-Curve
        $months = [];
        $plannedData = [];
        $actualData = [];

        // หาจุดเริ่มต้นและจุดสิ้นสุดของกราฟ
        $startDate = $workItem->planned_start_date
            ? $workItem->planned_start_date->copy()->startOfMonth()
            : now()->startOfYear();

        $endDate = $workItem->planned_end_date
            ? $workItem->planned_end_date->copy()->endOfMonth()
            : now()->endOfYear();

        // ป้องกันกรณีวันเริ่มกับวันจบใกล้กันเกินไป
        if ($endDate->lt($startDate)) {
            $endDate = $startDate->copy()->addMonths(1);
        }

        // รวบรวมลูกหลานทั้งหมดเพื่อคำนวณ (Flatten Tree)
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

        // Loop สร้างแกน X
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            // สร้าง Label แกน X เป็นภาษาไทย
            $thaiMonths = [
                1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.', 4 => 'เม.ย.', 5 => 'พ.ค.', 6 => 'มิ.ย.',
                7 => 'ก.ค.', 8 => 'ส.ค.', 9 => 'ก.ย.', 10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.'
            ];
            $monthName = $thaiMonths[$currentDate->month];
            $yearThai = $currentDate->year + 543;
            $yearShort = substr($yearThai, -2);

            $months[] = "$monthName $yearShort";

            $calcDate = $currentDate->copy()->endOfMonth();

            // --- คำนวณ Planned Value (PV) ---
            $pv = $allChildren->sum(function($item) use ($calcDate) {
                if (!$item->planned_start_date || !$item->planned_end_date || $item->budget <= 0) return 0;

                if ($calcDate->lt($item->planned_start_date)) return 0;
                if ($calcDate->gt($item->planned_end_date)) return $item->budget;

                $totalDays = $item->planned_start_date->diffInDays($item->planned_end_date) + 1;
                $passedDays = $item->planned_start_date->diffInDays($calcDate) + 1;
                $percent = $passedDays / max(1, $totalDays);

                return $item->budget * $percent;
            });
            $plannedData[] = round($pv, 2);

            // --- คำนวณ Earned Value (EV) ---
            if ($calcDate->lte(now()->endOfMonth())) {
                $ev = $allChildren->sum(function($item) {
                   return $item->budget * ($item->progress / 100);
                });
                $actualData[] = round($ev, 2);
            }

            $currentDate->addMonth();
        }

        return Inertia::render('Project/Detail', [
            'item' => $workItem,
            'chartData' => [
                'categories' => $months,
                'planned' => $plannedData,
                'actual' => $actualData
            ]
        ]);
    }

    public function list(Request $request, $type)
    {
        // 1. เริ่ม Query โดยเลือกเฉพาะ Type ที่ต้องการ
        $query = WorkItem::where('type', $type);

        // 2. ตัวกรอง: ค้นหาจากชื่อ (Search)
        if ($request->filled('search')) {
            // ใช้ ilike เพื่อค้นหาแบบ Case-Insensitive (PostgreSQL)
            $query->where('name', 'ilike', '%' . $request->search . '%');
        }

        // 3. ตัวกรอง: สถานะ (Status)
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // 4. ตัวกรอง: ปีงบประมาณ (Year)
        if ($request->filled('year')) {
            $query->whereYear('planned_start_date', $request->year);
        }

        // 5. การเรียงลำดับ (Sorting)
        $sortField = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');

        if(in_array($sortField, ['name', 'budget', 'progress', 'planned_start_date', 'created_at'])) {
            $query->orderBy($sortField, $sortDir);
        }

        // 6. ดึงข้อมูลแบบแบ่งหน้า (Pagination)
        $items = $query->paginate(10)->withQueryString();

        return Inertia::render('WorkItem/List', [
            'type' => $type,
            'items' => $items,
            'filters' => $request->all(['search', 'status', 'year', 'sort_by', 'sort_dir']),
        ]);
    }
}
