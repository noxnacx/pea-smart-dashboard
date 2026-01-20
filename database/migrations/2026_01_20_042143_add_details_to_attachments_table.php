<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // เปลี่ยนจาก Schema::table เป็น Schema::create เพื่อสร้างตารางใหม่
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();

            // Foreign Keys
            $table->foreignId('work_item_id')->constrained()->cascadeOnDelete(); // ผูกกับโครงการ
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // ผูกกับคนอัปโหลด

            // ข้อมูลไฟล์เดิม
            $table->string('file_name');
            $table->string('file_path');

            // ข้อมูลใหม่ (ที่เราอยากเพิ่ม)
            $table->string('file_type')->nullable(); // เช่น image/png, application/pdf
            $table->integer('file_size')->default(0); // ขนาดไฟล์ (bytes)
            $table->string('category')->default('general'); // หมวดหมู่เอกสาร

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('attachments');
    }
};
