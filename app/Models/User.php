<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        // ✅ เพิ่มฟิลด์ใหม่ (Phase 1)
        'department_id',
        'is_pm',
        'position',
        'phone',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function isAdmin() { return $this->role === 'admin'; }
    public function isPM() { return $this->role === 'pm' || $this->role === 'admin'; }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_pm' => 'boolean', // ✅ Cast เป็น boolean เพื่อความชัวร์
        ];
    }

    // --- ✅ เพิ่ม Relationships (แก้ปัญหา Call to undefined relationship) ---

    // 1. User สังกัดแผนก
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    // 2. User เป็น PM ดูแลหลายโครงการ
    public function responsibleProjects()
    {
        return $this->hasMany(WorkItem::class, 'responsible_user_id');
    }
}
