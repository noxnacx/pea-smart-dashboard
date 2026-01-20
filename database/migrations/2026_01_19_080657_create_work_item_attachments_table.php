<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_item_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_item_id')->constrained('work_items')->onDelete('cascade');
            $table->string('file_name'); // ชื่อไฟล์ที่แสดง
            $table->string('file_path'); // Path ที่เก็บไฟล์จริง
            $table->string('file_type')->nullable(); // pdf, jpg, etc.
            $table->integer('file_size')->nullable(); // ขนาดไฟล์ (bytes)
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_item_attachments');
    }
};
