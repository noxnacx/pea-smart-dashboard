<?php

namespace App\Http\Controllers;

use App\Models\WorkItem;
use App\Models\Issue;
use App\Models\Attachment;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TrashController extends Controller
{
    public function index()
    {
        // ดึงเฉพาะข้อมูลที่ถูก Soft Delete (ลบไม่เกิน 30 วัน)
        $deletedWorkItems = WorkItem::onlyTrashed()
            ->where('deleted_at', '>=', now()->subDays(30))
            ->orderBy('deleted_at', 'desc')
            ->get();

        $deletedIssues = Issue::onlyTrashed()
            ->where('deleted_at', '>=', now()->subDays(30))
            ->orderBy('deleted_at', 'desc')
            ->get();

        return Inertia::render('Settings/Trash', [
            'deletedWorkItems' => $deletedWorkItems,
            'deletedIssues' => $deletedIssues,
        ]);
    }

    public function restoreWorkItem($id)
    {
        $item = WorkItem::onlyTrashed()->findOrFail($id);
        $item->restore();

        // บันทึก Log
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'RESTORE',
            'model_type' => 'WorkItem',
            'model_id' => $item->id,
            'target_name' => $item->name,
            'ip_address' => request()->ip(),
        ]);

        return back()->with('success', 'กู้คืนข้อมูลสำเร็จ');
    }

    public function forceDeleteWorkItem($id)
    {
        $item = WorkItem::onlyTrashed()->findOrFail($id);
        $item->forceDelete();

        return back()->with('success', 'ลบข้อมูลถาวรสำเร็จ');
    }
}
