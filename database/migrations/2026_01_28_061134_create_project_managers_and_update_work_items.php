<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. สร้างตารางเก็บรายชื่อผู้ดูแล (Profile ลอยๆ ไม่ใช่ User Login)
        Schema::create('project_managers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // ชื่อห้ามซ้ำ
            $table->timestamps();
        });

        // 2. ปรับปรุงตาราง work_items
        Schema::table('work_items', function (Blueprint $table) {
            // ลบการผูกกับ User เดิม (ถ้ามี)
            if (Schema::hasColumn('work_items', 'responsible_user_id')) {
                $table->dropForeign(['responsible_user_id']);
                $table->dropColumn('responsible_user_id');
            }

            // เพิ่มการผูกกับ Project Manager แบบใหม่
            $table->foreignId('project_manager_id')->nullable()->constrained('project_managers')->nullOnDelete();

            // เพิ่ม division_id เพื่อให้เลือกแค่กองได้ (โดยไม่ต้องผ่าน department)
            $table->foreignId('division_id')->nullable()->constrained('divisions')->nullOnDelete();

            // department_id ของเดิมให้คงไว้ แต่ต้องแน่ใจว่าเป็น nullable (ซึ่ง Migration รอบก่อนเราทำ nullable ไว้แล้ว)
        });
    }

    public function down(): void
    {
        Schema::table('work_items', function (Blueprint $table) {
            $table->dropForeign(['project_manager_id']);
            $table->dropColumn(['project_manager_id', 'division_id']);
        });
        Schema::dropIfExists('project_managers');
    }
};
