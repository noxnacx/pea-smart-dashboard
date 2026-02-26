<?php

namespace App\Http\Controllers;

use App\Models\WorkItem;
use App\Models\Issue;
use App\Models\Attachment;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Cache;

class TrashController extends Controller
{
    public function index(Request $request)
    {
        $trashedIds = WorkItem::onlyTrashed()->pluck('id')->toArray();

        $query = WorkItem::onlyTrashed()
            ->where(function ($query) use ($trashedIds) {
                $query->whereNull('parent_id')
                      ->orWhereNotIn('parent_id', $trashedIds);
            })
            ->with(['workType'])
            ->with(['children' => function($q) {
                $q->onlyTrashed()->with(['workType', 'children' => function($sq) {
                    $sq->onlyTrashed()->with('workType');
                }]);
            }]);

        $query->where('deleted_at', '>=', now()->subDays(30));
        $deletedWorkItems = $query->orderBy('deleted_at', 'desc')->paginate(15);

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
        $restoredCount = $this->cascadeRestore($item);

        if ($item->parent_id) {
            $parent = WorkItem::find($item->parent_id);
            if ($parent) $parent->recalculateProgress();
        }

        Cache::forget('strategies_index_v2');

        return back()->with('success', "กู้คืนข้อมูลสำเร็จ ({$restoredCount} รายการ)");
    }

    private function cascadeRestore($item)
    {
        $count = 1;
        $itemName = $item->name; // ดึงชื่อมาก่อนกู้คืน
        $item->restore();

        // 🚀 บันทึก Log พร้อมสำรองชื่อไว้ใน Changes กันพลาด
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'RESTORE',
            'model_type' => 'WorkItem',
            'model_id' => $item->id,
            'target_name' => $itemName,
            'changes' => [
                'restored_name' => $itemName, // สำรองชื่อไว้ใน JSON ก๊อกสอง
                'note' => 'กู้คืนข้อมูลจากถังขยะ'
            ],
            'ip_address' => request()->ip(),
        ]);

        $trashedChildren = WorkItem::onlyTrashed()->where('parent_id', $item->id)->get();
        foreach ($trashedChildren as $child) {
            $count += $this->cascadeRestore($child);
        }
        return $count;
    }

    public function forceDeleteWorkItem($id)
    {
        $item = WorkItem::onlyTrashed()->findOrFail($id);
        $this->cascadeForceDelete($item);
        Cache::forget('strategies_index_v2');
        return back()->with('success', 'ลบข้อมูลถาวรสำเร็จ พร้อมงานย่อยทั้งหมด');
    }

    private function cascadeForceDelete($item)
    {
        $trashedChildren = WorkItem::onlyTrashed()->where('parent_id', $item->id)->get();
        foreach ($trashedChildren as $child) {
            $this->cascadeForceDelete($child);
        }
        $item->forceDelete();
    }
}
