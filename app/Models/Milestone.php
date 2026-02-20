<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Milestone extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'work_item_id',
        'title',
        'due_date',
        'status', // pending, completed
        'remarks'
    ];

    protected $casts = [
        'due_date' => 'date',
    ];

    public function workItem()
    {
        return $this->belongsTo(WorkItem::class);
    }
}
