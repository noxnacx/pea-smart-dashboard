<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkItem extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'planned_start_date' => 'date',
        'planned_end_date' => 'date',
        'actual_start_date' => 'date',
        'actual_end_date' => 'date',
    ];

    // ส่งค่า EV, PV, SV, Status ไปหน้าบ้านเสมอ (Append)
    protected $appends = ['ev', 'pv', 'sv', 'performance_status'];

    // --- Relationship (ส่วนที่ขาดไป) ---

    // แม่ (Parent)
    public function parent()
    {
        return $this->belongsTo(WorkItem::class, 'parent_id');
    }

    // ลูก (Children)
    public function children()
    {
        return $this->hasMany(WorkItem::class, 'parent_id')->orderBy('order_index');
    }

    // ลูกหลานทั้งหมด (Recursive)
    public function allChildren()
    {
        // เพิ่ม 'attachments' เข้าไปใน with
        return $this->children()->with(['allChildren', 'attachments']);
    }

    // *** เพิ่มส่วนนี้: ความสัมพันธ์กับ Logs (ประวัติการทำงาน) ***
    public function logs()
    {
        return $this->hasMany(WorkItemLog::class)->orderBy('log_date', 'desc');
    }

    // ผู้รับผิดชอบ
    public function responsibleUser()
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    // --- Calculation Logic (สูตรคำนวณ) ---

    // 1. Earned Value (EV)
    public function getEvAttribute()
    {
        return $this->budget * ($this->progress / 100);
    }

    // 2. Planned Value (PV)
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

    // 3. Schedule Variance (SV)
    public function getSvAttribute()
    {
        return $this->ev - $this->pv;
    }

    // 4. Status String
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

    public function attachments()
    {
        // เปลี่ยนจาก WorkItemAttachment เป็น Attachment
        return $this->hasMany(Attachment::class);
    }

    public function recalculateProgress()
    {
        // 1. หาค่าเฉลี่ยจากลูกๆ
        $average = $this->children()->avg('progress');

        // 2. แปลงเป็นจำนวนเต็ม (Integer) ก่อนบันทึก **สำคัญมาก**
        // ใช้ round() เพื่อปัดเศษให้ถูกต้อง (เช่น 58.6 -> 59) แล้ว cast เป็น (int)
        $this->update([
            'progress' => (int) round($average)
        ]);

        // 3. ถ้ามีตัวแม่ขึ้นไปอีก ก็ให้คำนวณต่อขึ้นไปเรื่อยๆ (Recursive)
        if ($this->parent) {
            $this->parent->recalculateProgress();
        }
    }

// เพิ่มฟังก์ชันนี้ลงไปใน Class WorkItem
    public function comments()
    {
        return $this->hasMany(Comment::class)->orderBy('created_at', 'desc');
    }

    public function issues()
    {
        // แก้ไข: ใช้ CASE WHEN แทน FIELD() สำหรับ PostgreSQL
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

}
