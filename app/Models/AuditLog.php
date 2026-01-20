<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id', 'action', 'model_type', 'model_id', 'changes', 'ip_address'
    ];

    protected $casts = [
        'changes' => 'array', // แปลง JSON เป็น Array อัตโนมัติ
    ];

    // เชื่อมกับตาราง User เพื่อดึงชื่อคนทำ
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
