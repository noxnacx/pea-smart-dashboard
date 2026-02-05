<?php

namespace App\Http\Controllers;

use App\Models\ProjectManager;
use App\Models\AuditLog; // ✅ เพิ่ม AuditLog
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache; // ✅ เพิ่ม Cache Facade

class ProjectManagerController extends Controller
{
    // =========================================================================
    // 1. แสดงหน้ารายชื่อ PM ทั้งหมด
    // =========================================================================
    public function index(Request $request)
    {
        $search = $request->input('search');
        $page = $request->input('page', 1);

        // สร้าง Cache Key
        $cacheKey = "pm_list_{$search}_page_{$page}";

        // 🚀 CACHE LOGIC: เก็บ 5 นาที (300 วินาที) ใช้ Tags เพื่อให้สั่งล้างง่าย
        $pms = Cache::tags(['project_managers'])->remember($cacheKey, 300, function () use ($search) {
            $query = ProjectManager::withCount('workItems') // นับจำนวนโครงการ
                ->withSum('workItems', 'budget'); // รวมงบประมาณ

            if ($search) {
                $query->where('name', 'ilike', '%' . $search . '%');
            }

            // เรียงตามจำนวนโครงการ (ใครงานเยอะขึ้นก่อน)
            return $query->orderByDesc('work_items_count')
                         ->paginate(12)
                         ->withQueryString();
        });

        return Inertia::render('ProjectManager/Index', [
            'pms' => $pms,
            'filters' => $request->only(['search'])
        ]);
    }

    // =========================================================================
    // 2. แสดงหน้า Portfolio รายบุคคล
    // =========================================================================
    public function show($id)
    {
        // 🚀 CACHE LOGIC: เก็บข้อมูลหน้า Profile 5 นาที
        // (เพราะมีการคำนวณ Stats และวนลูป Issues ซึ่งกินทรัพยากร)
        $data = Cache::remember("pm_profile_{$id}", 300, function () use ($id) {
            $pm = ProjectManager::withCount('workItems')
                ->withSum('workItems', 'budget')
                ->findOrFail($id);

            // ดึงรายการงานที่ดูแล (เอาเฉพาะ Project และ Plan)
            $projects = $pm->workItems()
                ->whereIn('type', ['project', 'plan'])
                ->with(['division', 'department', 'issues']) // โหลดข้อมูลสังกัดและปัญหา
                ->orderByDesc('created_at')
                ->get()
                ->map(function ($item) {
                    // คำนวณสถานะความเสี่ยง/ปัญหา
                    $item->has_issues = $item->issues->where('type', 'issue')->where('status', '!=', 'resolved')->count() > 0;
                    $item->has_risks = $item->issues->where('type', 'risk')->where('status', '!=', 'resolved')->count() > 0;
                    return $item;
                });

            // สรุปสถานะงาน (Pie Chart Data)
            $stats = [
                'completed' => $projects->where('status', 'completed')->count(),
                'in_progress' => $projects->where('status', 'in_progress')->count(),
                'delayed' => $projects->where('status', 'delayed')->count(),
                'pending' => $projects->where('status', 'pending')->count(),
            ];

            return compact('pm', 'projects', 'stats');
        });

        return Inertia::render('ProjectManager/Show', [
            'pm' => $data['pm'],
            'projects' => $data['projects'],
            'stats' => $data['stats']
        ]);
    }

    // =========================================================================
    // 3. ลบ Project Manager
    // =========================================================================
    public function destroy($id)
    {
        $pm = ProjectManager::findOrFail($id);
        $pmName = $pm->name;

        DB::transaction(function () use ($pm) {
            // 1. ปลดชื่อออกจากงานทั้งหมดที่ดูแล (Set Null)
            $pm->workItems()->update(['project_manager_id' => null]);

            // 2. ลบ PM
            $pm->delete();
        });

        // 🧹 Clear Cache
        // 1. ล้างรายการ PM ทั้งหมด
        Cache::tags(['project_managers'])->flush();
        // 2. ล้าง Cache หน้า Profile ของคนนี้ (เผื่อใครเปิดค้างไว้)
        Cache::forget("pm_profile_{$id}");
        // 3. ล้าง Cache Global Search เพราะชื่อ PM หายไป
        // (ส่วน Global Search เราใช้ Key random อาจจะล้างยาก ปล่อยให้ Expire เองตามเวลา 2 นาทีได้ครับ)

        // 📝 บันทึก Log
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'DELETE',
            'model_type' => 'ProjectManager',
            'model_id' => $id,
            'target_name' => $pmName,
            'changes' => ['note' => 'Deleted PM and unlinked from projects'],
            'ip_address' => request()->ip(),
        ]);

        return redirect()->route('pm.index')->with('success', 'ลบผู้ดูแลโครงการเรียบร้อยแล้ว');
    }
}
