<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkItem;

class WorkItemPolicy
{
    /**
     * ใครดูได้บ้าง? (ทุกคนที่เป็น Member ดูได้หมด)
     */
    public function view(User $user, WorkItem $workItem)
    {
        return true;
    }

    /**
     * ใครสร้างได้บ้าง? (เฉพาะ Admin และ PM)
     */
    public function create(User $user)
    {
        return in_array($user->role, ['admin', 'pm']);
    }

    /**
     * ใครแก้ไขได้บ้าง? (เฉพาะ Admin และ PM)
     */
    public function update(User $user, WorkItem $workItem)
    {
        return in_array($user->role, ['admin', 'pm']);
    }

    /**
     * ใครลบได้บ้าง? (เฉพาะ Admin และ PM)
     */
    public function delete(User $user, WorkItem $workItem)
    {
        return in_array($user->role, ['admin', 'pm']);
    }
}
