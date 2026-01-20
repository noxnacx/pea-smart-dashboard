<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // ใครทำ
            $table->string('action'); // CREATE, UPDATE, DELETE, LOGIN
            $table->string('model_type'); // WorkItem, User
            $table->unsignedBigInteger('model_id'); // ID ของข้อมูลนั้น
            $table->json('changes')->nullable(); // เก็บค่าเก่า vs ค่าใหม่ (JSON)
            $table->string('ip_address')->nullable(); // IP เครื่องที่ทำ
            $table->timestamps(); // เวลาที่ทำ
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
