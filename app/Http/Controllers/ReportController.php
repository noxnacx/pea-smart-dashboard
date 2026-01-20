<?php

namespace App\Http\Controllers;

use App\Models\WorkItem;
use Illuminate\Http\Request;
use Inertia\Inertia;
use PDF; // เรียกใช้ PDF Facade

class ReportController extends Controller
{
    // หน้าเลือกรายงาน (Frontend)
    public function index()
    {
        return Inertia::render('Report/Index');
    }

    // ฟังก์ชันสร้าง PDF (Backend)
    public function exportPdf()
    {
        // 1. ดึงข้อมูล (เหมือนหน้า Dashboard แต่ดึงทั้งหมด)
        $strategies = WorkItem::whereNull('parent_id')
            ->with([
                'children' => function($q) { $q->orderBy('order_index'); },
                'children.children' => function($q) { $q->orderBy('order_index'); }
            ])
            ->orderBy('order_index')
            ->get();

        $stats = [
            'total' => WorkItem::where('type', 'project')->count(),
            'budget' => WorkItem::where('type', 'project')->sum('budget'),
            'completed' => WorkItem::where('type', 'project')->where('progress', 100)->count(),
        ];

        // 2. ส่งข้อมูลไปที่หน้า View (Blade) เพื่อจัดรูปแบบ HTML
        $pdf = PDF::loadView('reports.master_pdf', [
            'strategies' => $strategies,
            'stats' => $stats,
            'date' => now()->format('d/m/Y') // วันที่พิมพ์รายงาน
        ]);

        // 3. ตั้งค่ากระดาษ A4 แนวนอน (Landscape) หรือแนวตั้ง (Portrait)
        $pdf->setPaper('a4', 'landscape');

        // 4. ดาวน์โหลดไฟล์ชื่อ 'pea-report.pdf'
        return $pdf->stream('pea-report.pdf');
    }
}
