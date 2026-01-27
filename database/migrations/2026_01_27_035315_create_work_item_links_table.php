<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('work_item_links', function (Blueprint $table) {
            $table->id();

            // 'source' คือ ID ของงานต้นทาง (Predecessor)
            $table->foreignId('source')
                  ->constrained('work_items')
                  ->onDelete('cascade'); // ถ้างานต้นทางถูกลบ เส้นโยงหายด้วย

            // 'target' คือ ID ของงานปลายทาง (Successor)
            $table->foreignId('target')
                  ->constrained('work_items')
                  ->onDelete('cascade'); // ถ้างานปลายทางถูกลบ เส้นโยงหายด้วย

            // 'type' คือประเภทความสัมพันธ์ (0='Finish-to-Start', 1='Start-to-Start', etc.)
            $table->string('type')->default('0');

            // Lag (ระยะเวลาหน่วง) เผื่ออนาคต
            $table->integer('lag')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_item_links');
    }
};
