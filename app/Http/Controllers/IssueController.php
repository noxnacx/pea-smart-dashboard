<?php

namespace App\Http\Controllers;

use App\Models\WorkItem;
use App\Models\Issue;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use App\Notifications\IssueCreatedNotification;
use Inertia\Inertia;

class IssueController extends Controller
{
    // =========================================================================
    // 1. หน้าแสดงรายการ Issue ทั้งหมด (พร้อมระบบค้นหาและตัวกรอง)
    // =========================================================================
    public function index(Request $request)
    {
        $query = Issue::with(['workItem', 'user']);

        // 🔍 ระบบค้นหาแบบ Case-Insensitive (รองรับ PostgreSQL ด้วย ilike)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'ilike', "%{$search}%")
                  ->orWhereHas('workItem', function($w) use ($search) {
                      $w->where('name', 'ilike', "%{$search}%");
                  });
            });
        }

        // 🎯 ระบบตัวกรอง (Filters)
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('severity')) {
            $query->where('severity', $request->severity);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // ↕️ ระบบจัดเรียง (Sorting)
        $sortField = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
        $query->orderBy($sortField, $sortDir);

        // 📄 ระบบแบ่งหน้า (Pagination)
        $issues = $query->paginate(10)->withQueryString();

        return Inertia::render('Issue/Index', [
            'issues' => $issues,
            // ใช้ only() เพื่อดึงเฉพาะค่าที่มีอยู่จริง ป้องกันค่า null ที่ไม่จำเป็น
            'filters' => $request->only(['search', 'type', 'severity', 'status', 'sort_by', 'sort_dir']),
        ]);
    }

    // =========================================================================
    // 2. สร้าง Issue ใหม่
    // =========================================================================
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

        // ✅ อัปเดตงานหลัก และ เคลียร์ Cache ปฏิทิน
        $workItem->touch();
        $this->clearCalendarCache();

        // 📝 บันทึก Log
        $this->logActivity('CREATE', $issue, [
            'work_item_id' => $workItem->id,
            'title' => $issue->title,
            'type' => $issue->type
        ]);

        // 🔔 แจ้งเตือน Admin ทุกคน เมื่อมีปัญหา/ความเสี่ยงใหม่ถูกสร้างขึ้น
        $admins = User::where('role', 'admin')->get();
        if ($admins->isNotEmpty()) {
            Notification::send($admins, new IssueCreatedNotification($issue, $workItem));
        }

        return back()->with('success', 'บันทึกข้อมูลเรียบร้อย');
    }

    // =========================================================================
    // 3. แก้ไข Issue
    // =========================================================================
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
            // ✅ อัปเดตงานหลัก และ เคลียร์ Cache ปฏิทิน
            $issue->workItem->touch();
            $this->clearCalendarCache();

            // 📝 บันทึก Log
            $this->logActivity('UPDATE', $issue, [
                'work_item_id' => $issue->work_item_id,
                'title' => $issue->title,
                'before' => $oldData,
                'after' => $changes
            ]);
        }

        return back()->with('success', 'อัปเดตข้อมูลเรียบร้อย');
    }

    // =========================================================================
    // 4. ลบ Issue
    // =========================================================================
    public function destroy(Issue $issue)
    {
        $workItemId = $issue->work_item_id;
        $title = $issue->title;
        $issueId = $issue->id;

        $issue->delete();

        // ✅ อัปเดตงานหลัก และ เคลียร์ Cache ปฏิทิน
        $workItem = WorkItem::find($workItemId);
        if ($workItem) {
            $workItem->touch();
        }

        $this->clearCalendarCache();

        // 📝 บันทึก Log
        $this->logActivity('DELETE', clone $issue, [
            'work_item_id' => $workItemId,
            'title' => $title
        ]);

        return back()->with('success', 'ลบข้อมูลเรียบร้อย');
    }

    // =========================================================================
    // 🔧 Helper Functions
    // =========================================================================

    /**
     * ล้าง Cache ของปฏิทิน
     */
    private function clearCalendarCache()
    {
        // ปล่อยว่างไว้ตามที่คุณตั้งใจ (รอให้ Cache หมดอายุเอง หรือรอเขียนเพิ่มแบบ tags ในอนาคต)
    }

    /**
     * บันทึกประวัติการกระทำ (Audit Log)
     */
    private function logActivity($action, $model, $changes = [])
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'model_type' => 'Issue',
            'model_id' => $model->id ?? 0,
            'ip_address' => request()->ip(),
            'changes' => $changes
        ]);
    }
}
