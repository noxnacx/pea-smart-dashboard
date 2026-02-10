<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; // ✅ เพิ่ม SoftDeletes ตามที่เห็นในโค้ดเก่าบางส่วน ถ้าไม่ได้ใช้ลบออกได้

class WorkItem extends Model
{
    use HasFactory;
    // use SoftDeletes; // เปิดใช้ถ้าต้องการ Soft Delete

    // ✅ 1. เพิ่ม weight และ is_active ใน fillable
    protected $fillable = [
        'name',
        'type',
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
        'weight',      // น้ำหนักงาน
        'is_active',    // สถานะเปิด/ปิด
        'description'
    ];

    protected $guarded = []; // ถ้าใช้ fillable แล้ว guarded ไม่จำเป็นต้องมีก็ได้ แต่ใส่ไว้ไม่เสียหายถ้าว่าง

    protected $casts = [
        'planned_start_date' => 'date',
        'planned_end_date' => 'date',
        'actual_start_date' => 'date',
        'actual_end_date' => 'date',
        'is_active' => 'boolean', // ✅ แปลงเป็น boolean อัตโนมัติ
        'weight' => 'float',      // ✅ แปลงเป็นตัวเลขทศนิยม
    ];

    // ส่งค่า EV, PV, SV, Status ไปหน้าบ้านเสมอ (Append)
    protected $appends = ['ev', 'pv', 'sv', 'performance_status'];

    // --- Relationship ---

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

    // ประวัติการทำงาน (Logs)
    public function logs()
    {
        return $this->hasMany(WorkItemLog::class)->orderBy('log_date', 'desc');
    }

    // ผู้รับผิดชอบ (PM)
    public function projectManager()
    {
        return $this->belongsTo(ProjectManager::class);
    }

    // สังกัดแผนก (Department)
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function division()
    {
        return $this->belongsTo(Division::class);
    }

    // เอกสารแนบ
    public function attachments()
    {
        return $this->hasMany(Attachment::class);
    }

    // คอมเมนต์
    public function comments()
    {
        return $this->hasMany(Comment::class)->orderBy('created_at', 'desc');
    }

    // ปัญหา/ความเสี่ยง
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

    // ✅ ฟังก์ชันคำนวณ Progress แบบถ่วงน้ำหนัก (Weighted Average)
    public function recalculateProgress()
    {
        // 1. ดึงลูกๆ เฉพาะที่ "เปิดใช้งาน" (Active) เท่านั้น
        $children = $this->children()->where('is_active', true)->get();

        if ($children->isEmpty()) {
            return; // ถ้าไม่มีลูกที่ Active เลย ไม่ต้องทำอะไร
        }

        // 2. หาน้ำหนักรวมทั้งหมด
        $totalWeight = $children->sum('weight');

        $aggregateProgress = 0;

        // 3. เริ่มคำนวณ
        if ($totalWeight > 0) {
            // กรณีมีการตั้งน้ำหนัก: คำนวณแบบ Weighted Average
            foreach ($children as $child) {
                // สัดส่วนความสำคัญ = น้ำหนักตัวเอง / น้ำหนักรวม
                $weightRatio = $child->weight / $totalWeight;
                // ผลรวม Progress = Progress ลูก * สัดส่วน
                $aggregateProgress += $child->progress * $weightRatio;
            }
        } else {
            // กรณีน้ำหนักเป็น 0 ทั้งหมด (User ไม่ได้กรอก): ใช้ค่าเฉลี่ยปกติ (Simple Average)
            $aggregateProgress = $children->avg('progress');
        }

        // 4. อัปเดตตัวเอง (แปลงเป็นจำนวนเต็ม หรือทศนิยมตามต้องการ)
        $this->update([
            'progress' => (int) round($aggregateProgress)
        ]);

        // 5. แจ้งพ่อให้คำนวณต่อ (Chain Reaction ขึ้นไปเรื่อยๆ)
        if ($this->parent) {
            $this->parent->recalculateProgress();
        }
    }
}
