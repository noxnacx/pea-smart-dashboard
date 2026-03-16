<?php

namespace App\Http\Controllers;

use App\Models\StrategicAlignment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\WorkItem;

class StrategicAlignmentController extends Controller
{
    public function index(Request $request)
    {
        $query = StrategicAlignment::query();

        // 🚀 ระบบค้นหาแบบ Case-Insensitive (รองรับ PostgreSQL โดยใช้ ILIKE)
        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                // Laravel จะใส่ "key" (Double Quote) ให้อัตโนมัติ ป้องกัน Error คำสงวน
                // และ ILIKE จะจัดการค้นหาแบบไม่สนพิมพ์เล็ก-พิมพ์ใหญ่ให้เอง
                $q->where('key', 'ILIKE', "%{$search}%")
                  ->orWhere('description', 'ILIKE', "%{$search}%");
            });
        }

        // เรียงลำดับ KEY ตามตัวอักษร (A-Z) และแบ่งหน้าละ 10 รายการ
        $alignments = $query->orderBy('key', 'asc')->paginate(10)->withQueryString();

        return Inertia::render('StrategicAlignment/Index', [
            'alignments' => $alignments,
            'filters' => $request->only('search'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'key' => 'required|string|unique:strategic_alignments,key',
            'description' => 'required|string',
        ]);
        StrategicAlignment::create($validated);
        return back()->with('success', 'เพิ่มข้อมูลยุทธศาสตร์สำเร็จ');
    }

    public function update(Request $request, StrategicAlignment $strategicAlignment)
    {
        $validated = $request->validate([
            'key' => 'required|string|unique:strategic_alignments,key,' . $strategicAlignment->id,
            'description' => 'required|string',
        ]);
        $strategicAlignment->update($validated);
        return back()->with('success', 'แก้ไขข้อมูลยุทธศาสตร์สำเร็จ');
    }

    public function destroy(StrategicAlignment $strategicAlignment)
    {
        $strategicAlignment->delete();
        return back()->with('success', 'ลบข้อมูลสำเร็จ');
    }
    public function show(Request $request, \App\Models\StrategicAlignment $strategicAlignment)
    {
        $searchKey = "[{$strategicAlignment->key}]";

        $query = \App\Models\WorkItem::with(['workType', 'projectManager', 'division'])
            ->where('alignment', 'ILIKE', "%{$searchKey}%");

        // ✅ ระบบกรองข้อมูล (Filters)
        if ($request->filled('search')) $query->where('name', 'ILIKE', "%{$request->search}%");
        if ($request->filled('type')) $query->where('type', $request->type);
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('division_id')) $query->where('division_id', $request->division_id);

        // ✅ กรองด้วย % ความคืบหน้า
        if ($request->filled('progress')) {
            $prog = $request->progress;
            if ($prog === '0') $query->where('progress', 0);
            elseif ($prog === '1-25') $query->whereBetween('progress', [1, 25]);
            elseif ($prog === '26-50') $query->whereBetween('progress', [26, 50]);
            elseif ($prog === '51-75') $query->whereBetween('progress', [51, 75]);
            elseif ($prog === '76-99') $query->whereBetween('progress', [76, 99]);
            elseif ($prog === '100') $query->where('progress', 100);
        }

        // ✅ เปลี่ยนเป็น 10 รายการต่อหน้า
        $workItems = $query->orderBy('created_at', 'desc')->paginate(10)->withQueryString();

        // ส่งข้อมูลตัวเลือกไปทำ Dropdown
        $divisions = \App\Models\Division::orderBy('name')->get(['id', 'name']);
        $workItemTypes = \App\Models\WorkItemType::orderBy('level_order')->get(['key', 'name', 'icon']);

        return inertia('StrategicAlignment/Show', [
            'modelData' => $strategicAlignment,
            'workItems' => $workItems,
            'divisions' => $divisions,
            'workItemTypes' => $workItemTypes,
            'filters' => $request->only(['search', 'type', 'status', 'division_id', 'progress']),
        ]);
    }
}
