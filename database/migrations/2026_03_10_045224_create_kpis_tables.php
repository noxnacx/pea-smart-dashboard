<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. ตาราง Master เก็บรายการตัวชี้วัด (KPIs)
        Schema::create('kpis', function (Blueprint $table) {
            $table->id();
            $table->text('name')->comment('ชื่อตัวชี้วัด / ค่าเป้าหมาย');
            $table->text('description')->nullable()->comment('คำอธิบายเพิ่มเติม (ถ้ามี)');
            $table->timestamps();
        });

        // 2. ตาราง Pivot สำหรับเชื่อม KPI กับ ประเภทงาน (Many-to-Many)
        // เพื่อบอกว่า KPI ข้อนี้ เอาไปแสดงให้เลือกในงานระดับไหนได้บ้าง (เช่น โชว์เฉพาะใน project)
        Schema::create('kpi_work_item_type', function (Blueprint $table) {
            $table->id();

            // เชื่อมกับตาราง kpis แบบมี Cascading (ถ้าลบ KPI ก็ลบ Tag ตามไปด้วย)
            $table->foreignId('kpi_id')->constrained('kpis')->onDelete('cascade');

            // เก็บ Key ของประเภทงาน เช่น 'strategy', 'plan', 'project', 'task'
            $table->string('work_item_type_key')->comment('Key ของประเภทงาน เช่น plan, project');

            $table->timestamps();

            // ทำ Index เพื่อให้ค้นหาเร็วขึ้น
            $table->index('work_item_type_key');

            // ป้องกันการผูกข้อมูลซ้ำซ้อน (KPI 1 ตัว ห้ามมี Tag ประเภทงานเดียวกันซ้ำ)
            $table->unique(['kpi_id', 'work_item_type_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_work_item_type');
        Schema::dropIfExists('kpis');
    }
};
