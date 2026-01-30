<?php

namespace App\Http\Controllers;

use App\Models\ProjectManager;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB; // ✅ เพิ่ม DB

class ProjectManagerController extends Controller
{
    // แสดงหน้ารายชื่อ PM ทั้งหมด
    public function index(Request $request)
    {
        $query = ProjectManager::withCount('workItems') // นับจำนวนโครงการ
            ->withSum('workItems', 'budget'); // รวมงบประมาณ

        if ($request->filled('search')) {
            $query->where('name', 'ilike', '%' . $request->search . '%');
        }

        // เรียงตามจำนวนโครงการ (ใครงานเยอะขึ้นก่อน)
        $pms = $query->orderByDesc('work_items_count')->paginate(12)->withQueryString();

        return Inertia::render('ProjectManager/Index', [
            'pms' => $pms,
            'filters' => $request->only(['search'])
        ]);
    }

    // แสดงหน้า Portfolio รายบุคคล
    public function show($id)
    {
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

        return Inertia::render('ProjectManager/Show', [
            'pm' => $pm,
            'projects' => $projects,
            'stats' => $stats
        ]);
    }

    public function destroy($id)
    {
        // ตรวจสอบสิทธิ์ (ถ้าไม่ได้ทำที่ Middleware ก็เช็คตรงนี้เพิ่มได้)
        // if (auth()->user()->role !== 'admin') abort(403);

        $pm = ProjectManager::findOrFail($id);

        DB::transaction(function () use ($pm) {
            // 1. ปลดชื่อออกจากงานทั้งหมดที่ดูแล (Set Null)
            $pm->workItems()->update(['project_manager_id' => null]);

            // 2. ลบ PM
            $pm->delete();
        });

        return redirect()->back()->with('success', 'ลบผู้ดูแลโครงการเรียบร้อยแล้ว');
    }
}
