<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\WorkItem;
use App\Models\Issue;
use App\Models\User;
use App\Models\Attachment;

class GlobalSearchController extends Controller
{
    public function search(Request $request)
    {
        $query = $request->input('q');

        if (!$query) {
            return response()->json([]);
        }

        // 1. ค้นหา Projects & Tasks
        $workItems = WorkItem::where('name', 'ilike', "%{$query}%")
            ->select('id', 'name', 'type', 'status')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'category' => 'Projects & Tasks',
                    'url' => route('work-items.show', $item->id), // ลิงก์ไปหน้างาน
                    'type' => 'internal' // บอกว่าเป็นลิงก์ภายใน
                ];
            });

        // 2. ค้นหา Issues (แก้ไขให้ลิงก์ไปหน้า Parent Project)
        $issues = Issue::where('title', 'ilike', "%{$query}%")
            ->with('workItem:id,name') // ดึงข้อมูลงานแม่มาด้วย
            ->limit(5)
            ->get()
            ->map(function ($item) {
                // ถ้ามีงานแม่ ให้ลิงก์ไปหางานแม่, ถ้าไม่มีให้เป็น null (หรือลิงก์ไปรายการรวม)
                $url = $item->work_item_id ? route('work-items.show', $item->work_item_id) : null;

                return [
                    'id' => $item->id,
                    'name' => $item->title,
                    'category' => 'Issues & Risks',
                    'url' => $url,
                    'type' => 'internal'
                ];
            });

        // 3. ค้นหา Users (แก้ไขให้ลิงก์ไปหน้า Users Index พร้อม Filter)
        $users = User::where('name', 'ilike', "%{$query}%")
            ->select('id', 'name', 'email', 'role')
            ->limit(3)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'category' => 'People',
                    // ✨ ลิงก์ไปหน้าจัดการ User พร้อมค้นหาชื่อคนนี้ให้เลย
                    'url' => route('users.index', ['search' => $item->name]),
                    'type' => 'internal'
                ];
            });

        // 4. ค้นหา Files (ลิงก์ดาวน์โหลด)
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
                    'type' => 'download' // บอกว่าเป็นดาวน์โหลด (ต้องเปิด tab ใหม่หรือโหลดเลย)
                ];
            });

        $results = $workItems->concat($issues)->concat($users)->concat($files);

        return response()->json($results);
    }
}
