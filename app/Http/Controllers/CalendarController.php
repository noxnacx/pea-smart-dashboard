<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Issue;
use App\Models\WorkItem;
use App\Models\AuditLog;
use App\Models\Division;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Barryvdh\DomPDF\Facade\Pdf;
use Inertia\Inertia;

class CalendarController extends Controller
{
    /**
     * Display the calendar view.
     */
    public function index(Request $request)
    {
        // 1. รับค่า Filter
        $defaultFilters = [
            'types' => ['plan', 'project', 'task', 'issue'],
            'division_id' => null,
            'department_id' => null,
        ];

        $requestFilters = $request->input('filters', []);
        if (!is_array($requestFilters)) $requestFilters = [];

        $filters = array_merge($defaultFilters, $requestFilters);
        $selectedTypes = $filters['types'] ?? [];
        $divisionId = $filters['division_id'] ?? null;
        $departmentId = $filters['department_id'] ?? null;

        // 2. ดึงข้อมูล Issue
        $issues = collect([]);
        if (in_array('issue', $selectedTypes)) {
            $issueQuery = Issue::with('workItem:id,name');

            if ($divisionId) {
                $issueQuery->whereHas('workItem', function($q) use ($divisionId) {
                    $q->where('division_id', $divisionId);
                });
            }
            if ($departmentId) {
                $issueQuery->whereHas('workItem', function($q) use ($departmentId) {
                    $q->where('department_id', $departmentId);
                });
            }

            $issues = $issueQuery->get()->map(function ($issue) {
                $color = match ($issue->severity) {
                    'critical' => '#ef4444',
                    'high' => '#f97316',
                    'medium' => '#eab308',
                    default => '#22c55e',
                };

                return [
                    'id' => 'issue_' . $issue->id,
                    'title' => $issue->title,
                    'start' => $issue->start_date ?? $issue->created_at->format('Y-m-d'),
                    'end' => $issue->end_date,
                    'backgroundColor' => $color,
                    'borderColor' => $color,
                    'textColor' => '#ffffff',
                    'displayOrder' => 1,
                    'extendedProps' => [
                        'type' => 'issue',
                        'severity' => $issue->severity,
                        'status' => $issue->status,
                        'description' => $issue->description,
                        'solution' => $issue->solution,
                        'parent_name' => $issue->workItem ? $issue->workItem->name : '-',
                        'url' => null,
                    ]
                ];
            });
        }

        // 3. ดึงข้อมูล WorkItem
        $workItems = collect([]);
        if (array_intersect(['plan', 'project', 'task'], $selectedTypes)) {
            $query = WorkItem::whereIn('type', $selectedTypes)
                ->select('id', 'name', 'type', 'status', 'progress', 'planned_start_date', 'planned_end_date', 'parent_id')
                ->with('parent:id,name');

            if ($divisionId) $query->where('division_id', $divisionId);
            if ($departmentId) $query->where('department_id', $departmentId);

            $workItems = $query->get()->map(function ($item) {
                $color = match ($item->type) {
                    'plan' => '#3b82f6',
                    'project' => '#8b5cf6',
                    'task' => '#10b981',
                    default => '#6b7280',
                };

                return [
                    'id' => 'work_' . $item->id,
                    'title' => $item->name,
                    'start' => $item->planned_start_date,
                    'end' => $item->planned_end_date,
                    'backgroundColor' => $color,
                    'borderColor' => $color,
                    'textColor' => '#ffffff',
                    'displayOrder' => 2,
                    'extendedProps' => [
                        'type' => 'work_item',
                        'work_type' => $item->type,
                        'status' => $item->status,
                        'progress' => $item->progress . '%',
                        'parent_name' => $item->parent ? $item->parent->name : '-',
                        'url' => route('work-items.show', $item->id),
                    ]
                ];
            });
        }

        // 4. รวม Events
        $events = $issues->concat($workItems);

        // 5. ดึงข้อมูล Divisions
        $divisions = Division::with('departments')->orderBy('name')->get();

        return Inertia::render('Calendar/Index', [
            'events' => $events,
            'filters' => $filters,
            'divisions' => $divisions
        ]);
    }

    /**
     * Export Calendar to PDF (Agenda View)
     */
    public function exportAgendaPdf(Request $request)
    {
        $type = $request->input('type', 'month');
        $dateInput = $request->input('date', now()->format('Y-m-d'));

        $divisionId = $request->input('division_id');
        $departmentId = $request->input('department_id');

        $baseDate = Carbon::parse($dateInput)->locale('th');
        $start = null; $end = null; $reportTitle = "";

        switch ($type) {
            case 'year':
                $start = $baseDate->copy()->startOfYear();
                $end = $baseDate->copy()->endOfYear();
                $reportTitle = "รายงานประจำปี " . ($start->year + 543);
                break;
            case 'week':
                $start = $baseDate->copy()->startOfWeek();
                $end = $baseDate->copy()->endOfWeek();
                $reportTitle = "รายงานประจำสัปดาห์ (" . $start->format('d/m/y') . " - " . $end->format('d/m/Y') . ")";
                break;
            case 'day':
                $start = $baseDate->copy()->startOfDay();
                $end = $baseDate->copy()->endOfDay();
                $reportTitle = "รายงานประจำวันที่ " . $start->isoFormat('D MMMM YYYY');
                break;
            case 'month':
            default:
                $start = $baseDate->copy()->startOfMonth();
                $end = $baseDate->copy()->endOfMonth();
                $reportTitle = "รายงานประจำเดือน " . $start->monthName . " " . ($start->year + 543);
                break;
        }

        if ($divisionId) {
            $div = Division::find($divisionId);
            if ($div) $reportTitle .= " (" . $div->name . ")";
        }

        // Query Issues
        $issueQuery = Issue::where(function($q) use ($start, $end) {
            $q->whereBetween('start_date', [$start, $end])
              ->orWhereBetween('end_date', [$start, $end]);
        });
        if ($divisionId) $issueQuery->whereHas('workItem', fn($q) => $q->where('division_id', $divisionId));
        if ($departmentId) $issueQuery->whereHas('workItem', fn($q) => $q->where('department_id', $departmentId));
        $issues = $issueQuery->get();

        // Query WorkItems
        $workQuery = WorkItem::whereIn('type', ['project', 'plan', 'task'])
            ->where(function($q) use ($start, $end) {
                 $q->where('planned_start_date', '<=', $end)
                   ->where('planned_end_date', '>=', $start);
            });
        if ($divisionId) $workQuery->where('division_id', $divisionId);
        if ($departmentId) $workQuery->where('department_id', $departmentId);
        $workItems = $workQuery->get();

        $calendarData = [];
        $period = CarbonPeriod::create($start, $end);

        foreach ($period as $date) {
            $currentDate = $date->copy();
            $dayEvents = [];

            foreach ($issues as $issue) {
                $iStart = $issue->start_date ? Carbon::parse($issue->start_date) : null;
                $iEnd = $issue->end_date ? Carbon::parse($issue->end_date) : null;
                if (($iStart && $currentDate->between($iStart, $iEnd)) || ($iEnd && $currentDate->isSameDay($iEnd))) {
                    $dayEvents[] = ['type' => 'issue', 'title' => $issue->title, 'severity' => $issue->severity];
                }
            }

            foreach ($workItems as $item) {
                $wStart = Carbon::parse($item->planned_start_date);
                $wEnd = Carbon::parse($item->planned_end_date);
                if ($currentDate->between($wStart, $wEnd)) {
                    $dayEvents[] = ['type' => $item->type, 'title' => $item->name, 'status' => $item->status, 'progress' => $item->progress];
                }
            }

            // ✅ แก้ไข: ข้ามวันนั้นไปเลย หากไม่มีงานในวันนั้น (สำหรับทุกรูปแบบรายงาน)
            if (count($dayEvents) == 0) {
                continue;
            }

            $calendarData[] = [
                'raw_date' => $currentDate->format('Y-m-d'),
                'day' => $currentDate->day,
                'weekday' => $currentDate->isoFormat('ddd'),
                'month_label' => $currentDate->isoFormat('MMM'),
                'month_full' => $currentDate->isoFormat('MMMM YYYY'),
                'events' => $dayEvents
            ];
        }

        $pdf = Pdf::loadView('reports.calendar_agenda_pdf', [
            'calendarData' => $calendarData,
            'reportTitle' => $reportTitle,
            'viewType' => $type
        ]);

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'EXPORT',
            'model_type' => 'ปฏิทินงาน (Calendar)',
            'model_id' => 0,
            'ip_address' => request()->ip(),
            'target_name' => $reportTitle,
            'changes' => [
                'ประเภท' => $type,
                'วันที่เลือก' => $dateInput,
                'กอง' => $divisionId,
                'รูปแบบ' => 'PDF'
            ],
        ]);

        return $pdf->stream("agenda-report.pdf");
    }
}
