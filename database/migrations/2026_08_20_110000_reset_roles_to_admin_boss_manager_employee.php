<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('employee_role')) {
            return;
        }

        $now = now();

        $roleDefs = [
            'admin' => ['name' => 'Admin', 'description' => 'Full system access'],
            'boss' => ['name' => 'Boss', 'description' => 'Executive operations lead with full access'],
            'manager' => ['name' => 'Manager', 'description' => 'Full access; responsible for reviewing and closing tasks'],
            'employee' => ['name' => 'Employee', 'description' => 'Can work assigned tasks and view same-project tasks'],
        ];

        $systemRoleIds = [];
        foreach ($roleDefs as $slug => $meta) {
            $existing = DB::table('roles')->where('slug', $slug)->first();
            if ($existing) {
                DB::table('roles')->where('id', $existing->id)->update([
                    'name' => $meta['name'],
                    'description' => $meta['description'],
                    'is_system' => true,
                    'updated_at' => $now,
                ]);
                $systemRoleIds[$slug] = (int) $existing->id;
            } else {
                $systemRoleIds[$slug] = (int) DB::table('roles')->insertGetId([
                    'name' => $meta['name'],
                    'slug' => $slug,
                    'description' => $meta['description'],
                    'is_system' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $assignments = DB::table('employee_role')
            ->join('roles', 'roles.id', '=', 'employee_role.role_id')
            ->select('employee_role.employee_id', 'roles.slug')
            ->get();

        $byEmployee = [];
        foreach ($assignments as $assignment) {
            $target = match (strtolower((string) $assignment->slug)) {
                'admin', 'ceo', 'owner' => 'admin',
                'boss' => 'boss',
                'manager' => 'manager',
                default => 'employee',
            };
            $byEmployee[(int) $assignment->employee_id][$target] = true;
        }

        foreach ($byEmployee as $employeeId => $targets) {
            $slugs = array_keys($targets);
            $privileged = array_values(array_intersect($slugs, ['admin', 'boss', 'manager']));
            $chosen = $privileged !== [] ? [$privileged[0]] : ['employee'];

            DB::table('employee_role')->where('employee_id', $employeeId)->delete();
            foreach ($chosen as $slug) {
                DB::table('employee_role')->insert([
                    'employee_id' => $employeeId,
                    'role_id' => $systemRoleIds[$slug],
                ]);
            }
        }

        $obsoleteIds = DB::table('roles')
            ->whereNotIn('id', array_values($systemRoleIds))
            ->pluck('id');

        if ($obsoleteIds->isNotEmpty()) {
            DB::table('employee_role')->whereIn('role_id', $obsoleteIds)->delete();
            if (Schema::hasTable('role_permission')) {
                DB::table('role_permission')->whereIn('role_id', $obsoleteIds)->delete();
            }
            DB::table('roles')->whereIn('id', $obsoleteIds)->delete();
        }

        if (! Schema::hasTable('permissions') || ! Schema::hasTable('role_permission')) {
            return;
        }

        $allPermissionIds = DB::table('permissions')->pluck('id')->all();
        $employeePermissionIds = DB::table('permissions')
            ->whereIn('slug', ['tasks.view', 'tasks.create', 'tasks.edit', 'projects.view'])
            ->pluck('id')
            ->all();

        foreach (['admin', 'boss', 'manager'] as $slug) {
            DB::table('role_permission')->where('role_id', $systemRoleIds[$slug])->delete();
            foreach ($allPermissionIds as $permissionId) {
                DB::table('role_permission')->insert([
                    'role_id' => $systemRoleIds[$slug],
                    'permission_id' => $permissionId,
                ]);
            }
        }

        DB::table('role_permission')->where('role_id', $systemRoleIds['employee'])->delete();
        foreach ($employeePermissionIds as $permissionId) {
            DB::table('role_permission')->insert([
                'role_id' => $systemRoleIds['employee'],
                'permission_id' => $permissionId,
            ]);
        }
    }

    public function down(): void
    {
        // Irreversible data remap; roles remain as the four system roles.
    }
};
