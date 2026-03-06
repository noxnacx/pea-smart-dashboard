<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('strategic_alignments', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique()->comment('เช่น SO1, SO2');
            $table->text('description')->comment('ข้อความยุทธศาสตร์ยาวๆ');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('strategic_alignments');
    }
};
