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
        // ✅ เช็คก่อนว่ามีคอลัมน์นี้ไหม ถ้ายังไม่มีค่อยสร้าง
        if (!Schema::hasColumn('work_items', 'description')) {
            Schema::table('work_items', function (Blueprint $table) {
                $table->text('description')->nullable()->after('name');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_items', function (Blueprint $table) {
            // ✅ เวลาลบก็เช็คก่อนว่ามีไหม
            if (Schema::hasColumn('work_items', 'description')) {
                $table->dropColumn('description');
            }
        });
    }
};
