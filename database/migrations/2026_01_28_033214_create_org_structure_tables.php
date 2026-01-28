<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. สร้างตาราง "กอง" (Divisions)
        Schema::create('divisions', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // ชื่อกอง
            $table->string('code')->nullable(); // รหัสกอง (เผื่อมี)
            $table->timestamps();
        });

        // 2. สร้างตาราง "แผนก" (Departments)
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // ชื่อแผนก
            $table->string('code')->nullable(); // รหัสแผนก
            $table->foreignId('division_id')->constrained('divisions')->onDelete('cascade'); // อยู่ภายใต้กองไหน
            $table->timestamps();
        });

        // 3. เพิ่มคอลัมน์ในตาราง "users"
        Schema::table('users', function (Blueprint $table) {
            // User สังกัดแผนกไหน (Nullable ไว้ก่อนเผื่อ Admin ส่วนกลาง)
            $table->foreignId('department_id')->nullable()->constrained('departments')->onDelete('set null');

            // ระบุว่าเป็น PM หรือไม่ (Default false)
            $table->boolean('is_pm')->default(false);

            // เก็บเบอร์โทร/Position เพิ่มเติม (เผื่อโชว์หน้า Profile)
            $table->string('position')->nullable();
            $table->string('phone')->nullable();
        });

        // 4. เพิ่มคอลัมน์ในตาราง "work_items" (Projects/Plans)
        Schema::table('work_items', function (Blueprint $table) {
            // เจ้าภาพระดับหน่วยงาน (แผนก)
            $table->foreignId('department_id')->nullable()->constrained('departments')->onDelete('set null');

            // เจ้าภาพระดับบุคคล (PM ผู้ดูแล) -> เปลี่ยนชื่อให้ชัดเจน หรือใช้ responsible_user_id เดิมถ้ามีอยู่แล้ว
            // แต่เพื่อความชัวร์ ผมจะเพิ่ม column นี้ (ถ้ามีอยู่แล้วให้ลบบรรทัดนี้ออกนะครับ)
            if (!Schema::hasColumn('work_items', 'responsible_user_id')) {
                $table->foreignId('responsible_user_id')->nullable()->constrained('users')->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        // ย้อนกลับ: ลบคอลัมน์ก่อน แล้วค่อยลบตาราง
        Schema::table('work_items', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropColumn(['department_id']);
            // $table->dropColumn(['responsible_user_id']); // ลบเฉพาะถ้าเราสร้างเพิ่ม
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropColumn(['department_id', 'is_pm', 'position', 'phone']);
        });

        Schema::dropIfExists('departments');
        Schema::dropIfExists('divisions');
    }
};
