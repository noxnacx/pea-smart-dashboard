<?php

namespace App\Http\Controllers;

use App\Models\WorkItemType;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Cache;

class WorkItemTypeController extends Controller
{
    public function index()
    {
        $types = WorkItemType::orderBy('level_order', 'asc')->get();
        return Inertia::render('Settings/WorkTypes', ['types' => $types]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'key' => 'required|string|unique:work_item_types,key',
            'icon' => 'nullable|string|max:50', // ✅ รับค่า icon
            'level_order' => 'required|integer|min:1',
            'color_code' => 'nullable|string'
        ]);

        WorkItemType::create($validated);
        $this->clearTreeCache();

        return back()->with('success', 'เพิ่มประเภทงานใหม่สำเร็จ');
    }

    public function update(Request $request, WorkItemType $workItemType)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'icon' => 'nullable|string|max:50', // ✅ รับค่า icon
            'level_order' => 'required|integer|min:1',
            'color_code' => 'nullable|string'
        ]);

        $workItemType->update($validated);
        $this->clearTreeCache();

        return back()->with('success', 'อัปเดตประเภทงานสำเร็จ');
    }

    public function destroy(WorkItemType $workItemType)
    {
        if ($workItemType->workItems()->count() > 0) {
            return back()->withErrors(['error' => 'ไม่สามารถลบได้ เนื่องจากมีงานที่ใช้ประเภทนี้อยู่']);
        }
        $workItemType->delete();
        $this->clearTreeCache();

        return back()->with('success', 'ลบประเภทงานสำเร็จ');
    }

    private function clearTreeCache()
    {
        Cache::forget('dashboard_hierarchy');
        Cache::forget('strategies_index_v2');
        Cache::forget('strategies_index');
    }
}
