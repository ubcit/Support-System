<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Employees\Models\Employee;
use Modules\MultiTenancy\Models\Workspace;
use Modules\Security\Models\Role;

class UserAndEmployeeSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::where('slug', Role::ADMIN)->first();
        $workspace = Workspace::first();

        // Release seed: exactly one admin account
        $this->seedPerson(
            email: env('ADMIN_EMAIL', 'admin@thespace.app'),
            name: env('ADMIN_NAME', 'Admin'),
            jobTitle: 'Admin',
            phone: env('ADMIN_PHONE', '+9647700000001'),
            workspaceId: $workspace?->id,
            role: $adminRole,
            password: env('ADMIN_PASSWORD', 'password123'),
        );

        // Demo roster only for local/testing — never in production release seeds
        if (app()->environment(['local', 'testing'])) {
            $this->seedDemoUsers($workspace?->id);
        }
    }

    protected function seedDemoUsers(?int $workspaceId): void
    {
        $bossRole = Role::where('slug', Role::BOSS)->first();
        $managerRole = Role::where('slug', Role::MANAGER)->first();
        $employeeRole = Role::where('slug', Role::EMPLOYEE)->first();

        $this->seedPerson(
            email: 'boss@thespace.app',
            name: 'Yousif',
            jobTitle: 'Executive Boss',
            phone: '+9647700000000',
            workspaceId: $workspaceId,
            role: $bossRole,
        );

        $this->seedPerson(
            email: 'manager@thespace.app',
            name: 'Manager',
            jobTitle: 'Manager',
            phone: '+9647700000002',
            workspaceId: $workspaceId,
            role: $managerRole,
        );

        $this->seedPerson(
            email: 'ahmed@thespace.app',
            name: 'Ahmed',
            jobTitle: 'Backend Engineer',
            phone: '+9647701111111',
            workspaceId: $workspaceId,
            role: $employeeRole,
        );

        $this->seedPerson(
            email: 'sara@thespace.app',
            name: 'Sara',
            jobTitle: 'QA Lead',
            phone: '+9647702222222',
            workspaceId: $workspaceId,
            role: $employeeRole,
        );

        $this->seedPerson(
            email: 'ali@thespace.app',
            name: 'Ali',
            jobTitle: 'Support Specialist',
            phone: '+9647703333333',
            workspaceId: $workspaceId,
            role: $employeeRole,
        );
    }

    protected function seedPerson(
        string $email,
        string $name,
        string $jobTitle,
        string $phone,
        ?int $workspaceId,
        ?Role $role,
        string $password = 'password123',
    ): void {
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'phone' => $phone,
                'password' => Hash::make($password),
            ]
        );

        $employee = Employee::firstOrCreate(
            ['email' => $email],
            [
                'uuid' => (string) Str::uuid(),
                'user_id' => $user->id,
                'workspace_id' => $workspaceId,
                'name' => $name,
                'role' => $jobTitle,
                'phone' => $phone,
            ]
        );

        $employee->update([
            'user_id' => $user->id,
            'workspace_id' => $workspaceId,
            'name' => $name,
            'role' => $jobTitle,
            'phone' => $phone,
        ]);

        if ($role) {
            $employee->roles()->sync([$role->id]);
        }
    }
}
