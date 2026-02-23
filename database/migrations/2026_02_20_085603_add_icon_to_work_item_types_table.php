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
    Schema::table('work_item_types', function (Blueprint $table) {
        $table->string('icon', 50)->nullable()->after('key'); // เพิ่มคอลัมน์ icon
    });
}
public function down()
{
    Schema::table('work_item_types', function (Blueprint $table) {
        $table->dropColumn('icon');
    });
}
};
