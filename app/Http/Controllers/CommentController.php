<?php

namespace App\Http\Controllers;

use App\Models\WorkItem;
use App\Models\Comment;
use App\Models\AuditLog; // ✅ เพิ่ม Audit Log
use App\Services\LineService; // ✅ เพิ่ม LINE Notification
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function store(Request $request, WorkItem $workItem)
    {
        $request->validate([
            'body' => 'required|string|max:1000',
        ]);

        // สร้าง Comment
        $comment = $workItem->comments()->create([
            'user_id' => auth()->id(),
            'body' => $request->body
        ]);

        // ✅ 1. อัปเดตเวลาของงานหลัก (เพื่อให้รู้ว่างานนี้มีความเคลื่อนไหวล่าสุด)
        $workItem->touch();

        // ✅ 2. บันทึก Audit Log (แทนการใช้ Observer)
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'COMMENT',
            'model_type' => 'Comment',
            'model_id' => $comment->id,
            'target_name' => $workItem->name, // ระบุชื่อโครงการที่ถูกเม้น
            'changes' => ['message' => $request->body],
            'ip_address' => $request->ip(),
        ]);

        // ✅ 3. ส่งแจ้งเตือนเข้า LINE (Optional: ถ้าต้องการให้ทีมรู้ทันที)
        try {
            $msg = "💬 ความคิดเห็นใหม่: " . $workItem->name . "\n" .
                   "👤 โดย: " . auth()->user()->name . "\n" .
                   "📝 ข้อความ: " . $request->body;
            LineService::sendPushMessage($msg);
        } catch (\Exception $e) {
            // กรณีส่งไม่ผ่าน (เช่น เน็ตหลุด) ไม่ต้อง throw error ให้ User เห็น
        }

        return back()->with('success', 'บันทึกความคิดเห็นแล้ว');
    }
}
