<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\WorkItem;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create Users
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@pea.co.th',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $pm = User::create([
            'name' => 'Project Manager',
            'email' => 'pm@pea.co.th',
            'password' => bcrypt('password'),
            'role' => 'pm',
        ]);

        // 2. Create Hierarchy Data
        // Level 1: ยุทธศาสตร์ (Strategy)
        $strategies = [
            ['name' => 'ยุทธศาสตร์ที่ 1: Grid Modernization', 'budget' => 50000000],
            ['name' => 'ยุทธศาสตร์ที่ 2: Green Energy', 'budget' => 30000000],
        ];

        foreach ($strategies as $index => $stData) {
            $strategy = WorkItem::create([
                'name' => $stData['name'],
                'type' => 'strategy',
                'description' => 'รายละเอียดของยุทธศาสตร์...',
                'budget' => $stData['budget'],
                'planned_start_date' => Carbon::now(),
                'planned_end_date' => Carbon::now()->addYears(1),
                'order_index' => $index,
                'responsible_user_id' => $admin->id,
            ]);

            // Level 2: แผนงาน (Plan)
            for ($i = 1; $i <= 2; $i++) {
                $plan = WorkItem::create([
                    'parent_id' => $strategy->id,
                    'name' => "แผนงานพัฒนาที่ $i ภายใต้ " . $strategy->name,
                    'type' => 'plan',
                    'budget' => $strategy->budget / 2,
                    'planned_start_date' => Carbon::now()->addMonths($i),
                    'planned_end_date' => Carbon::now()->addMonths($i + 6),
                    'order_index' => $i,
                    'responsible_user_id' => $pm->id,
                ]);

                // Level 3: โครงการ (Project)
                for ($j = 1; $j <= 3; $j++) {
                    $project = WorkItem::create([
                        'parent_id' => $plan->id,
                        'name' => "โครงการ Smart Grid ระยะที่ $j",
                        'type' => 'project',
                        'status' => $j == 1 ? 'completed' : ($j == 2 ? 'in_progress' : 'pending'),
                        'progress' => $j == 1 ? 100 : ($j == 2 ? 45 : 0),
                        'budget' => 1000000,
                        'planned_start_date' => Carbon::now()->addMonths($j),
                        'planned_end_date' => Carbon::now()->addMonths($j + 2),
                        'actual_start_date' => $j <= 2 ? Carbon::now()->addMonths($j) : null,
                        'order_index' => $j,
                        'responsible_user_id' => $pm->id,
                        'color' => $j == 2 ? '#f59e0b' : '#3b82f6', // สีส้มถ้ากำลังทำ, สีฟ้าทั่วไป
                    ]);

                    // Level 4: Task (งานย่อย)
                    for ($k = 1; $k <= 4; $k++) {
                        WorkItem::create([
                            'parent_id' => $project->id,
                            'name' => "Task ย่อยที่ $k: ติดตั้งอุปกรณ์",
                            'type' => 'task',
                            'status' => 'pending',
                            'progress' => 0,
                            'weight' => 1,
                            'planned_start_date' => $project->planned_start_date->copy()->addDays($k * 5),
                            'planned_end_date' => $project->planned_start_date->copy()->addDays(($k * 5) + 4),
                            'order_index' => $k,
                        ]);
                    }
                }
            }
        }
    }
}
