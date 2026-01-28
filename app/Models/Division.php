<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Division extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'code'];

    // ความสัมพันธ์: กอง มีหลาย แผนก
    public function departments()
    {
        return $this->hasMany(Department::class);
    }
}
