<?php

namespace App\Http\Controllers;

use App\Models\StrategicAlignment;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StrategicAlignmentController extends Controller
{
    public function index()
    {
        $alignments = StrategicAlignment::orderBy('key', 'asc')->get();
        return Inertia::render('StrategicAlignment/Index', [
            'alignments' => $alignments
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'key' => 'required|string|unique:strategic_alignments,key',
            'description' => 'required|string',
        ]);
        StrategicAlignment::create($validated);
        return back()->with('success', 'เพิ่มข้อมูลยุทธศาสตร์สำเร็จ');
    }

    public function update(Request $request, StrategicAlignment $strategicAlignment)
    {
        $validated = $request->validate([
            'key' => 'required|string|unique:strategic_alignments,key,' . $strategicAlignment->id,
            'description' => 'required|string',
        ]);
        $strategicAlignment->update($validated);
        return back()->with('success', 'แก้ไขข้อมูลยุทธศาสตร์สำเร็จ');
    }

    public function destroy(StrategicAlignment $strategicAlignment)
    {
        $strategicAlignment->delete();
        return back()->with('success', 'ลบข้อมูลสำเร็จ');
    }
}
