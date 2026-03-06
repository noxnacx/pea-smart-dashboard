<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            // อนุญาตให้ end_date เป็นค่าว่าง (null) ได้
            $table->date('end_date')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            // คืนค่ากลับ (ถ้าจำเป็นต้อง rollback)
            $table->date('end_date')->nullable(false)->change();
        });
    }
};
