<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 🚀 ใช้คำสั่ง SQL ดิบของ PostgreSQL เพื่อให้มันเช็คก่อนว่ามี Index หรือยัง (ถ้ามีแล้วจะข้ามให้เลย ไม่ Error)
        DB::statement('CREATE INDEX IF NOT EXISTS work_items_parent_id_index ON work_items (parent_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS work_items_type_index ON work_items (type)');
        DB::statement('CREATE INDEX IF NOT EXISTS work_items_status_index ON work_items (status)');
        DB::statement('CREATE INDEX IF NOT EXISTS work_items_division_id_index ON work_items (division_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS work_items_project_manager_id_index ON work_items (project_manager_id)');

        DB::statement('CREATE INDEX IF NOT EXISTS audit_logs_action_index ON audit_logs (action)');
        DB::statement('CREATE INDEX IF NOT EXISTS audit_logs_model_type_index ON audit_logs (model_type)');
        DB::statement('CREATE INDEX IF NOT EXISTS audit_logs_created_at_index ON audit_logs (created_at)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // ตอนลบก็ใช้ IF EXISTS เพื่อป้องกัน Error เช่นกัน
        DB::statement('DROP INDEX IF EXISTS work_items_parent_id_index');
        DB::statement('DROP INDEX IF EXISTS work_items_type_index');
        DB::statement('DROP INDEX IF EXISTS work_items_status_index');
        DB::statement('DROP INDEX IF EXISTS work_items_division_id_index');
        DB::statement('DROP INDEX IF EXISTS work_items_project_manager_id_index');

        DB::statement('DROP INDEX IF EXISTS audit_logs_action_index');
        DB::statement('DROP INDEX IF EXISTS audit_logs_model_type_index');
        DB::statement('DROP INDEX IF EXISTS audit_logs_created_at_index');
    }
};
