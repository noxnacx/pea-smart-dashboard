<?php

namespace App\Http\Controllers;

use App\Models\StrategicAlignment;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StrategicAlignmentController extends Controller
{
    public function index(Request $request)
    {
        $query = StrategicAlignment::query();

        // 🚀 ระบบค้นหาแบบ Case-Insensitive (รองรับ PostgreSQL โดยใช้ ILIKE)
        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                // Laravel จะใส่ "key" (Double Quote) ให้อัตโนมัติ ป้องกัน Error คำสงวน
                // และ ILIKE จะจัดการค้นหาแบบไม่สนพิมพ์เล็ก-พิมพ์ใหญ่ให้เอง
                $q->where('key', 'ILIKE', "%{$search}%")
                  ->orWhere('description', 'ILIKE', "%{$search}%");
            });
        }

        // เรียงลำดับ KEY ตามตัวอักษร (A-Z) และแบ่งหน้าละ 10 รายการ
        $alignments = $query->orderBy('key', 'asc')->paginate(10)->withQueryString();

        return Inertia::render('StrategicAlignment/Index', [
            'alignments' => $alignments,
            'filters' => $request->only('search'),
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
