<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_item_id')->constrained()->cascadeOnDelete(); // ผูกกับงานไหน
            $table->foreignId('user_id')->constrained(); // ใครเป็นคนแจ้ง
            $table->string('title'); // หัวข้อปัญหา
            $table->enum('type', ['issue', 'risk']); // ประเภท: ปัญหา หรือ ความเสี่ยง
            $table->enum('severity', ['low', 'medium', 'high', 'critical']); // ความรุนแรง
            $table->enum('status', ['open', 'in_progress', 'resolved']); // สถานะ
            $table->text('description')->nullable(); // รายละเอียด
            $table->text('solution')->nullable(); // แนวทางแก้ไข
            $table->date('due_date')->nullable(); // กำหนดเสร็จ
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('issues');
    }
};
