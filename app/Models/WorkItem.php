<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
// use Illuminate\Database\Eloquent\SoftDeletes;

class WorkItem extends Model
{
    use HasFactory;
    // use SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'status',
        'progress',
        'budget',
        'planned_start_date',
        'planned_end_date',
        'parent_id',
        'project_manager_id', // ✅ ID นี้ตอนนี้คือ User ID
        'division_id',
        'department_id',
        'order_index',
        'weight',
        'is_active',
        'description'
    ];

    protected $guarded = [];

    protected $casts = [
        'planned_start_date' => 'date',
        'planned_end_date' => 'date',
        'actual_start_date' => 'date',
        'actual_end_date' => 'date',
        'is_active' => 'boolean',
        'weight' => 'float',
    ];

    protected $appends = ['ev', 'pv', 'sv', 'performance_status'];

    // --- Relationship ---

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

    // ✅ เปลี่ยนให้ชี้ไปที่ User Model
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

    // --- Calculation Logic (คงเดิม) ---

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

        $variancePercent = ($this->sv / $this->budget) * 100;

        if ($variancePercent < -10) return 'Critical Delayed';
        if ($variancePercent < 0) return 'Behind Schedule';
        if ($variancePercent > 0) return 'Ahead of Schedule';

        return 'On Track';
    }

    public function recalculateProgress()
    {
        $children = $this->children()->where('is_active', true)->get();

        if ($children->isEmpty()) {
            return;
        }

        $totalWeight = $children->sum('weight');
        $aggregateProgress = 0;

        if ($totalWeight > 0) {
            foreach ($children as $child) {
                $weightRatio = $child->weight / $totalWeight;
                $aggregateProgress += $child->progress * $weightRatio;
            }
        } else {
            $aggregateProgress = $children->avg('progress');
        }

        $this->update([
            'progress' => (int) round($aggregateProgress)
        ]);

        if ($this->parent) {
            $this->parent->recalculateProgress();
        }
    }
}
