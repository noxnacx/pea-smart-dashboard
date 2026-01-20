<?php

namespace App\Http\Controllers;

use App\Models\WorkItem;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class DashboardController extends Controller
{
    // ฟังก์ชันช่วยคำนวณข้อมูล (Private Helper) จะได้ไม่ต้องเขียนซ้ำ
    private function getDashboardData()
    {
        // 1. ดึงข้อมูลลำดับชั้น
        $strategies = WorkItem::whereNull('parent_id')
            ->with([
                'attachments',
                'children' => function($q) { $q->orderBy('order_index')->with('attachments'); },
                'children.children' => function($q) { $q->orderBy('order_index')->with('attachments'); },
                'children.children.children' => function($q) { $q->orderBy('order_index')->with('attachments'); },
                'children.children.children.children'
            ])
            ->orderBy('order_index')
            ->get();

        // 2. คำนวณ Stats
        $totalProjects = WorkItem::where('type', 'project')->count();
        $completedProjects = WorkItem::where('type', 'project')->where('progress', '>=', 100)->count();
        $delayedProjects = WorkItem::where('type', 'project')->where('status', 'delayed')->count();
        $totalBudget = WorkItem::where('type', 'project')->sum('budget');

        // 3. คำนวณ S-Curve
        $months = [];
        $plannedData = [];
        $actualData = [];

        $startYear = now()->startOfYear();
        $allProjects = WorkItem::where('type', 'project')->get();

        for ($i = 0; $i < 12; $i++) {
            $date = $startYear->copy()->addMonths($i)->endOfMonth();
            $months[] = $date->format('M Y');

            // PV
            $pv = $allProjects->sum(function($item) use ($date) {
                if (!$item->planned_start_date || !$item->planned_end_date || $item->budget <= 0) return 0;
                if ($date->lt($item->planned_start_date)) return 0;
                if ($date->gt($item->planned_end_date)) return $item->budget;

                $totalDays = $item->planned_start_date->diffInDays($item->planned_end_date) + 1;
                $passedDays = $item->planned_start_date->diffInDays($date) + 1;
                $percent = $passedDays / max(1, $totalDays);
                return $item->budget * $percent;
            });
            $plannedData[] = round($pv, 2);

            // EV
            if ($date->lte(now()->endOfMonth())) {
                $ev = $allProjects->sum(function($item) {
                   return $item->budget * ($item->progress / 100);
                });
                $actualData[] = round($ev, 2);
            }
        }

        return [
            'hierarchy' => $strategies,
            'stats' => [
                'total' => $totalProjects,
                'completed' => $completedProjects,
                'delayed' => $delayedProjects,
                'budget' => $totalBudget,
            ],
            'chartData' => [
                'categories' => $months,
                'planned' => $plannedData,
                'actual' => $actualData
            ]
        ];
    }

    // สำหรับหน้าแรก (Public)
    public function publicDashboard()
    {
        $data = $this->getDashboardData();
        return Inertia::render('Dashboard/PublicDashboard', [
            'hierarchy' => $data['hierarchy'],
            'stats' => $data['stats'],
            'canLogin' => \Illuminate\Support\Facades\Route::has('login'),
        ]);
    }

    // สำหรับหน้า Admin (Admin)
    public function adminDashboard()
    {
        $data = $this->getDashboardData();
        return Inertia::render('Dashboard/AdminDashboard', [
            'hierarchy' => $data['hierarchy'],
            'stats' => $data['stats'], // ส่งไปเผื่อใช้
            'chartData' => $data['chartData'] // ส่งข้อมูลกราฟไปด้วย! (นี่คือสิ่งที่ WorkItemController ขาดไป)
        ]);
    }
}
