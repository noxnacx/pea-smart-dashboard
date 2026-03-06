<?php

namespace App\Http\Controllers;

use App\Models\Milestone;
use App\Models\WorkItem;
use Illuminate\Http\Request;

class MilestoneController extends Controller
{
    public function store(Request $request, WorkItem $workItem)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'due_date' => 'nullable|date',
            'status' => 'nullable|string|max:50', // ✅ รับค่า Status
        ]);

        $validated['status'] = $validated['status'] ?? 'pending';

        $workItem->milestones()->create($validated);

        return back()->with('success', 'เพิ่มเป้าหมายสำเร็จ');
    }

    public function update(Request $request, Milestone $milestone)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'status' => 'required|string|max:50', // ✅ รับค่าได้ทุก Status (แก้บัคสีไม่เปลี่ยน)
            'due_date' => 'nullable|date',
            'remarks' => 'nullable|string'
        ]);

        $milestone->update($validated);

        return back()->with('success', 'อัปเดตเป้าหมายสำเร็จ');
    }

    public function destroy(Milestone $milestone)
    {
        $milestone->delete();
        return back()->with('success', 'ลบเป้าหมายสำเร็จ');
    }

    // 🚀 ฟังก์ชันดึงเป้าหมายจากงานย่อย (แทนที่ AI)
    public function generateAuto(Request $request, WorkItem $workItem)
    {
        // 1. โหลดข้อมูลงานย่อยแบบเจาะลึกลงไป (เผื่อมีลูกหลาน)
        $workItem->load('children.children.children');

        // 2. ดึงงานย่อยทั้งหมดออกมาเป็น Array แถวเดียว
        $tasks = $this->extractTasks($workItem);
        $count = 0;

        foreach ($tasks as $task) {
            // ถ้างานย่อยมีวันสิ้นสุด และไม่ได้ถูกยกเลิก
            if ($task->planned_end_date && $task->status !== 'cancelled') {

                // เช็คว่ามีเป้าหมายชื่อนี้และกำหนดส่งวันนี้ ถูกสร้างไว้แล้วหรือยัง (ป้องกันการดึงซ้ำ)
                $exists = Milestone::where('work_item_id', $workItem->id)
                    ->where('title', $task->name)
                    ->where('due_date', $task->planned_end_date)
                    ->exists();

                if (!$exists) {
                    $workItem->milestones()->create([
                        'title' => $task->name,
                        'due_date' => $task->planned_end_date,
                        // ถ้าระดับ Task งานเสร็จแล้ว ให้ Milestone เสร็จด้วยเลย
                        'status' => $task->progress >= 100 ? 'completed' : 'pending',
                        'remarks' => 'ดึงอัตโนมัติจากตารางงาน'
                    ]);
                    $count++;
                }
            }
        }

        if ($count > 0) {
            return back()->with('success', "สร้างเป้าหมายจากงานย่อยสำเร็จจำนวน {$count} รายการ");
        }

        return back()->with('success', 'ไม่มีเป้าหมายใหม่ให้ดึง (ข้อมูลเป็นปัจจุบันแล้ว)');
    }

    // --- Helper Function ช่วยควานหางานย่อยทุกระดับชั้น ---
    private function extractTasks($item)
    {
        $tasks = [];
        foreach ($item->children as $child) {
            $tasks[] = $child;
            if ($child->children && $child->children->count() > 0) {
                $tasks = array_merge($tasks, $this->extractTasks($child));
            }
        }
        return $tasks;
    }
}
