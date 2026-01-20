<?php

namespace App\Observers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class GlobalObserver
{
    public function created(Model $model)
    {
        $this->recordLog($model, 'CREATE', null);
    }

    public function updated(Model $model)
    {
        // บันทึกเฉพาะฟิลด์ที่มีการเปลี่ยนแปลง
        $changes = [
            'before' => array_intersect_key($model->getOriginal(), $model->getDirty()),
            'after' => $model->getDirty(),
        ];

        // ถ้าไม่มีอะไรเปลี่ยนจริง (เช่น กด save แต่ค่าเดิม) ไม่ต้องบันทึก
        if (empty($changes['after'])) return;

        $this->recordLog($model, 'UPDATE', $changes);
    }

    public function deleted(Model $model)
    {
        $this->recordLog($model, 'DELETE', $model->toArray());
    }

    protected function recordLog(Model $model, $action, $changes)
    {
        // ไม่บันทึก Log ของตัว Log เอง (เดี๋ยว Loop ไม่จบ)
        if ($model instanceof AuditLog) return;

        AuditLog::create([
            'user_id' => Auth::id(), // ถ้าเป็น System/Job อาจจะเป็น null
            'action' => $action,
            'model_type' => class_basename($model), // เก็บแค่ชื่อรุ่น เช่น WorkItem
            'model_id' => $model->id,
            'changes' => $changes,
            'ip_address' => request()->ip(),
        ]);
    }
}
