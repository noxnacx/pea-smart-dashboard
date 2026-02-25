<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\AuditLog;
use App\Models\Division;
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
        $division_id = $request->input('division_id');
        $department_id = $request->input('department_id');
        $sort_by = $request->input('sort_by', 'projects_count');
        $sort_dir = $request->input('sort_dir', 'desc');

        // ค้นหาเฉพาะ User ที่เป็น PM
        $query = User::where(function($q) {
            $q->where('is_pm', true)
              ->orWhereIn('role', ['pm', 'project_manager']);
        })
        ->withCount('projects')
        ->withSum('projects', 'budget');

        // 🔍 กรองตามชื่อ
        if ($search) {
            $query->where('name', 'ilike', '%' . $search . '%');
        }

        // 🏢 กรองตามกอง (ค้นหาจากงานที่ PM คนนั้นดูแลอยู่)
        if ($division_id) {
            $query->whereHas('projects', function($pq) use ($division_id) {
                $pq->where('division_id', $division_id);
            });
        }

        // 🏷️ กรองตามแผนก (ค้นหาจากงานที่ PM คนนั้นดูแลอยู่)
        if ($department_id) {
            $query->whereHas('projects', function($pq) use ($department_id) {
                $pq->where('department_id', $department_id);
            });
        }

        // 📊 จัดการการเรียงลำดับ
        if ($sort_by === 'projects_count') {
            $query->orderBy('projects_count', $sort_dir);
        } elseif ($sort_by === 'budget') {
            $query->orderBy('projects_sum_budget', $sort_dir);
        } else {
            $query->orderBy('name', $sort_dir);
        }

        // จำกัดการแสดงผลที่ 12 คนต่อหน้า
        $pms = $query->paginate(12)->withQueryString();

        $divisions = Division::with('departments')->orderBy('name')->get();

        return Inertia::render('ProjectManager/Index', [
            'pms' => $pms,
            'divisions' => $divisions,
            'filters' => $request->only(['search', 'division_id', 'department_id', 'sort_by', 'sort_dir'])
        ]);
    }

    // =========================================================================
    // 2. แสดงหน้า Portfolio รายบุคคล
    // =========================================================================
    public function show($id)
    {
        $data = Cache::remember("pm_profile_v3_{$id}", 300, function () use ($id) {
            $pm = User::withCount('projects')
                ->withSum('projects', 'budget')
                ->with(['division', 'department.division'])
                ->findOrFail($id);

            $projects = $pm->projects()
                ->whereIn('type', ['project', 'plan'])
                ->with(['division', 'department', 'issues'])
                ->orderByDesc('created_at')
                ->get()
                ->map(function ($item) {
                    $item->has_issues = $item->issues->where('type', 'issue')->where('status', '!=', 'resolved')->count() > 0;
                    $item->has_risks = $item->issues->where('type', 'risk')->where('status', '!=', 'resolved')->count() > 0;
                    return $item;
                });

            $stats = [
                'completed' => $projects->where('status', 'completed')->count(),
                'in_progress' => $projects->where('status', 'in_progress')->count(),
                'delayed' => $projects->where('status', 'delayed')->count(),
                'pending' => $projects->where('status', 'in_active')->count(),
            ];

            $logs = AuditLog::where('user_id', $id)
                ->orderByDesc('created_at')
                ->limit(20)
                ->get();

            return compact('pm', 'projects', 'stats', 'logs');
        });

        return Inertia::render('ProjectManager/Show', [
            'pm' => $data['pm'],
            'projects' => $data['projects'],
            'stats' => $data['stats'],
            'logs' => $data['logs']
        ]);
    }

    // =========================================================================
    // 3. ลบ Project Manager
    // =========================================================================
    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $userName = $user->name;

        if (auth()->id() == $id) {
            return back()->withErrors(['error' => 'ไม่สามารถลบบัญชีตัวเองได้']);
        }

        DB::transaction(function () use ($user) {
            $user->projects()->update(['project_manager_id' => null]);
            $user->delete();
        });

        Cache::forget("pm_profile_v3_{$id}");

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
