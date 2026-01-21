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
        Schema::table('issues', function (Blueprint $table) {
            // เปลี่ยนชื่อจาก due_date เป็น end_date เพื่อความชัดเจน
            $table->renameColumn('due_date', 'end_date');

            // เพิ่ม start_date มาก่อนหน้า end_date
            $table->date('start_date')->nullable()->after('solution');
        });
    }

    public function down()
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->dropColumn('start_date');
            $table->renameColumn('end_date', 'due_date');
        });
    }
};
