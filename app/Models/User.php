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
        'division_id',
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

    // --- Helper Functions สำหรับเช็คสิทธิ์ ---

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isPm(): bool
    {
        // เช็คว่าเป็น PM หรือไม่ (ใช้ทั้ง boolean และ role string)
        return $this->is_pm || $this->role === 'project_manager' || $this->role === 'pm';
    }

    public function isGeneralUser(): bool
    {
        return !$this->isAdmin() && !$this->isPm();
    }

    // ✅ เพิ่มฟังก์ชันนี้ (Fix Error: Call to undefined method canEdit)
    public function canEdit(): bool
    {
        return $this->isAdmin() || $this->isPm();
    }

    // ฟังก์ชันเช็คสิทธิ์แบบละเอียด: PM แก้ได้เฉพาะงานตัวเอง
    public function canManageProject($workItem): bool
    {
        if ($this->isAdmin()) return true;
        // ถ้าเป็น PM ต้องเช็คว่าเป็นเจ้าของโปรเจคหรือไม่ (เช็คจาก project_manager_id ที่เป็น User ID แล้ว)
        if ($this->isPm() && $workItem->project_manager_id === $this->id) return true;
        return false;
    }

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
            'is_pm' => 'boolean',
        ];
    }

    // --- Relationships ---

    // 1. สังกัดกอง
    public function division()
    {
        return $this->belongsTo(Division::class);
    }

    // 2. สังกัดแผนก
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    // 3. งานที่ดูแล (ในฐานะ PM)
    public function projects()
    {
        return $this->hasMany(WorkItem::class, 'project_manager_id');
    }
}
