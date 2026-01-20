<?php

namespace App\Http\Controllers;

use App\Models\WorkItem;
use App\Models\WorkItemAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Redirect;

class AttachmentController extends Controller
{
    // อัปโหลดไฟล์
    public function store(Request $request, WorkItem $workItem)
    {
        $request->validate([
            'file' => 'required|file|max:10240', // Max 10MB
        ]);

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $path = $file->store('attachments/' . $workItem->id, 'public');

        $workItem->attachments()->create([
            'file_name' => $originalName,
            'file_path' => $path,
            'file_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'uploaded_by' => auth()->id(),
        ]);

        return back()->with('success', 'อัปโหลดไฟล์เรียบร้อยแล้ว');
    }

    // ดาวน์โหลดไฟล์
    public function download(WorkItemAttachment $attachment)
    {
        return Storage::disk('public')->download($attachment->file_path, $attachment->file_name);
    }

    // ลบไฟล์
    public function destroy(WorkItemAttachment $attachment)
    {
        Storage::disk('public')->delete($attachment->file_path);
        $attachment->delete();

        return back()->with('success', 'ลบไฟล์เรียบร้อยแล้ว');
    }
}
