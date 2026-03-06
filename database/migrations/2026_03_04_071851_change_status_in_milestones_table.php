<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('milestones', function (Blueprint $table) {
            // ปลดล็อกให้เป็น String จะได้ใส่สถานะอะไรก็ได้
            $table->string('status', 50)->default('pending')->change();
        });
    }
};
