<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('progress_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_item_id')->constrained()->cascadeOnDelete();
            $table->integer('progress')->default(0)->comment('เปอร์เซ็นต์ความคืบหน้า ณ เวลานั้น');
            // ระบบจะใช้ created_at เพื่ออ้างอิงเวลาที่เก็บ Snapshot
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('progress_histories');
    }
};
