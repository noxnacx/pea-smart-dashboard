<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\WorkItem;
use App\Models\AuditLog; // ✅ Import AuditLog
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Redirect;

class AttachmentController extends Controller
{
    // อัปโหลดไฟล์
    public function store(Request $request, WorkItem $workItem)
    {
        $request->validate([
            'file' => 'required|file|max:10240', // 10MB
            'category' => 'required|string',
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

        // ✅ บันทึก Log การอัปโหลด
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'UPLOAD',
            'model_type' => 'Attachment',
            'model_id' => $attachment->id,
            'target_name' => $attachment->file_name,
            'changes' => [
                'ขนาดไฟล์' => number_format($file->getSize() / 1024, 2) . ' KB',
                'หมวดหมู่' => $request->category
            ],
        ]);

        return redirect()->back()->with('success', 'อัปโหลดไฟล์สำเร็จ');
    }

    // ดาวน์โหลดไฟล์
    public function download(Attachment $attachment)
    {
        if (!Storage::disk('public')->exists($attachment->file_path)) {
            return back()->with('error', 'ไม่พบไฟล์ต้นฉบับ');
        }

        // ✅ บันทึก Log การดาวน์โหลด (ส่ง Array แทน json_encode)
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'DOWNLOAD',
            'model_type' => 'Attachment',
            'model_id' => $attachment->id,
            'target_name' => $attachment->file_name,
            'changes' => ['ชื่อไฟล์' => $attachment->file_name],
        ]);

        return Storage::disk('public')->download($attachment->file_path, $attachment->file_name);
    }

    // ลบไฟล์
    public function destroy(Attachment $attachment)
    {
        if (Storage::disk('public')->exists($attachment->file_path)) {
            Storage::disk('public')->delete($attachment->file_path);
        }

        // เก็บชื่อไว้ก่อนลบ
        $fileName = $attachment->file_name;
        $id = $attachment->id;

        $attachment->delete();

        // ✅ บันทึก Log การลบ
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'DELETE',
            'model_type' => 'Attachment',
            'model_id' => $id,
            'target_name' => $fileName,
            'changes' => ['สถานะ' => 'ลบไฟล์ถาวร'],
        ]);

        return back()->with('success', 'ลบไฟล์เรียบร้อยแล้ว');
    }
}
