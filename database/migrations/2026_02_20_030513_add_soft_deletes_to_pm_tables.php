<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('work_items', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('issues', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('attachments', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::table('work_items', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('issues', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('attachments', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
