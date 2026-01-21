<?php

namespace App\Http\Controllers;

use App\Models\WorkItem;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class DashboardController extends Controller
{
    // สำหรับหน้า Admin (Executive Dashboard)
    public function adminDashboard()
    {
        // 1. ดึงข้อมูลลำดับชั้น
        // แก้ไข: ใช้ 'children.children' เพื่อโหลด แผนงาน (Level 2) และ โครงการ (Level 3)
        $strategies = WorkItem::whereNull('parent_id')
            ->with('children.children')
            ->orderBy('name', 'asc')
            ->get();

        // 2. คำนวณสถิติพื้นฐาน
        $totalProjects = WorkItem::where('type', 'project')->count();
        $totalBudget = (float) WorkItem::sum('budget');
        $completedProjects = WorkItem::where('type', 'project')->where('status', 'completed')->count();
        $delayedProjects = WorkItem::where('type', 'project')->where('status', 'delayed')->count();

        // 3. กราฟวงกลม
        $statusCounts = WorkItem::where('type', 'project')
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $chartData = [
            'series' => !empty($statusCounts) ? array_values($statusCounts) : [0],
            'labels' => !empty($statusCounts) ? array_keys($statusCounts) : ['ไม่มีข้อมูล'],
        ];

        // 4. กิจกรรมล่าสุด
        $recentLogs = AuditLog::with('user')
            ->latest()
            ->take(5)
            ->get();

        return Inertia::render('Dashboard/AdminDashboard', [
            'hierarchy' => $strategies,
            'stats' => [
                'total_projects' => $totalProjects,
                'total_budget' => $totalBudget,
                'completed' => $completedProjects,
                'delayed' => $delayedProjects,
            ],
            'chartData' => $chartData,
            'recentLogs' => $recentLogs
        ]);
    }

    // สำหรับหน้าแรก (Public)
    public function publicDashboard()
    {
        // แก้ตรงนี้ด้วยให้เหมือนกัน
        $strategies = WorkItem::whereNull('parent_id')
            ->with('children.children')
            ->orderBy('name', 'asc')
            ->get();

        return Inertia::render('Dashboard/PublicDashboard', [
            'hierarchy' => $strategies,
            'stats' => [
                'total' => WorkItem::where('type', 'project')->count(),
                'budget' => WorkItem::sum('budget')
            ],
            'canLogin' => \Illuminate\Support\Facades\Route::has('login'),
        ]);
    }
}
