<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkItem;

class WorkItemPolicy
{
    /**
     * 👑 ด่านแรก: ตรวจสอบสิทธิ์สูงสุด (Super Admin)
     * ถ้า User คนนี้เป็น 'admin' จะให้ผ่าน (return true) ทุก Action ทันทีโดยไม่ต้องเช็คข้ออื่น
     */
    public function before(User $user, $ability)
    {
        if ($user->role === 'admin') {
            return true;
        }
    }

    /**
     * ใครดูได้บ้าง? (ทุกคนที่ Login เข้ามาดูได้หมด)
     */
    public function view(User $user, WorkItem $workItem)
    {
        return true;
    }

    /**
     * ใครสร้างงานใหม่ได้บ้าง? (เฉพาะ PM เพราะ Admin ผ่านด่าน before ไปแล้ว)
     */
    public function create(User $user)
    {
        return $user->role === 'pm';
    }

    /**
     * 🔒 ใครแก้ไขได้บ้าง?
     * (เฉพาะ PM ที่เป็น "ผู้ดูแลโครงการ (project_manager_id)" ของงานนี้เท่านั้น ถึงจะแก้ได้)
     */
    public function update(User $user, WorkItem $workItem)
    {
        return $user->id === $workItem->project_manager_id;
    }

    /**
     * 🔒 ใครลบได้บ้าง?
     * (เฉพาะ PM ที่เป็นเจ้าของงานนี้เท่านั้น)
     */
    public function delete(User $user, WorkItem $workItem)
    {
        return $user->id === $workItem->project_manager_id;
    }
}
