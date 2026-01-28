<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\WorkItem;
use App\Models\Issue;
use App\Models\ProjectManager; // ✅ Import PM
use App\Models\Attachment;

class GlobalSearchController extends Controller
{
    public function search(Request $request)
    {
        $query = $request->input('q');

        if (!$query) {
            return response()->json([]);
        }

        // 1. ค้นหา Project Managers (PM)
        // ✅ ค้นหาชื่อ PM และดึงโครงการที่เขาดูแลมาด้วย (3 อันดับแรก)
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
                    'url' => route('pm.show', $pm->id), // ลิงก์ไปหน้า Profile PM
                    'type' => 'pm', // ✨ ระบุ Type พิเศษ
                    'related_projects' => $pm->workItems->map(function($w) {
                        return [
                            'name' => $w->name,
                            'url' => route('work-items.show', $w->id)
                        ];
                    })
                ];
            });

        // 2. ค้นหา Projects & Tasks
        // ✅ ค้นหาจาก ชื่อโครงการ OR ชื่อกอง OR ชื่อแผนก
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
                // สร้างคำอธิบายเพิ่มเติม (เช่น อยู่กองไหน)
                $desc = $item->division ? $item->division->name : '';
                if ($item->department) $desc .= ' / ' . $item->department->name;

                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'description' => $desc, // ส่งข้อมูลสังกัดไปโชว์
                    'category' => 'Projects & Plans',
                    'url' => route('work-items.show', $item->id),
                    'type' => 'work_item',
                    'status' => $item->status
                ];
            });

        // 3. ค้นหา Issues (เหมือนเดิม)
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

        // 4. ค้นหา Files (เหมือนเดิม)
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

        // เอามารวมกัน (เอา PM ขึ้นก่อน ตามด้วยงาน)
        $results = $pms->concat($workItems)->concat($issues)->concat($files);

        return response()->json($results);
    }
}
