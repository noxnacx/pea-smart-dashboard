<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Kpi extends Model
{
    protected $fillable = ['name', 'description'];

    /**
     * ความสัมพันธ์ Many-to-Many กับ WorkItemType
     * อ้างอิงผ่านคอลัมน์ key (เช่น plan, project, task)
     */
    public function workItemTypes()
    {
        return $this->belongsToMany(
            WorkItemType::class,
            'kpi_work_item_type', // ชื่อตาราง Pivot
            'kpi_id',             // FK ฝั่ง KPI
            'work_item_type_key', // FK ฝั่ง WorkItemType
            'id',                 // PK ของ KPI
            'key'                 // PK อ้างอิงของ WorkItemType
        );
    }
}
