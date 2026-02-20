<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_item_id')->constrained('work_items')->cascadeOnDelete();
            $table->string('title'); // ชื่อเป้าหมาย เช่น "ส่งมอบเฟส 1"
            $table->date('due_date')->nullable(); // กำหนดส่ง
            $table->string('status')->default('pending'); // pending, completed
            $table->text('remarks')->nullable(); // หมายเหตุ
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('milestones');
    }
};
