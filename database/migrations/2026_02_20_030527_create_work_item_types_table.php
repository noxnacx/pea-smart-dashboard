<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // 1. สร้างตารางประเภทงาน
        Schema::create('work_item_types', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // ชื่อ เช่น แผนแม่บท, ยุทธศาสตร์, โครงการ
            $table->string('key')->unique(); // รหัสอ้างอิง เช่น strategy, project
            $table->integer('level_order'); // ลำดับชั้น (1 คือใหญ่สุด)
            $table->string('color_code')->default('#7A2F8F'); // สีป้าย
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. สร้างข้อมูลพื้นฐาน (Default) ให้ตรงกับระบบเดิมของคุณ
        DB::table('work_item_types')->insert([
            ['name' => 'ยุทธศาสตร์ (Strategy)', 'key' => 'strategy', 'level_order' => 1, 'color_code' => '#4A148C', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'แผนงาน (Plan)', 'key' => 'plan', 'level_order' => 2, 'color_code' => '#F59E0B', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'โครงการ (Project)', 'key' => 'project', 'level_order' => 3, 'color_code' => '#3B82F6', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'งานย่อย (Task)', 'key' => 'task', 'level_order' => 4, 'color_code' => '#10B981', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // 3. เพิ่มคอลัมน์ work_item_type_id ลงในตาราง work_items
        Schema::table('work_items', function (Blueprint $table) {
            $table->foreignId('work_item_type_id')->nullable()->constrained('work_item_types')->nullOnDelete();
        });

        // 4. อัปเดตข้อมูลเก่าให้มาใช้ ID ของประเภทงานใหม่ (Data Migration)
        $types = DB::table('work_item_types')->get();
        foreach ($types as $type) {
            DB::table('work_items')
                ->where('type', $type->key) // เอาคำว่า 'project', 'task' เดิม
                ->update(['work_item_type_id' => $type->id]); // มาผูกกับ ID ใหม่
        }
    }

    public function down()
    {
        Schema::table('work_items', function (Blueprint $table) {
            $table->dropForeign(['work_item_type_id']);
            $table->dropColumn('work_item_type_id');
        });

        Schema::dropIfExists('work_item_types');
    }
};
