<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_item_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_item_id')->constrained('work_items')->onDelete('cascade');

            $table->date('log_date'); // วันที่บันทึก
            $table->integer('progress'); // ความคืบหน้า ณ ตอนนั้น
            $table->decimal('actual_cost', 15, 2)->default(0); // เงินที่ใช้ไป ณ ตอนนั้น
            $table->text('note')->nullable(); // บันทึกเพิ่มเติม

            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_item_logs');
    }
};
