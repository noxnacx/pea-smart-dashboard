<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\WorkItem;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache; // ✅ เพิ่ม Cache Facade

class AttachmentController extends Controller
{
    // =========================================================================
    // 1. อัปโหลดไฟล์
    // =========================================================================
    public function store(Request $request, WorkItem $workItem)
    {
        // ✅ เพิ่มการจำกัดนามสกุลไฟล์ (mimes)
        $request->validate([
            'file' => 'required|file|mimes:pdf,doc,docx,ppt,pptx,jpg,jpeg,png|max:10240', // 10MB
            'category' => 'required|string',
        ], [
            'file.mimes' => 'รองรับเฉพาะไฟล์ PDF, Word, Excel, PowerPoint และรูปภาพ (PNG, JPG) เท่านั้น'
        ]);

        $file = $request->file('file');
        $path = $file->store('attachments', 'public');

        $attachment = $workItem->attachments()->create([
            'user_id' => auth()->id(),
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'category' => $request->category,
        ]);

        // ✅ อัปเดตเวลาล่าสุดของงาน
        $workItem->touch();

        // 🧹 Clear Cache ที่เกี่ยวข้อง
        $this->clearRelatedCache($workItem->id);

        // 📝 บันทึก Log
        $this->logActivity('UPLOAD', $attachment, [
            'ขนาดไฟล์' => number_format($file->getSize() / 1024, 2) . ' KB',
            'หมวดหมู่' => $request->category
        ]);

        return redirect()->back()->with('success', 'อัปโหลดไฟล์สำเร็จ');
    }

    // =========================================================================
    // 2. ดาวน์โหลดไฟล์
    // =========================================================================
    public function download(Attachment $attachment)
    {
        if (!Storage::disk('public')->exists($attachment->file_path)) {
            return back()->with('error', 'ไม่พบไฟล์ต้นฉบับ');
        }

        // 📝 บันทึก Log (ไม่ต้อง Clear Cache เพราะแค่โหลด)
        $this->logActivity('DOWNLOAD', $attachment, ['ชื่อไฟล์' => $attachment->file_name]);

        return Storage::disk('public')->download($attachment->file_path, $attachment->file_name);
    }

    // =========================================================================
    // 3. ลบไฟล์
    // =========================================================================
    public function destroy(Attachment $attachment)
    {
        if (Storage::disk('public')->exists($attachment->file_path)) {
            Storage::disk('public')->delete($attachment->file_path);
        }

        $workItemId = $attachment->work_item_id; // เก็บ ID ไว้ก่อนลบ
        $oldData = $attachment->toArray(); // เก็บข้อมูลไว้ทำ Log

        $attachment->delete();

        // ✅ อัปเดตเวลาของงานหลัก
        if ($workItemId) {
            $workItem = WorkItem::find($workItemId);
            if ($workItem) {
                $workItem->touch();
                $this->clearRelatedCache($workItemId);
            }
        }

        // 📝 บันทึก Log
        $this->logActivity('DELETE', (object)$oldData, ['สถานะ' => 'ลบไฟล์ถาวร']);

        return back()->with('success', 'ลบไฟล์เรียบร้อยแล้ว');
    }

    // =========================================================================
    // 🔧 Helper Functions
    // =========================================================================

    /**
     * ล้าง Cache ที่เกี่ยวข้องกับ WorkItem นี้
     */
    private function clearRelatedCache($workItemId)
    {
        // 1. เคลียร์ S-Curve หรือข้อมูล Detail ที่อาจจะ Cache ไว้
        Cache::forget("report_project_{$workItemId}");
        Cache::forget("work_item_{$workItemId}_s_curve");

        // 2. (Optional) ถ้ามี Cache ส่วนกลางอื่นๆ ก็ใส่เพิ่มตรงนี้ได้
        // Cache::tags(['work_items'])->flush();
    }

    /**
     * บันทึก Audit Log แบบรวมศูนย์
     */
    private function logActivity($action, $model, $changes = [])
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'model_type' => 'Attachment',
            'model_id' => $model->id ?? 0,
            'target_name' => $model->file_name ?? 'Unknown File',
            'changes' => $changes,
            'ip_address' => request()->ip(), // ✅ เก็บ IP
        ]);
    }
}
