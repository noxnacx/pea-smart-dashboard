<?php

namespace App\Http\Controllers;

use App\Models\WorkItem;
use App\Models\Issue;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class IssueController extends Controller
{
    public function store(Request $request, WorkItem $workItem)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|in:issue,risk',
            'severity' => 'required|in:low,medium,high,critical',
            'status' => 'required|in:open,in_progress,resolved',
            'description' => 'nullable|string',
            'solution' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $validated['user_id'] = auth()->id();
        $issue = $workItem->issues()->create($validated);

        // --- บันทึก Log (เพิ่ม IP) ---
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'CREATE',
            'model_type' => 'Issue',
            'model_id' => $issue->id,
            'ip_address' => request()->ip(), // <--- เพิ่มบรรทัดนี้
            'changes' => [
                'work_item_id' => $workItem->id,
                'title' => $issue->title,
                'type' => $issue->type
            ]
        ]);

        return back()->with('success', 'บันทึกข้อมูลเรียบร้อย');
    }

    public function update(Request $request, Issue $issue)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|in:issue,risk',
            'severity' => 'required|in:low,medium,high,critical',
            'status' => 'required|in:open,in_progress,resolved',
            'description' => 'nullable|string',
            'solution' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $oldData = $issue->getOriginal();
        $issue->update($validated);
        $changes = $issue->getChanges();

        if (!empty($changes)) {
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'UPDATE',
                'model_type' => 'Issue',
                'model_id' => $issue->id,
                'ip_address' => request()->ip(), // <--- เพิ่มบรรทัดนี้
                'changes' => [
                    'work_item_id' => $issue->work_item_id,
                    'title' => $issue->title,
                    'before' => $oldData,
                    'after' => $changes
                ]
            ]);
        }

        return back()->with('success', 'อัปเดตข้อมูลเรียบร้อย');
    }

    public function destroy(Issue $issue)
    {
        $workItemId = $issue->work_item_id;
        $title = $issue->title;
        $issue->delete();

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'DELETE',
            'model_type' => 'Issue',
            'model_id' => $issue->id,
            'ip_address' => request()->ip(), // <--- เพิ่มบรรทัดนี้
            'changes' => [
                'work_item_id' => $workItemId,
                'title' => $title
            ]
        ]);

        return back()->with('success', 'ลบข้อมูลเรียบร้อย');
    }
}
