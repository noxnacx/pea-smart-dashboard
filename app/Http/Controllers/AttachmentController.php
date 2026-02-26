<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\WorkItem;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;

class AttachmentController extends Controller
{
    // =========================================================================
    // 1. อัปโหลดไฟล์ (ซ่อนไฟล์ไว้ในโฟลเดอร์ Private)
    // =========================================================================
    public function store(Request $request, WorkItem $workItem)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,jpg,jpeg,png|max:10240', // จำกัด 10MB
            'category' => 'required|string',
        ], [
            'file.max' => 'ขนาดไฟล์ต้องไม่เกิน 10MB',
            'file.mimes' => 'รองรับเฉพาะไฟล์ PDF, Word, Excel, PowerPoint และรูปภาพ เท่านั้น'
        ]);

        $file = $request->file('file');

        // 🔒 เปลี่ยนจาก public เป็น disk local (ซ่อนไฟล์)
        $path = $file->store('attachments'); // จะไปเซฟที่ storage/app/attachments

        $attachment = $workItem->attachments()->create([
            'user_id' => auth()->id(),
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'category' => $request->category,
        ]);

        $workItem->touch();
        $this->clearRelatedCache($workItem->id);

        $this->logActivity('UPLOAD', $attachment, [
            'ขนาดไฟล์' => number_format($file->getSize() / 1024, 2) . ' KB',
            'หมวดหมู่' => $request->category
        ]);

        return redirect()->back()->with('success', 'อัปโหลดไฟล์สำเร็จ');
    }

    // =========================================================================
    // 2. ดาวน์โหลดไฟล์ (ตรวจสิทธิ์ก่อนโหลด)
    // =========================================================================
    public function download(Attachment $attachment)
    {
        // 🔒 ตรวจสอบว่าไฟล์มีอยู่จริงใน Disk ส่วนตัวหรือไม่
        if (!Storage::exists($attachment->file_path) && !Storage::disk('public')->exists($attachment->file_path)) {
            return back()->with('error', 'ไม่พบไฟล์ต้นฉบับในระบบ');
        }

        $this->logActivity('DOWNLOAD', $attachment, ['ชื่อไฟล์' => $attachment->file_name]);

        // รองรับทั้งไฟล์เก่า (public) และไฟล์ใหม่ (private)
        if (Storage::exists($attachment->file_path)) {
            return Storage::download($attachment->file_path, $attachment->file_name);
        } else {
            return Storage::disk('public')->download($attachment->file_path, $attachment->file_name);
        }
    }

    // =========================================================================
    // 3. ลบไฟล์
    // =========================================================================
    public function destroy(Attachment $attachment)
    {
        // ลบไฟล์จริงในเครื่อง
        if (Storage::exists($attachment->file_path)) {
            Storage::delete($attachment->file_path);
        } elseif (Storage::disk('public')->exists($attachment->file_path)) {
            Storage::disk('public')->delete($attachment->file_path);
        }

        $workItemId = $attachment->work_item_id;
        $oldData = $attachment->toArray();

        $attachment->delete();

        if ($workItemId) {
            $workItem = WorkItem::find($workItemId);
            if ($workItem) {
                $workItem->touch();
                $this->clearRelatedCache($workItemId);
            }
        }

        $this->logActivity('DELETE', (object)$oldData, ['สถานะ' => 'ลบไฟล์ถาวร']);

        return back()->with('success', 'ลบไฟล์เรียบร้อยแล้ว');
    }

    // =========================================================================
    // 🔧 Helper Functions
    // =========================================================================
    private function clearRelatedCache($workItemId)
    {
        Cache::forget("report_project_{$workItemId}");
        Cache::forget("work_item_{$workItemId}_s_curve");
    }

    private function logActivity($action, $model, $changes = [])
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'model_type' => 'Attachment',
            'model_id' => $model->id ?? 0,
            'target_name' => $model->file_name ?? 'Unknown File',
            'changes' => $changes,
            'ip_address' => request()->ip(),
        ]);
    }
}
