<?php

namespace App\Http\Controllers;

use App\Models\User; // ✅ ใช้ User Model
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

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

        // 🚀 CACHE LOGIC: เก็บ 5 นาที
        $pms = Cache::tags(['project_managers'])->remember($cacheKey, 300, function () use ($search) {

            // ✅ ค้นหาเฉพาะ User ที่เป็น PM
            $query = User::where(function($q) {
                $q->where('is_pm', true)
                  ->orWhereIn('role', ['pm', 'project_manager']);
            })
            ->withCount('projects') // ✅ นับจำนวนโครงการ
            ->withSum('projects', 'budget'); // ✅ รวมงบประมาณ

            if ($search) {
                $query->where('name', 'ilike', '%' . $search . '%');
            }

            // เรียงตามจำนวนโครงการ (ใครงานเยอะขึ้นก่อน)
            return $query->orderByDesc('projects_count')
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
        // 🔧 เปลี่ยน Key เป็น v3 เพื่อบังคับโหลดใหม่ (เพิ่ม Logs)
        $data = Cache::remember("pm_profile_v3_{$id}", 300, function () use ($id) {

            // 1. ข้อมูล PM
            $pm = User::withCount('projects')
                ->withSum('projects', 'budget')
                ->with(['division', 'department.division']) // ✅ โหลดสังกัด (และกองของแผนก)
                ->findOrFail($id);

            // 2. โปรเจคที่ดูแล
            $projects = $pm->projects()
                ->whereIn('type', ['project', 'plan'])
                ->with(['division', 'department', 'issues'])
                ->orderByDesc('created_at')
                ->get()
                ->map(function ($item) {
                    // คำนวณสถานะความเสี่ยง/ปัญหา
                    $item->has_issues = $item->issues->where('type', 'issue')->where('status', '!=', 'resolved')->count() > 0;
                    $item->has_risks = $item->issues->where('type', 'risk')->where('status', '!=', 'resolved')->count() > 0;
                    return $item;
                });

            // 3. สถิติ
            $stats = [
                'completed' => $projects->where('status', 'completed')->count(),
                'in_progress' => $projects->where('status', 'in_progress')->count(),
                'delayed' => $projects->where('status', 'delayed')->count(),
                'pending' => $projects->where('status', 'in_active')->count(),
            ];

            // 4. ✅ เพิ่ม Audit Logs (กิจกรรมล่าสุดของ PM คนนี้)
            $logs = AuditLog::where('user_id', $id)
                ->orderByDesc('created_at')
                ->limit(20) // ดึงมาแค่ 20 รายการล่าสุด
                ->get();

            return compact('pm', 'projects', 'stats', 'logs');
        });

        return Inertia::render('ProjectManager/Show', [
            'pm' => $data['pm'],
            'projects' => $data['projects'],
            'stats' => $data['stats'],
            'logs' => $data['logs'] // ✅ ส่ง logs ไปหน้าบ้าน
        ]);
    }

    // =========================================================================
    // 3. ลบ Project Manager (ลบ User ออกจากระบบ)
    // =========================================================================
    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $userName = $user->name;

        // 🛡️ ป้องกันการลบตัวเอง
        if (auth()->id() == $id) {
            return back()->withErrors(['error' => 'ไม่สามารถลบบัญชีตัวเองได้']);
        }

        DB::transaction(function () use ($user) {
            // 1. ปลดชื่อออกจากงานทั้งหมดที่ดูแล (Set Null)
            // ✅ ใช้ relation projects ของ User Model
            $user->projects()->update(['project_manager_id' => null]);

            // 2. ลบ User
            $user->delete();
        });

        // 🧹 Clear Cache
        Cache::tags(['project_managers'])->flush();
        Cache::forget("pm_profile_v3_{$id}"); // ล้าง Cache ของคนนี้

        // 📝 บันทึก Log
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'DELETE',
            'model_type' => 'User (PM)',
            'model_id' => $id,
            'target_name' => $userName,
            'changes' => ['note' => 'Deleted PM User and unlinked from projects'],
            'ip_address' => request()->ip(),
        ]);

        return redirect()->route('pm.index')->with('success', 'ลบผู้ใช้งานเรียบร้อยแล้ว');
    }
}
