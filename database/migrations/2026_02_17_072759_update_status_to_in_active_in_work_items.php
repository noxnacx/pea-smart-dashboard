<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // 1. ปลดล็อคกฏ (Constraint) เดิมของ PostgreSQL ออกก่อน
        DB::statement('ALTER TABLE work_items DROP CONSTRAINT IF EXISTS work_items_status_check');

        // 2. เปลี่ยนข้อมูลเก่าทั้งหมดที่เคยเป็น pending ให้เป็น in_active
        DB::table('work_items')->where('status', 'pending')->update(['status' => 'in_active']);

        // 3. เปลี่ยน Default ของคอลัมน์
        Schema::table('work_items', function (Blueprint $table) {
            $table->string('status')->default('in_active')->change();
        });

        // 4. (ทางเลือก) สร้างกฏใหม่กลับเข้าไปเพื่อความปลอดภัยของ Database
        DB::statement("ALTER TABLE work_items ADD CONSTRAINT work_items_status_check CHECK (status::text = ANY (ARRAY['in_active'::text, 'in_progress'::text, 'completed'::text, 'delayed'::text, 'cancelled'::text]))");
    }

    public function down()
    {
        // ทำย้อนกลับ (Rollback)
        DB::statement('ALTER TABLE work_items DROP CONSTRAINT IF EXISTS work_items_status_check');

        DB::table('work_items')->where('status', 'in_active')->update(['status' => 'pending']);

        Schema::table('work_items', function (Blueprint $table) {
            $table->string('status')->default('pending')->change();
        });

        DB::statement("ALTER TABLE work_items ADD CONSTRAINT work_items_status_check CHECK (status::text = ANY (ARRAY['pending'::text, 'in_progress'::text, 'completed'::text, 'delayed'::text, 'cancelled'::text]))");
    }
};
