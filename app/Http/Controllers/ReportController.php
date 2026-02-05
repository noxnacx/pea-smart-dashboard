<?php

namespace App\Http\Controllers;

use App\Models\WorkItem;
use App\Models\Issue;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ProjectProgressExport;
use App\Exports\IssueRiskExport;
use App\Exports\ExecutiveSummaryExport;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Illuminate\Support\Facades\Cache; // ✅ เพิ่ม Cache Facade

class ReportController extends Controller
{
    public function index()
    {
        return Inertia::render('Report/Index');
    }

    // =========================================================================
    // 1. รายงานความก้าวหน้า (Progress Report)
    // =========================================================================

    public function exportProgressPdf()
    {
        // 🚀 CACHE: เก็บข้อมูล 10 นาที (ป้องกันการกดรัวๆ)
        $data = Cache::remember('report_progress_pdf_data', 600, function () {
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

            return compact('strategies', 'stats');
        });

        $fileName = 'progress-report-' . now()->format('Ymd-His') . '.pdf';

        $pdf = Pdf::loadView('reports.progress_pdf', [
            'strategies' => $data['strategies'],
            'stats' => $data['stats'],
            'date' => now()->format('d/m/Y')
        ])->setPaper('a4', 'landscape');

        // ✅ บันทึก Log
        $this->logExport('รายงานความก้าวหน้า (PDF)', $fileName);

        return $pdf->stream($fileName);
    }

    public function exportProgressExcel()
    {
        $fileName = 'progress-report-' . now()->format('Ymd-His') . '.xlsx';
        $this->logExport('รายงานความก้าวหน้า (Excel)', $fileName);
        return Excel::download(new ProjectProgressExport, $fileName);
    }

    public function exportProgressCsv()
    {
        $fileName = 'progress-report-' . now()->format('Ymd-His') . '.csv';
        $this->logExport('รายงานความก้าวหน้า (CSV)', $fileName);
        return Excel::download(new ProjectProgressExport, $fileName, ExcelFormat::CSV);
    }

    // =========================================================================
    // 2. รายงานปัญหา (Issues Report)
    // =========================================================================

    public function exportIssuesPdf()
    {
        // ไม่ Cache เพราะต้องการข้อมูล Real-time ล่าสุดเสมอสำหรับปัญหา
        $issues = Issue::with('workItem')->orderBy('severity')->get();
        $fileName = 'issues-report-' . now()->format('Ymd-His') . '.pdf';

        $pdf = Pdf::loadView('reports.issues_pdf', [
            'issues' => $issues,
            'date' => now()->format('d/m/Y')
        ])->setPaper('a4', 'portrait');

        $this->logExport('รายงานปัญหา (PDF)', $fileName);

        return $pdf->stream($fileName);
    }

    public function exportIssuesExcel()
    {
        $fileName = 'issues-report-' . now()->format('Ymd-His') . '.xlsx';
        $this->logExport('รายงานปัญหา (Excel)', $fileName);
        return Excel::download(new IssueRiskExport, $fileName);
    }

    public function exportIssuesCsv()
    {
        $fileName = 'issues-report-' . now()->format('Ymd-His') . '.csv';
        $this->logExport('รายงานปัญหา (CSV)', $fileName);
        return Excel::download(new IssueRiskExport, $fileName, ExcelFormat::CSV);
    }

    // =========================================================================
    // 3. รายงานผู้บริหาร (Executive Report)
    // =========================================================================

    public function exportExecutivePdf()
    {
        // 🚀 CACHE: เก็บข้อมูล 15 นาที
        $data = Cache::remember('report_executive_pdf_data', 900, function () {
            $stats = [
                'total' => WorkItem::where('type', 'project')->count(),
                'budget' => WorkItem::where('type', 'project')->sum('budget'),
                'critical_issues' => Issue::where('severity', 'critical')->count(),
            ];

            $topProjects = WorkItem::where('type', 'project')
                ->orderByDesc('budget')
                ->take(5)
                ->get();

            return compact('stats', 'topProjects');
        });

        $fileName = 'executive-report-' . now()->format('Ymd-His') . '.pdf';

        $pdf = Pdf::loadView('reports.executive_pdf', [
            'stats' => $data['stats'],
            'topProjects' => $data['topProjects'],
            'date' => now()->format('d/m/Y')
        ])->setPaper('a4', 'portrait');

        $this->logExport('รายงานผู้บริหาร (PDF)', $fileName);

        return $pdf->stream($fileName);
    }

    public function exportExecutiveExcel()
    {
        $fileName = 'executive-report-' . now()->format('Ymd-His') . '.xlsx';
        $this->logExport('รายงานผู้บริหาร (Excel)', $fileName);
        return Excel::download(new ExecutiveSummaryExport, $fileName);
    }

    public function exportExecutiveCsv()
    {
        $fileName = 'executive-report-' . now()->format('Ymd-His') . '.csv';
        $this->logExport('รายงานผู้บริหาร (CSV)', $fileName);
        return Excel::download(new ExecutiveSummaryExport, $fileName, ExcelFormat::CSV);
    }

    // =========================================================================
    // 4. รายงานรายโครงการ (Project Detail)
    // =========================================================================

    public function exportWorkItemPdf($id)
    {
        // 🚀 CACHE: เก็บข้อมูล 1 นาที (เผื่อกดซ้ำๆ)
        $workItem = Cache::remember("report_project_{$id}", 60, function () use ($id) {
            return WorkItem::with(['children', 'issues', 'attachments', 'parent'])
                ->findOrFail($id);
        });

        $fileName = 'project-' . $workItem->id . '-' . now()->format('Ymd') . '.pdf';

        $pdf = Pdf::loadView('reports.work_item_detail', [
            'item' => $workItem,
            'date' => now()->format('d/m/Y')
        ])->setPaper('a4', 'portrait');

        $this->logExport('WorkItem Detail (PDF)', $fileName, $workItem->id, $workItem->name);

        return $pdf->stream($fileName);
    }

    // --- Helper for Logging ---
    private function logExport($type, $fileName, $modelId = 0, $targetName = null)
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'EXPORT',
            'model_type' => $type,
            'model_id' => $modelId,
            'ip_address' => request()->ip(), // ✅ เก็บ IP
            'target_name' => $targetName ?? $type,
            'changes' => ['ชื่อไฟล์' => $fileName],
        ]);
    }
}
