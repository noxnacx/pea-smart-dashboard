<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; // ✅ เอาคอมเมนต์ออก

class WorkItem extends Model
{
    use HasFactory, SoftDeletes; // ✅ นำ SoftDeletes กลับมาใช้

    protected $fillable = [
        'name',
        'type',
        'work_item_type_id', // ✅ เพิ่มคอลัมน์ใหม่สำหรับเก็บประเภทแบบ Dynamic
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

    // ✅ 1. ความสัมพันธ์กับตารางประเภทงาน (Dynamic Hierarchy)
    public function workType()
    {
        return $this->belongsTo(WorkItemType::class, 'work_item_type_id');
    }

    // ✅ 2. ความสัมพันธ์กับตาราง Milestones (Auto-Milestone)
    public function milestones()
    {
        return $this->hasMany(Milestone::class);
    }

    // (ส่วนดั้งเดิมทั้งหมดคงไว้เหมือนเดิมเป๊ะ)
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

    // ==========================================================
    // สูตรคำนวณความคืบหน้า (Progress) สะท้อนจากลูกสู่แม่
    // ==========================================================
    public function recalculateProgress()
    {
        // ถ้ามีลูก ห้ามใช้ค่าที่กรอกมือเด็ดขาด ให้คำนวณจากลูกเท่านั้น
        if ($this->children()->count() > 0) {
            // ไม่เอาลูกที่ถูกยกเลิกมาคำนวณ
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

        // ส่งต่อให้แม่คำนวณตัวเองใหม่ด้วย (Real-time Cascade)
        if ($this->parent_id) {
            $parent = Self::find($this->parent_id);
            if ($parent) {
                $parent->recalculateProgress();
            }
        }
    }

    // ==========================================================
    // กฏการปรับสถานะอัตโนมัติ (Auto Update Status Logic)
    // ==========================================================
    public function autoUpdateStatus()
    {
        // กฏข้อที่ 1: ถ้ายกเลิกไปแล้ว ห้ามระบบ Auto เข้าไปยุ่งเด็ดขาด
        if ($this->status === 'cancelled') {
            return;
        }

        // กฏข้อที่ 2: ถ้าครบ 100% = เสร็จแล้ว (Completed)
        if ($this->progress >= 100) {
            $this->progress = 100; // ล็อคไว้ไม่ให้เกิน 100
            $this->status = 'completed';
            return;
        }

        // กฏข้อที่ 3: ล่าช้า (Delayed)
        // -> ถ้ามีวันสิ้นสุด และ วันนี้ผ่านวันสิ้นสุดมาแล้ว (End of Day) แต่ยังไม่ 100%
        if ($this->planned_end_date && $this->progress < 100) {
            $endDate = \Carbon\Carbon::parse($this->planned_end_date)->endOfDay();
            if (now()->greaterThan($endDate)) {
                $this->status = 'delayed';
                return;
            }
        }

        // กฏข้อที่ 4: อัปเดตงานครั้งแรก (Progress > 0) = กำลังดำเนินการ (In Progress)
        if ($this->progress > 0 && $this->progress < 100) {
            $this->status = 'in_progress';
            return;
        }

        // กฏข้อที่ 5: เพิ่งสร้างงาน หรือ Progress กลับมาเป็น 0 = รอเริ่ม (In Active)
        if ($this->progress == 0) {
            $this->status = 'in_active';
            return;
        }
    }
}
