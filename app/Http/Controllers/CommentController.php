<?php

namespace App\Http\Controllers;

use App\Models\WorkItem;
use App\Models\Comment;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function store(Request $request, WorkItem $workItem)
    {
        $request->validate([
            'body' => 'required|string|max:1000',
        ]);

        $workItem->comments()->create([
            'user_id' => auth()->id(),
            'body' => $request->body
        ]);

        // Audit Log จะทำงานอัตโนมัติถ้าเราสร้าง Observer ให้ Comment (แต่ตอนนี้ปล่อยให้มันแยกกันดีกว่าครับ จะได้ไม่ซ้ำซ้อน)

        return back()->with('success', 'บันทึกความคิดเห็นแล้ว');
    }
}
