<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_items', function (Blueprint $table) {
            $table->id();

            // Hierarchy: ระบบแม่ลูก (Recursive)
            $table->foreignId('parent_id')->nullable()->constrained('work_items')->onDelete('cascade');
            $table->integer('order_index')->default(0); // สำหรับจัดลำดับการโชว์ใน Gantt

            // Core Info
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type')->index(); // 'strategy', 'plan', 'project', 'task'

            // Status & Progress
            $table->enum('status', ['pending', 'in_progress', 'completed', 'delayed', 'cancelled'])->default('pending')->index();
            $table->integer('progress')->default(0); // 0-100% (Physical Progress)
            $table->float('weight')->default(1.0); // น้ำหนักความสำคัญ (ใช้ตอนคำนวณงานแม่)

            // Timeline (Gantt Chart)
            $table->date('planned_start_date')->nullable();
            $table->date('planned_end_date')->nullable();
            $table->date('actual_start_date')->nullable();
            $table->date('actual_end_date')->nullable();

            // Financial & EVM (Earned Value Management)
            $table->decimal('budget', 15, 2)->default(0); // Planned Value Base
            $table->decimal('actual_cost', 15, 2)->default(0); // Actual Cost

            // Management
            $table->foreignId('responsible_user_id')->nullable()->constrained('users');
            $table->string('color')->default('#3b82f6'); // สีใน Gantt Chart

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_items');
    }
};
