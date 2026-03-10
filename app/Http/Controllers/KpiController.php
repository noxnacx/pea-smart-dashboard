<?php

namespace App\Http\Controllers;

use App\Models\Kpi;
use App\Models\WorkItemType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class KpiController extends Controller
{
    public function index(Request $request)
    {
        // ดึง KPI พร้อม Tag ประเภทงานที่ผูกไว้
        $query = Kpi::with('workItemTypes');

        // ระบบค้นหา
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('name', 'ILIKE', "%{$search}%")
                  ->orWhere('description', 'ILIKE', "%{$search}%");
        }

        $kpis = $query->orderBy('id', 'desc')->paginate(10)->withQueryString();

        // ดึงประเภทงานทั้งหมดไปให้หน้า Vue ทำเป็น Checkbox ให้ Admin เลือกผูก
        $workItemTypes = WorkItemType::orderBy('level_order', 'asc')->get();

        return Inertia::render('Kpi/Index', [
            'kpis' => $kpis,
            'workItemTypes' => $workItemTypes,
            'filters' => $request->only('search'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'description' => 'nullable|string',
            'work_item_type_keys' => 'nullable|array', // รับค่า Array ของ Tag (เช่น ['plan', 'project'])
        ]);

        DB::beginTransaction();
        try {
            $kpi = Kpi::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
            ]);

            // บันทึก Tag ลงตาราง Pivot
            $this->syncWorkItemTypes($kpi->id, $validated['work_item_type_keys'] ?? []);

            DB::commit();
            return back()->with('success', 'เพิ่มตัวชี้วัดสำเร็จ');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
        }
    }

    public function update(Request $request, Kpi $kpi)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'description' => 'nullable|string',
            'work_item_type_keys' => 'nullable|array',
        ]);

        DB::beginTransaction();
        try {
            $kpi->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
            ]);

            // อัปเดต Tag ลงตาราง Pivot
            $this->syncWorkItemTypes($kpi->id, $validated['work_item_type_keys'] ?? []);

            DB::commit();
            return back()->with('success', 'แก้ไขตัวชี้วัดสำเร็จ');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
        }
    }

    public function destroy(Kpi $kpi)
    {
        $kpi->delete(); // ตาราง Pivot จะถูกลบอัตโนมัติเพราะเราเซ็ต onDelete('cascade') ไว้ใน Migration
        return back()->with('success', 'ลบตัวชี้วัดสำเร็จ');
    }

    /**
     * ฟังก์ชัน Helper สำหรับอัปเดตตาราง Pivot อย่างปลอดภัย
     */
    private function syncWorkItemTypes($kpiId, $keysArray)
    {
        // 1. ลบ Tag เก่าทิ้งทั้งหมดก่อน
        DB::table('kpi_work_item_type')->where('kpi_id', $kpiId)->delete();

        // 2. ถ้ามี Tag ใหม่ส่งมา ให้เพิ่มเข้าไป
        if (!empty($keysArray)) {
            $pivotData = collect($keysArray)->map(function ($key) use ($kpiId) {
                return [
                    'kpi_id' => $kpiId,
                    'work_item_type_key' => $key,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })->toArray();

            DB::table('kpi_work_item_type')->insert($pivotData);
        }
    }

    // =========================================================================
    // API สำหรับให้หน้า Detail ค้นหา KPI ตามประเภทงาน
    // =========================================================================
    public function searchApi(Request $request)
    {
        $search = $request->query('search');
        $workItemType = $request->query('work_item_type'); // เช่น plan, project

        $query = Kpi::query();

        // 1. ค้นหาจากชื่อ หรือ รายละเอียด (ไม่สนพิมพ์เล็ก-ใหญ่)
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'ILIKE', "%{$search}%")
                  ->orWhere('description', 'ILIKE', "%{$search}%");
            });
        }

        // 2. กรองให้แสดงเฉพาะ KPI ที่ผูก Tag ตรงกับประเภทงานของหน้านี้
        if ($workItemType) {
            $query->whereHas('workItemTypes', function($q) use ($workItemType) {
                $q->where('work_item_type_key', $workItemType);
            });
        }

        // ดึงไปแสดง 20 รายการ
        $kpis = $query->limit(20)->get(['id', 'name', 'description']);

        return response()->json($kpis);
    }
}
