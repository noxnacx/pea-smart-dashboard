<?php

namespace App\Http\Controllers;

use App\Models\WorkItemType;
use Illuminate\Http\Request;
use Inertia\Inertia;

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
            'level_order' => 'required|integer|min:1',
            'color_code' => 'nullable|string'
        ]);

        WorkItemType::create($validated);
        return back()->with('success', 'เพิ่มประเภทงานใหม่สำเร็จ');
    }

    public function update(Request $request, WorkItemType $workItemType)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'level_order' => 'required|integer|min:1',
            'color_code' => 'nullable|string'
        ]);

        $workItemType->update($validated);
        return back()->with('success', 'อัปเดตประเภทงานสำเร็จ');
    }

    public function destroy(WorkItemType $workItemType)
    {
        if ($workItemType->workItems()->count() > 0) {
            return back()->withErrors(['error' => 'ไม่สามารถลบได้ เนื่องจากมีงานที่ใช้ประเภทนี้อยู่']);
        }

        $workItemType->delete();
        return back()->with('success', 'ลบประเภทงานสำเร็จ');
    }
}
