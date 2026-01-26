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

class ReportController extends Controller
{
    public function index()
    {
        return Inertia::render('Report/Index');
    }

    // --- 1. รายงานความก้าวหน้า ---

    public function exportProgressPdf()
    {
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

        $fileName = 'progress-report-' . now()->format('Ymd-His') . '.pdf';

        $pdf = Pdf::loadView('reports.progress_pdf', [
            'strategies' => $strategies,
            'stats' => $stats,
            'date' => now()->format('d/m/Y')
        ])->setPaper('a4', 'landscape');

        // ✅ บันทึก Audit Log พร้อม IP Address
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'EXPORT',
            'model_type' => 'รายงานความก้าวหน้า (PDF)',
            'model_id' => 0,
            'ip_address' => request()->ip(), // ✅ เก็บ IP
            'target_name' => 'Progress Report',
            'changes' => ['ชื่อไฟล์' => $fileName],
        ]);

        return $pdf->stream($fileName);
    }

    public function exportProgressExcel()
    {
        $fileName = 'progress-report-' . now()->format('Ymd-His') . '.xlsx';

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'EXPORT',
            'model_type' => 'รายงานความก้าวหน้า (Excel)',
            'model_id' => 0,
            'ip_address' => request()->ip(), // ✅ เก็บ IP
            'target_name' => 'Progress Report',
            'changes' => ['ชื่อไฟล์' => $fileName],
        ]);

        return Excel::download(new ProjectProgressExport, $fileName);
    }

    public function exportProgressCsv()
    {
        $fileName = 'progress-report-' . now()->format('Ymd-His') . '.csv';

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'EXPORT',
            'model_type' => 'รายงานความก้าวหน้า (CSV)',
            'model_id' => 0,
            'ip_address' => request()->ip(), // ✅ เก็บ IP
            'target_name' => 'Progress Report',
            'changes' => ['ชื่อไฟล์' => $fileName],
        ]);

        return Excel::download(new ProjectProgressExport, $fileName, ExcelFormat::CSV);
    }

    // --- 2. รายงานปัญหา ---

    public function exportIssuesPdf()
    {
        $issues = Issue::with('workItem')->orderBy('severity')->get();
        $fileName = 'issues-report-' . now()->format('Ymd-His') . '.pdf';

        $pdf = Pdf::loadView('reports.issues_pdf', [
            'issues' => $issues,
            'date' => now()->format('d/m/Y')
        ])->setPaper('a4', 'portrait');

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'EXPORT',
            'model_type' => 'รายงานปัญหา (PDF)',
            'model_id' => 0,
            'ip_address' => request()->ip(), // ✅ เก็บ IP
            'target_name' => 'Issues Report',
            'changes' => ['ชื่อไฟล์' => $fileName],
        ]);

        return $pdf->stream($fileName);
    }

    public function exportIssuesExcel()
    {
        $fileName = 'issues-report-' . now()->format('Ymd-His') . '.xlsx';

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'EXPORT',
            'model_type' => 'รายงานปัญหา (Excel)',
            'model_id' => 0,
            'ip_address' => request()->ip(), // ✅ เก็บ IP
            'target_name' => 'Issues Report',
            'changes' => ['ชื่อไฟล์' => $fileName],
        ]);

        return Excel::download(new IssueRiskExport, $fileName);
    }

    public function exportIssuesCsv()
    {
        $fileName = 'issues-report-' . now()->format('Ymd-His') . '.csv';

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'EXPORT',
            'model_type' => 'รายงานปัญหา (CSV)',
            'model_id' => 0,
            'ip_address' => request()->ip(), // ✅ เก็บ IP
            'target_name' => 'Issues Report',
            'changes' => ['ชื่อไฟล์' => $fileName],
        ]);

        return Excel::download(new IssueRiskExport, $fileName, ExcelFormat::CSV);
    }

    // --- 3. รายงานผู้บริหาร ---

    public function exportExecutivePdf()
    {
        $stats = [
            'total' => WorkItem::where('type', 'project')->count(),
            'budget' => WorkItem::where('type', 'project')->sum('budget'),
            'critical_issues' => Issue::where('severity', 'critical')->count(),
        ];

        $topProjects = WorkItem::where('type', 'project')
            ->orderByDesc('budget')
            ->take(5)
            ->get();

        $fileName = 'executive-report-' . now()->format('Ymd-His') . '.pdf';

        $pdf = Pdf::loadView('reports.executive_pdf', [
            'stats' => $stats,
            'topProjects' => $topProjects,
            'date' => now()->format('d/m/Y')
        ])->setPaper('a4', 'portrait');

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'EXPORT',
            'model_type' => 'รายงานผู้บริหาร (PDF)',
            'model_id' => 0,
            'ip_address' => request()->ip(), // ✅ เก็บ IP
            'target_name' => 'Executive Report',
            'changes' => ['ชื่อไฟล์' => $fileName],
        ]);

        return $pdf->stream($fileName);
    }

    public function exportExecutiveExcel()
    {
        $fileName = 'executive-report-' . now()->format('Ymd-His') . '.xlsx';

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'EXPORT',
            'model_type' => 'รายงานผู้บริหาร (Excel)',
            'model_id' => 0,
            'ip_address' => request()->ip(), // ✅ เก็บ IP
            'target_name' => 'Executive Report',
            'changes' => ['ชื่อไฟล์' => $fileName],
        ]);

        return Excel::download(new ExecutiveSummaryExport, $fileName);
    }

    public function exportExecutiveCsv()
    {
        $fileName = 'executive-report-' . now()->format('Ymd-His') . '.csv';

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'EXPORT',
            'model_type' => 'รายงานผู้บริหาร (CSV)',
            'model_id' => 0,
            'ip_address' => request()->ip(), // ✅ เก็บ IP
            'target_name' => 'Executive Report',
            'changes' => ['ชื่อไฟล์' => $fileName],
        ]);

        return Excel::download(new ExecutiveSummaryExport, $fileName, ExcelFormat::CSV);
    }

    // --- 4. รายงานรายโครงการ ---

    public function exportWorkItemPdf($id)
    {
        $workItem = WorkItem::with(['children', 'issues', 'attachments', 'parent'])
            ->findOrFail($id);

        $fileName = 'project-' . $workItem->id . '-' . now()->format('Ymd') . '.pdf';

        $pdf = Pdf::loadView('reports.work_item_detail', [
            'item' => $workItem,
            'date' => now()->format('d/m/Y')
        ])->setPaper('a4', 'portrait');

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'EXPORT',
            'model_type' => 'WorkItem',
            'model_id' => $workItem->id,
            'ip_address' => request()->ip(), // ✅ เก็บ IP
            'target_name' => $workItem->name,
            'changes' => ['ชื่อไฟล์' => $fileName, 'ประเภท' => 'PDF รายละเอียดโครงการ'],
        ]);

        return $pdf->stream($fileName);
    }
}
