<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. เพิ่ม division_id ให้ Users (เพื่อให้ User มีสังกัดครบทั้ง กอง และ แผนก)
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'division_id')) {
                $table->unsignedBigInteger('division_id')->nullable()->after('department_id');
                $table->foreign('division_id')->references('id')->on('divisions')->nullOnDelete();
            }
        });

        // 2. ปรับปรุงตาราง Work Items (เปลี่ยน PM ไปผูกกับ Users)
        Schema::table('work_items', function (Blueprint $table) {
            // 2.1 ลบ Foreign Key เดิมที่ผูกกับตาราง project_managers
            // (ต้องเช็คชื่อ constraint ใน DB คุณ ปกติจะเป็น work_items_project_manager_id_foreign)
            $table->dropForeign(['project_manager_id']);
        });

        // 2.2 ล้างข้อมูล ID ของ PM เก่าทิ้งก่อน (เพราะ ID 1 ของตารางเก่า ไม่ใช่ ID 1 ของ User)
        // เพื่อป้องกัน Data Integrity Violation
        DB::table('work_items')->update(['project_manager_id' => null]);

        Schema::table('work_items', function (Blueprint $table) {
            // 2.3 สร้าง Foreign Key ใหม่ ชี้ไปหาตาราง users
            $table->foreign('project_manager_id')
                  ->references('id')
                  ->on('users')
                  ->nullOnDelete(); // ถ้า User ถูกลบ ให้งานนี้ไม่มี PM
        });

        // 3. ลบตาราง project_managers ทิ้ง (เพราะเราไม่ใช้แล้ว)
        Schema::dropIfExists('project_managers');
    }

    public function down(): void
    {
        // กู้คืน (เผื่อ Rollback)

        // 1. สร้างตาราง project_managers กลับมา
        Schema::create('project_managers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        // 2. แก้ work_items กลับไปชี้ project_managers
        Schema::table('work_items', function (Blueprint $table) {
            $table->dropForeign(['project_manager_id']);

            // ล้างข้อมูลอีกรอบเพราะ ID user ไม่ตรงกับ pm table
            DB::table('work_items')->update(['project_manager_id' => null]);

            $table->foreign('project_manager_id')
                  ->references('id')
                  ->on('project_managers')
                  ->nullOnDelete();
        });

        // 3. ลบคอลัมน์ division_id ออกจาก users
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['division_id']);
            $table->dropColumn('division_id');
        });
    }
};
