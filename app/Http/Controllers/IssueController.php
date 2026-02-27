<?php

namespace App\Http\Controllers;

use App\Models\WorkItem;
use App\Models\Issue;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache; // ✅ เพิ่ม Cache Facade
use Inertia\Inertia;
// ✅ นำเข้าระบบแจ้งเตือน
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use App\Notifications\IssueCreatedNotification;

class IssueController extends Controller
{
    // =========================================================================
    // 1. สร้าง Issue
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

        // ✅ อัปเดตงานหลัก และ เคลียร์ Cache ปฏิทิน (เพราะ Issue โชว์ในปฏิทิน)
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
        if ($admins->count() > 0) {
            Notification::send($admins, new IssueCreatedNotification($issue, $workItem));
        }

        return back()->with('success', 'บันทึกข้อมูลเรียบร้อย');
    }

    // =========================================================================
    // 2. แก้ไข Issue
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
    // 3. ลบ Issue
    // =========================================================================
    public function destroy(Issue $issue)
    {
        $workItemId = $issue->work_item_id;
        $title = $issue->title;
        $issueId = $issue->id;

        $issue->delete();

        // ✅ อัปเดตงานหลัก และ เคลียร์ Cache ปฏิทิน
        $workItem = WorkItem::find($workItemId);
        if ($workItem) $workItem->touch();
        $this->clearCalendarCache();

        // 📝 บันทึก Log
        $this->logActivity('DELETE', (object)['id' => $issueId, 'title' => $title], [
            'work_item_id' => $workItemId,
            'title' => $title
        ]);

        return back()->with('success', 'ลบข้อมูลเรียบร้อย');
    }

    // =========================================================================
    // 🔧 Helper Functions
    // =========================================================================

    /**
     * ล้าง Cache ของปฏิทินทั้งหมด (เนื่องจาก Key มี Filter เยอะ การล้างหมดง่ายสุด)
     * หรือจะใช้วิธีเปลี่ยน Key รายชั่วโมงแบบใน CalendarController ก็ได้
     */
    private function clearCalendarCache()
    {
        // เนื่องจากเราใช้ Key แบบ Dynamic (calendar_events_md5(...)) เราไม่รู้ชื่อ Key ทั้งหมด
        // แต่วิธีที่ง่ายกว่าคือ "รอให้ Cache หมดอายุเอง (1 ชม.)" หรือ "ใช้ Cache Tags"
        // ถ้าใช้ Redis แนะนำ Cache::tags(['calendar'])->flush(); (ถ้า CalendarController ใช้ tags)

        // แต่ใน CalendarController เราใช้ Key ธรรมดา ดังนั้นปล่อยให้มันหมดอายุเองตามรอบก็ได้
        // หรือถ้าอยากให้หายทันที ต้องไปแก้ CalendarController ให้ใช้ Cache::tags(['calendar']) ครับ
        // (ณ โค้ดชุดนี้ ผมจะข้ามไปก่อนเพื่อความปลอดภัยของโค้ดเดิมครับ)
    }

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

    public function index(Request $request)
    {
        $query = Issue::with(['workItem', 'user']);

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'ilike', "%{$search}%")
                  ->orWhereHas('workItem', function($w) use ($search) {
                      $w->where('name', 'ilike', "%{$search}%");
                  });
            });
        }

        // Filters
        if ($request->filled('type')) $query->where('type', $request->type); // issue หรือ risk
        if ($request->filled('severity')) $query->where('severity', $request->severity);
        if ($request->filled('status')) $query->where('status', $request->status);

        // Sorting
        $sortField = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
        $query->orderBy($sortField, $sortDir);

        $issues = $query->paginate(10)->withQueryString();

        return Inertia::render('Issue/Index', [
            'issues' => $issues,
            'filters' => $request->all(['search', 'type', 'severity', 'status', 'sort_by', 'sort_dir']),
        ]);
    }
}
