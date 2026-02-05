<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\WorkItem;
use App\Models\Issue;
use App\Models\ProjectManager;
use App\Models\Attachment;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Cache; // ✅ เพิ่ม Cache Facade

class GlobalSearchController extends Controller
{
    public function search(Request $request)
    {
        $query = $request->input('q');

        if (!$query) {
            return response()->json([]);
        }

        // สร้าง Cache Key จากคำค้นหา (ไม่ซ้ำกันตามคำที่พิมพ์)
        $cacheKey = 'global_search_' . md5(strtolower(trim($query)));

        // 🚀 CACHE LOGIC: เก็บผลการค้นหา 2 นาที (120 วินาที)
        // เก็บสั้นๆ เพราะ User คาดหวังผลลัพธ์ที่ Real-time พอสมควร
        $results = Cache::remember($cacheKey, 120, function () use ($query) {

            // ==========================================
            // 🚀 ส่วนที่ 1: ค้นหาเมนู (Filter ตาม Role/Gate)
            // ==========================================
            $systemPages = collect([
                // --- ทุกคนเข้าได้ (General) ---
                ['name' => 'Dashboard', 'route' => 'dashboard', 'keywords' => 'home admin overview graph chart'],
                ['name' => 'Strategies (ยุทธศาสตร์)', 'route' => 'strategies.index', 'keywords' => 'strategy goal'],
                ['name' => 'Plans (แผนงาน)', 'route' => 'plans.index', 'keywords' => 'plan master'],
                ['name' => 'Projects (โครงการ)', 'route' => 'projects.index', 'keywords' => 'project list'],
                ['name' => 'All Work Items (งานทั้งหมด)', 'route' => 'work-items.index', 'keywords' => 'task all work'],
                ['name' => 'Reports (รายงาน)', 'route' => 'reports.index', 'keywords' => 'export pdf excel print status progress'],
                ['name' => 'Calendar (ปฏิทิน)', 'route' => 'calendar.index', 'keywords' => 'schedule timeline date'],
                ['name' => 'Project Managers (ผู้รับผิดชอบ)', 'route' => 'pm.index', 'keywords' => 'people staff user pm'],

                // --- เฉพาะ Admin (manage-system) ---
                [
                    'name' => 'Organization (โครงสร้างองค์กร)',
                    'route' => 'organization.index',
                    'keywords' => 'department division structure',
                    'gate' => 'manage-system' // 🔒 ระบุ Gate
                ],
                [
                    'name' => 'Audit Logs (ประวัติระบบ)',
                    'route' => 'audit-logs.index',
                    'keywords' => 'history log system action',
                    'gate' => 'manage-system' // 🔒 ระบุ Gate
                ],
                [
                    'name' => 'User Management (จัดการผู้ใช้)',
                    'route' => 'users.index',
                    'keywords' => 'user member account register',
                    'gate' => 'manage-system' // 🔒 ระบุ Gate
                ],
            ]);

            // ✅ กรองตามสิทธิ์ (Gate)
            $matchedPages = $systemPages->filter(function ($page) use ($query) {
                // 1. ถ้ามี Gate ต้องเช็คก่อนว่า User ผ่านไหม
                if (isset($page['gate']) && !Gate::allows($page['gate'])) {
                    return false; // ถ้าไม่ผ่าน Gate ให้ซ่อนไปเลย
                }

                // 2. เช็คว่ามี Route จริงไหม และคำค้นตรงไหม
                return Route::has($page['route']) && (
                    stripos($page['name'], $query) !== false ||
                    stripos($page['keywords'], $query) !== false
                );
            })->map(function ($page) {
                return [
                    'id' => 'nav-' . $page['route'],
                    'name' => 'Go to: ' . $page['name'],
                    'category' => 'Navigation',
                    'url' => route($page['route']),
                    'type' => 'page',
                    'description' => isset($page['gate']) ? 'Admin Only' : 'System Page'
                ];
            })->values();


            // ==========================================
            // 💾 ส่วนที่ 2: ค้นหา Database
            // ==========================================

            // 1. Project Managers
            $pms = ProjectManager::where('name', 'ilike', "%{$query}%")
                ->with(['workItems' => function($q) {
                    $q->select('id', 'name', 'project_manager_id')->limit(3);
                }])
                ->limit(3)
                ->get()
                ->map(function ($pm) {
                    return [
                        'id' => $pm->id,
                        'name' => $pm->name,
                        'category' => 'Project Managers',
                        'url' => route('pm.show', $pm->id),
                        'type' => 'pm',
                        'related_projects' => $pm->workItems->map(function($w) {
                            return [
                                'name' => $w->name,
                                'url' => route('work-items.show', $w->id)
                            ];
                        })
                    ];
                });

            // 2. Work Items
            $workItems = WorkItem::with(['division', 'department'])
                ->where(function($q) use ($query) {
                    $q->where('name', 'ilike', "%{$query}%")
                      ->orWhereHas('division', function($d) use ($query) {
                          $d->where('name', 'ilike', "%{$query}%");
                      })
                      ->orWhereHas('department', function($d) use ($query) {
                          $d->where('name', 'ilike', "%{$query}%");
                      });
                })
                ->select('id', 'name', 'division_id', 'department_id', 'status', 'type')
                ->limit(5)
                ->get()
                ->map(function ($item) {
                    $desc = $item->division ? $item->division->name : '';
                    if ($item->department) $desc .= ' / ' . $item->department->name;

                    return [
                        'id' => $item->id,
                        'name' => $item->name,
                        'description' => $desc,
                        'category' => 'Projects & Plans',
                        'url' => route('work-items.show', $item->id),
                        'type' => 'work_item',
                        'status' => $item->status
                    ];
                });

            // 3. Issues
            $issues = Issue::where('title', 'ilike', "%{$query}%")
                ->with('workItem:id,name')
                ->limit(3)
                ->get()
                ->map(function ($item) {
                    $url = $item->work_item_id ? route('work-items.show', $item->work_item_id) : null;
                    return [
                        'id' => $item->id,
                        'name' => $item->title,
                        'category' => 'Issues & Risks',
                        'url' => $url,
                        'type' => 'issue'
                    ];
                });

            // 4. Files
            $files = Attachment::where('file_name', 'ilike', "%{$query}%")
                ->select('id', 'file_name', 'file_path')
                ->limit(3)
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'name' => $item->file_name,
                        'category' => 'Files',
                        'url' => route('attachments.download', $item->id),
                        'type' => 'download'
                    ];
                });

            // รวมผลลัพธ์และส่งกลับ Cache
            return $matchedPages
                ->concat($pms)
                ->concat($workItems)
                ->concat($issues)
                ->concat($files);
        });

        return response()->json($results);
    }
}
