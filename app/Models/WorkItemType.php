<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkItemType extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'key',
        'icon',
        'level_order',
        'color_code',
        'is_active'
    ];

    public function workItems()
    {
        return $this->hasMany(WorkItem::class);
    }
}
