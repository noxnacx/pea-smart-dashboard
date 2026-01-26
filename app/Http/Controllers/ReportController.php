<?php

namespace App\Http\Controllers;

use App\Models\WorkItem;
use App\Models\Issue;
use Illuminate\Http\Request;
use Inertia\Inertia;
use PDF; // ใช้ barryvdh/laravel-dompdf
use Maatwebsite\Excel\Facades\Excel; // ใช้ maatwebsite/excel
use App\Exports\ProjectProgressExport;
use App\Exports\IssueRiskExport;
use App\Exports\ExecutiveSummaryExport;
use Maatwebsite\Excel\Excel as ExcelFormat;

class ReportController extends Controller
{
    // หน้าเลือกรายงาน (Frontend)
    public function index()
    {
        return Inertia::render('Report/Index');
    }

    // --- 1. รายงานความก้าวหน้า (Project Progress) ---

    public function exportProgressPdf()
    {
        // ดึงข้อมูล WorkItem ที่เป็น Root (ไม่มี Parent) พร้อมลูกหลาน
        $strategies = WorkItem::whereNull('parent_id')
            ->with(['children.children' => function($q) {
                $q->orderBy('order_index');
            }])
            ->orderBy('order_index')
            ->get();

        $stats = [
            'total' => WorkItem::where('type', 'project')->count(),
            'budget' => WorkItem::where('type', 'project')->sum('budget'),
            'completed' => WorkItem::where('type', 'project')->where('progress', 100)->count(),
        ];

        // โหลด View: reports.progress_pdf (คุณต้องสร้างไฟล์ blade นี้นะครับ ตามโค้ดก่อนหน้า)
        $pdf = PDF::loadView('reports.progress_pdf', [
            'strategies' => $strategies,
            'stats' => $stats,
            'date' => now()->format('d/m/Y')
        ])->setPaper('a4', 'landscape'); // แนวนอน

        return $pdf->stream('pea-progress-report.pdf');
    }

    public function exportProgressExcel()
    {
        // เรียกใช้ Export Class (ต้องสร้างไฟล์ App/Exports/ProjectProgressExport.php)
        return Excel::download(new ProjectProgressExport, 'pea-progress-report.xlsx');
    }

    // --- 2. รายงานปัญหาและความเสี่ยง (Issues & Risks) ---

    public function exportIssuesPdf()
    {
        $issues = Issue::with('workItem')->orderBy('severity')->get();

        $pdf = PDF::loadView('reports.issues_pdf', [
            'issues' => $issues,
            'date' => now()->format('d/m/Y')
        ])->setPaper('a4', 'portrait'); // แนวตั้ง

        return $pdf->stream('pea-issues-report.pdf');
    }

    public function exportIssuesExcel()
    {
        return Excel::download(new IssueRiskExport, 'pea-issues-report.xlsx');
    }

    // --- 3. รายงานสรุปผู้บริหาร (Executive Summary) ---

    public function exportExecutivePdf()
    {
        $stats = [
            'total' => WorkItem::where('type', 'project')->count(),
            'budget' => WorkItem::where('type', 'project')->sum('budget'),
            'critical_issues' => Issue::where('severity', 'critical')->count(),
        ];

        // 5 โครงการที่งบเยอะสุด
        $topProjects = WorkItem::where('type', 'project')
            ->orderByDesc('budget')
            ->take(5)
            ->get();

        $pdf = PDF::loadView('reports.executive_pdf', [
            'stats' => $stats,
            'topProjects' => $topProjects,
            'date' => now()->format('d/m/Y')
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('pea-executive-report.pdf');
    }

    public function exportExecutiveExcel()
    {
        return Excel::download(new ExecutiveSummaryExport, 'pea-executive-report.xlsx');
    }

    public function exportProgressCsv()
    {
        return Excel::download(new ProjectProgressExport, 'pea-progress-report.csv', ExcelFormat::CSV);
    }

    public function exportIssuesCsv()
    {
        return Excel::download(new IssueRiskExport, 'pea-issues-report.csv', ExcelFormat::CSV);
    }

    public function exportExecutiveCsv()
    {
        return Excel::download(new ExecutiveSummaryExport, 'pea-executive-report.csv', ExcelFormat::CSV);
    }
}
