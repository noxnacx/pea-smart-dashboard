<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProgressHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'work_item_id',
        'progress',
    ];

    public function workItem()
    {
        return $this->belongsTo(WorkItem::class);
    }
}
