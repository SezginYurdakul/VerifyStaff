<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * Seed departments and assign workers to them.
     */
    public function run(): void
    {
        // Create 3 departments with different shift schedules
        $departments = [
            [
                'name' => 'Warehouse',
                'code' => 'WH',
                'shift_start' => '07:00:00',
                'shift_end' => '16:00:00',
                'late_threshold_minutes' => 10,
                'early_departure_threshold_minutes' => 10,
                'regular_work_minutes' => 480, // 8 hours
                'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'],
                'description' => 'Warehouse operations - early shift',
                'is_active' => true,
            ],
            [
                'name' => 'Office',
                'code' => 'OFF',
                'shift_start' => '09:00:00',
                'shift_end' => '18:00:00',
                'late_threshold_minutes' => 15,
                'early_departure_threshold_minutes' => 15,
                'regular_work_minutes' => 480, // 8 hours
                'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
                'description' => 'Office staff - standard hours',
                'is_active' => true,
            ],
            [
                'name' => 'Security',
                'code' => 'SEC',
                'shift_start' => '22:00:00',
                'shift_end' => '06:00:00',
                'late_threshold_minutes' => 5,
                'early_departure_threshold_minutes' => 5,
                'regular_work_minutes' => 480, // 8 hours
                'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
                'description' => 'Security staff - night shift',
                'is_active' => true,
            ],
        ];

        $createdDepartments = [];
        foreach ($departments as $deptData) {
            $createdDepartments[] = Department::create($deptData);
        }

        // Assign workers to departments in equal distribution
        $workers = User::where('role', 'worker')->get();
        $departmentCount = count($createdDepartments);

        foreach ($workers as $index => $worker) {
            $departmentIndex = $index % $departmentCount;
            $worker->update([
                'department_id' => $createdDepartments[$departmentIndex]->id,
            ]);
        }

        $this->command->info("Created {$departmentCount} departments");
        $this->command->info("Assigned {$workers->count()} workers to departments");
    }
}
