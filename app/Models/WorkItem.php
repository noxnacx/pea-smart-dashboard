<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'work_item_type_id',
        'status',
        'progress',
        'budget',
        'planned_start_date',
        'planned_end_date',
        'parent_id',
        'project_manager_id',
        'division_id',
        'department_id',
        'order_index',
        'weight',
        'is_active',
        'description',
        'is_manual_description',
        // ✅ 13 ฟิลด์ใหม่สำหรับรายละเอียดโครงการ
        'rationale',
        'objective',
        'alignment',
        'scope_output',
        'architecture_image',
        'responsible_agency',
        'budget_framework',
        'kpi_details',
        'expected_benefits',
        'potential_impacts',
        'success_factors',
        'capability',
        'estimated_timeline',
    ];

    protected $guarded = [];

    protected $casts = [
        'planned_start_date' => 'date',
        'planned_end_date' => 'date',
        'actual_start_date' => 'date',
        'actual_end_date' => 'date',
        'is_active' => 'boolean',
        'is_manual_description' => 'boolean',
        'weight' => 'float',
    ];

    protected $appends = ['ev', 'pv', 'sv', 'performance_status', 'auto_description'];

    // --- Relationship ---

    public function workType()
    {
        return $this->belongsTo(WorkItemType::class, 'work_item_type_id');
    }

    public function milestones()
    {
        return $this->hasMany(Milestone::class);
    }

    public function progressHistories()
    {
        return $this->hasMany(ProgressHistory::class)->orderBy('created_at', 'desc');
    }

    public function parent()
    {
        return $this->belongsTo(WorkItem::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(WorkItem::class, 'parent_id')->orderBy('order_index');
    }

    public function allChildren()
    {
        return $this->children()->with(['allChildren', 'attachments']);
    }

    public function logs()
    {
        return $this->hasMany(WorkItemLog::class)->orderBy('log_date', 'desc');
    }

    public function projectManager()
    {
        return $this->belongsTo(User::class, 'project_manager_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function division()
    {
        return $this->belongsTo(Division::class);
    }

    public function attachments()
    {
        return $this->hasMany(Attachment::class);
    }

    public function comments()
    {
        return $this->hasMany(Comment::class)->orderBy('created_at', 'desc');
    }

    public function issues()
    {
        return $this->hasMany(Issue::class)->orderByRaw("
            CASE severity
                WHEN 'critical' THEN 1
                WHEN 'high' THEN 2
                WHEN 'medium' THEN 3
                WHEN 'low' THEN 4
                ELSE 5
            END
        ");
    }

    // ==========================================================
    // 🤖 ระบบสรุป Description อัตโนมัติจากงานลูก (อัปเกรดใหม่)
    // ==========================================================
    public function getAutoDescriptionAttribute()
    {
        if ($this->status === 'cancelled') return $this->description;

        $children = collect($this->children)->whereNotIn('status', ['cancelled', 'in_active']);

        if ($children->isEmpty()) {
            return "💡 ยังไม่มีงานย่อยที่กำลังดำเนินการในขณะนี้";
        }

        $delayed = $children->where('status', 'delayed');
        $inProgress = $children->where('status', 'in_progress');
        $completed = $children->where('status', 'completed');

        $text = "";

        if ($delayed->count() > 0) {
            $text .= "🔴 งานที่กำลังล่าช้า:\n";
            foreach ($delayed->take(2) as $t) {
                $text .= "- " . $t->name . " (" . $t->progress . "%)\n";
            }
        }

        if ($inProgress->count() > 0) {
            $text .= ($text ? "\n" : "") . "🔵 กำลังดำเนินการ:\n";
            foreach ($inProgress->take(3) as $t) {
                $text .= "- " . $t->name . " (" . $t->progress . "%)\n";
            }
        }

        if ($text === "" && $completed->count() > 0) {
            $text .= "🟢 ล่าสุด (เสร็จสมบูรณ์):\n";
            foreach ($completed->sortByDesc('updated_at')->take(2) as $t) {
                $text .= "- " . $t->name . " (" . $t->progress . "%)\n";
            }
        }

        return $text === "" ? "💡 กำลังดำเนินการงานย่อย" : $text;
    }

    // --- Calculation Logic ---
    public function getEvAttribute()
    {
        return $this->budget * ($this->progress / 100);
    }

    public function getPvAttribute()
    {
        $now = Carbon::now();
        if ($this->planned_start_date && $now->lt($this->planned_start_date)) return 0;
        if ($this->planned_end_date && $now->gt($this->planned_end_date)) return $this->budget;

        if ($this->planned_start_date && $this->planned_end_date) {
            $totalDays = $this->planned_start_date->diffInDays($this->planned_end_date) + 1;
            $daysPassed = $this->planned_start_date->diffInDays($now) + 1;
            $percent = $daysPassed / max(1, $totalDays);
            return $this->budget * $percent;
        }
        return 0;
    }

    public function getSvAttribute()
    {
        return $this->ev - $this->pv;
    }

    public function getPerformanceStatusAttribute()
    {
        if ($this->progress >= 100) return 'Completed';
        if ($this->budget == 0) return 'On Track';

        $variancePercent = ($this->sv / max(1, $this->budget)) * 100;

        if ($variancePercent < -10) return 'Critical Delayed';
        if ($variancePercent < 0) return 'Behind Schedule';
        if ($variancePercent > 0) return 'Ahead of Schedule';

        return 'On Track';
    }

    // ==========================================================
    // สูตรคำนวณความคืบหน้า (Progress) สะท้อนจากลูกสู่แม่
    // ==========================================================
    public function recalculateProgress()
    {
        $oldProgress = $this->progress;

        if ($this->children()->count() > 0) {
            $activeChildren = $this->children()->where('status', '!=', 'cancelled')->get();
            $totalWeight = $activeChildren->sum('weight');

            if ($totalWeight > 0) {
                $calculatedProgress = $activeChildren->sum(function ($child) {
                    return ($child->progress * $child->weight);
                }) / $totalWeight;

                $this->update(['progress' => round($calculatedProgress)]);
            } else {
                $this->update(['progress' => 0]);
            }
        }

        if ($oldProgress != $this->progress) {
            $this->progressHistories()->create(['progress' => $this->progress]);
        }

        if ($this->parent_id) {
            $parent = Self::find($this->parent_id);
            if ($parent) {
                $parent->recalculateProgress();
            }
        }
    }

    public function autoUpdateStatus()
    {
        if ($this->status === 'cancelled') return;

        if ($this->progress >= 100) {
            $this->progress = 100;
            $this->status = 'completed';
            return;
        }

        if ($this->planned_end_date && $this->progress < 100) {
            $endDate = \Carbon\Carbon::parse($this->planned_end_date)->endOfDay();
            if (now()->greaterThan($endDate)) {
                $this->status = 'delayed';
                return;
            }
        }

        if ($this->progress > 0 && $this->progress < 100) {
            $this->status = 'in_progress';
            return;
        }

        if ($this->progress == 0) {
            $this->status = 'in_active';
            return;
        }
    }
}
