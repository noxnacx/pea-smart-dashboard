<?php

namespace App\Jobs;

use App\Models\WorkItem;
use App\Models\AuditLog;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class GenerateProgressReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $userId;
    public $filters;

    // เวลาที่อนุญาตให้รัน (วินาที) ป้องกันค้าง
    public $timeout = 600;

    public function __construct($userId, $filters)
    {
        $this->userId = $userId;
        $this->filters = $filters;
    }

    public function handle()
    {
        try {
            // 1. ดึงข้อมูล (จำลอง Logic จาก ReportController)
            $query = WorkItem::whereNull('parent_id')
                ->with(['children' => function($q) {
                    if (!empty($this->filters['division_id'])) $q->where('division_id', $this->filters['division_id']);
                    $q->with(['children' => function($sq) {
                        if (!empty($this->filters['division_id'])) $sq->where('division_id', $this->filters['division_id']);
                    }]);
                }]);

            if (!empty($this->filters['strategy_id'])) {
                $query->where('id', $this->filters['strategy_id']);
            }

            $strategies = $query->get()->sortBy('name', SORT_NATURAL)->values();

            $statsQuery = WorkItem::where('type', 'project');
            if (!empty($this->filters['division_id'])) $statsQuery->where('division_id', $this->filters['division_id']);

            $stats = [
                'total' => $statsQuery->count(),
                'budget' => $statsQuery->sum('budget'),
                'completed' => (clone $statsQuery)->where('progress', 100)->count(),
            ];

            // 2. สร้าง PDF
            $pdf = Pdf::loadView('reports.progress_pdf', [
                'strategies' => $strategies,
                'stats' => $stats,
                'date' => now()->format('d/m/Y')
            ])->setPaper('a4', 'landscape');

            // 3. บันทึกไฟล์ลง Storage (รอให้คนมากดโหลด)
            $fileName = 'progress-report-' . now()->format('Ymd-His') . '.pdf';
            Storage::disk('public')->put('exports/' . $fileName, $pdf->output());

            // 4. บันทึก Log การ Export
            AuditLog::create([
                'user_id' => $this->userId,
                'action' => 'EXPORT',
                'model_type' => 'รายงานความก้าวหน้า (PDF - Background)',
                'model_id' => 0,
                'target_name' => 'Report Queue',
                'changes' => ['สถานะ' => 'สร้างไฟล์สำเร็จ', 'ชื่อไฟล์' => $fileName],
                'ip_address' => '127.0.0.1',
            ]);

            Log::info("✅ PDF Report Generated Successfully: {$fileName}");

        } catch (\Exception $e) {
            Log::error("❌ Failed to generate PDF Report: " . $e->getMessage());
        }
    }
}
