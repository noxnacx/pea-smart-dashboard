<?php

namespace App\Http\Controllers;

use App\Models\Attachment; // *** ใช้ Model ตัวใหม่ ***
use App\Models\WorkItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Redirect;

class AttachmentController extends Controller
{
    // อัปโหลดไฟล์
    public function store(Request $request, WorkItem $workItem)
    {
        $request->validate([
            'file' => 'required|file|max:10240', // จำกัด 10MB
            'category' => 'required|string', // รับค่าหมวดหมู่มาด้วย
        ]);

        $file = $request->file('file');
        $path = $file->store('attachments', 'public');

        // บันทึกลงฐานข้อมูล (ผ่าน Relationship attachments ที่แก้ใน WorkItem แล้ว)
        $workItem->attachments()->create([
            'user_id' => auth()->id(),
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_type' => $file->getMimeType(), // บันทึกประเภทไฟล์
            'file_size' => $file->getSize(),     // บันทึกขนาดไฟล์
            'category' => $request->category,    // บันทึกหมวดหมู่
        ]);

        return redirect()->back()->with('success', 'อัปโหลดไฟล์สำเร็จ');
    }

    // ดาวน์โหลดไฟล์
    // *** แก้ตรงนี้: เปลี่ยน WorkItemAttachment เป็น Attachment ***
    public function download(Attachment $attachment)
    {
        if (!Storage::disk('public')->exists($attachment->file_path)) {
            return back()->with('error', 'ไม่พบไฟล์ต้นฉบับ');
        }

        return Storage::disk('public')->download($attachment->file_path, $attachment->file_name);
    }

    // ลบไฟล์
    // *** แก้ตรงนี้: เปลี่ยน WorkItemAttachment เป็น Attachment ***
    public function destroy(Attachment $attachment)
    {
        // ลบไฟล์จริงออกจาก Storage
        if (Storage::disk('public')->exists($attachment->file_path)) {
            Storage::disk('public')->delete($attachment->file_path);
        }

        // ลบข้อมูลออกจากฐานข้อมูล
        $attachment->delete();

        return back()->with('success', 'ลบไฟล์เรียบร้อยแล้ว');
    }
}
