<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_items', function (Blueprint $table) {
            $table->text('rationale')->nullable()->comment('หลักการและเหตุผล');
            $table->text('objective')->nullable()->comment('วัตถุประสงค์ของแผนงาน');
            $table->text('alignment')->nullable()->comment('ความสอดคล้องต่อยุทธศาสตร์');
            $table->text('scope_output')->nullable()->comment('ขอบเขตงานและผลลัพธ์ที่คาดหวัง');
        });
    }

    public function down(): void
    {
        Schema::table('work_items', function (Blueprint $table) {
            $table->dropColumn(['rationale', 'objective', 'alignment', 'scope_output']);
        });
    }
};
