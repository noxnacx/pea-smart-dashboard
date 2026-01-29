<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('work_items', function (Blueprint $table) {
            // 1. เช็คว่ามีคอลัมน์ weight หรือยัง ถ้าไม่มีให้เพิ่ม
            if (!Schema::hasColumn('work_items', 'weight')) {
                $table->decimal('weight', 8, 2)->nullable()->default(0)->after('progress')->comment('น้ำหนักงานสำหรับคำนวณ Progress');
            }

            // 2. เช็คว่ามีคอลัมน์ is_active หรือยัง ถ้าไม่มีให้เพิ่ม
            if (!Schema::hasColumn('work_items', 'is_active')) {
                // ถ้ามี weight แล้ว ให้ is_active ต่อท้าย weight, ถ้าไม่มีให้ต่อท้าย progress
                $afterColumn = Schema::hasColumn('work_items', 'weight') ? 'weight' : 'progress';
                $table->boolean('is_active')->default(true)->after($afterColumn)->comment('สถานะการใช้งาน (true=เปิด, false=ปิด)');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_items', function (Blueprint $table) {
            if (Schema::hasColumn('work_items', 'is_active')) {
                $table->dropColumn('is_active');
            }
            if (Schema::hasColumn('work_items', 'weight')) {
                $table->dropColumn('weight');
            }
        });
    }
};
