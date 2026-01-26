<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\WorkItem;
use App\Models\Issue;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CalendarController extends Controller
{
    public function index(Request $request)
    {
        $events = [];

        // 1. Issues (ดึงมาไว้ก่อน และกำหนดให้ขึ้นเป็นอันดับแรก)
        $issues = Issue::query()
            ->whereNotNull('end_date')
            ->with('workItem') // Eager load งานแม่มาด้วย
            ->get();

        foreach ($issues as $issue) {
            $color = match ($issue->severity) {
                'critical' => '#ef4444',
                'high' => '#f97316',
                'medium' => '#eab308',
                'low' => '#22c55e',
                default => '#6b7280',
            };

            $events[] = [
                'id' => 'issue_' . $issue->id,
                'title' => '[แจ้งปัญหา] ' . $issue->title,
                'start' => $issue->start_date ?? $issue->end_date,
                'end' => $issue->end_date,
                'allDay' => true,
                'backgroundColor' => $color,
                'borderColor' => $color,
                'displayOrder' => 1,
                'extendedProps' => [
                    'type' => 'issue',
                    'severity' => $issue->severity,
                    'status' => $issue->status,
                    'description' => $issue->description, // ส่งรายละเอียดไปด้วย
                    'parent_name' => $issue->workItem ? $issue->workItem->name : '-',
                ],
                // ลิงก์ไปหน้า Work Item แม่
                'url' => route('work-items.show', $issue->work_item_id)
            ];
        }

        // 2. Work Items
        $workItems = WorkItem::query()
            ->whereIn('type', ['plan', 'project', 'task'])
            ->whereNotNull('planned_start_date')
            ->whereNotNull('planned_end_date')
            ->get();

        foreach ($workItems as $item) {
            $color = match ($item->type) {
                'plan' => '#3b82f6',
                'project' => '#8b5cf6',
                'task' => '#10b981',
                default => '#6b7280',
            };

            $events[] = [
                'id' => 'work_' . $item->id,
                'title' => $item->name,
                'start' => $item->planned_start_date,
                'end' => $item->planned_end_date,
                'backgroundColor' => $color,
                'borderColor' => $color,
                'displayOrder' => 2,
                'extendedProps' => [
                    'type' => 'work_item',
                    'work_type' => $item->type,
                    'status' => $item->status,
                    'progress' => $item->progress . '%',
                    'description' => $item->description,
                ],
                'url' => route('work-items.show', $item->id)
            ];
        }

        return Inertia::render('Calendar/Index', [
            'events' => $events
        ]);
    }
}
