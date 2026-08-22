<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Security\Models\Permission;
use Modules\Security\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['name' => 'View Tasks', 'slug' => 'tasks.view', 'group' => 'tasks'],
            ['name' => 'Create Tasks', 'slug' => 'tasks.create', 'group' => 'tasks'],
            ['name' => 'Edit Tasks', 'slug' => 'tasks.edit', 'group' => 'tasks'],
            ['name' => 'Delete Tasks', 'slug' => 'tasks.delete', 'group' => 'tasks'],

            ['name' => 'View Conversations', 'slug' => 'conversations.view', 'group' => 'conversations'],
            ['name' => 'Reply WhatsApp', 'slug' => 'conversations.reply', 'group' => 'conversations'],
            ['name' => 'Assign Conversation', 'slug' => 'conversations.assign', 'group' => 'conversations'],

            ['name' => 'Manage Projects', 'slug' => 'projects.manage', 'group' => 'projects'],
            ['name' => 'View Projects', 'slug' => 'projects.view', 'group' => 'projects'],

            ['name' => 'View Issues', 'slug' => 'issues.view', 'group' => 'issues'],
            ['name' => 'Manage Issues', 'slug' => 'issues.manage', 'group' => 'issues'],

            ['name' => 'Manage Employees', 'slug' => 'employees.manage', 'group' => 'employees'],
            ['name' => 'Manage Customers', 'slug' => 'customers.manage', 'group' => 'customers'],

            ['name' => 'Manage AI Settings', 'slug' => 'ai.manage', 'group' => 'ai'],
            ['name' => 'Manage Rules', 'slug' => 'rules.manage', 'group' => 'rules'],

            ['name' => 'View Reports', 'slug' => 'reports.view', 'group' => 'reports'],
            ['name' => 'Manage Settings', 'slug' => 'settings.manage', 'group' => 'settings'],
            ['name' => 'Manage Signup Requests', 'slug' => 'signup_requests.manage', 'group' => 'signup_requests'],
        ];

        $permissionModels = [];
        foreach ($permissions as $permission) {
            $permissionModels[$permission['slug']] = Permission::updateOrCreate(
                ['slug' => $permission['slug']],
                $permission
            );
        }

        $allPermissionIds = array_map(fn (Permission $permission) => $permission->id, $permissionModels);
        $employeePermissionIds = Permission::whereIn('slug', [
            'tasks.view',
            'tasks.create',
            'tasks.edit',
            'projects.view',
        ])->pluck('id')->all();

        $roles = [
            [
                'name' => 'Admin',
                'slug' => Role::ADMIN,
                'description' => 'Full system access',
                'permission_ids' => $allPermissionIds,
            ],
            [
                'name' => 'Boss',
                'slug' => Role::BOSS,
                'description' => 'Executive operations lead with full access',
                'permission_ids' => $allPermissionIds,
            ],
            [
                'name' => 'Manager',
                'slug' => Role::MANAGER,
                'description' => 'Full access; responsible for reviewing and closing tasks',
                'permission_ids' => $allPermissionIds,
            ],
            [
                'name' => 'Employee',
                'slug' => Role::EMPLOYEE,
                'description' => 'Can work assigned tasks and view same-project tasks',
                'permission_ids' => $employeePermissionIds,
            ],
        ];

        $systemRoleIds = [];
        foreach ($roles as $roleData) {
            $role = Role::updateOrCreate(
                ['slug' => $roleData['slug']],
                [
                    'name' => $roleData['name'],
                    'description' => $roleData['description'],
                    'is_system' => true,
                ]
            );
            $role->permissions()->sync($roleData['permission_ids']);
            $systemRoleIds[$roleData['slug']] = $role->id;
        }

        $this->remapEmployeeRoles($systemRoleIds);
        $this->deleteObsoleteRoles(array_values($systemRoleIds));
    }

    /**
     * @param  array<string, int>  $systemRoleIds
     */
    protected function remapEmployeeRoles(array $systemRoleIds): void
    {
        $assignments = DB::table('employee_role')
            ->join('roles', 'roles.id', '=', 'employee_role.role_id')
            ->select('employee_role.employee_id', 'roles.slug')
            ->get();

        $byEmployee = [];
        foreach ($assignments as $assignment) {
            $targetSlug = Role::remapSlug((string) $assignment->slug);
            $byEmployee[(int) $assignment->employee_id][$targetSlug] = true;
        }

        foreach ($byEmployee as $employeeId => $targetSlugs) {
            $roleIds = [];
            foreach (array_keys($targetSlugs) as $slug) {
                if (isset($systemRoleIds[$slug])) {
                    $roleIds[] = $systemRoleIds[$slug];
                }
            }

            // Prefer a single privileged role when multiple map onto the same employee.
            if (count($roleIds) > 1) {
                $privilegedIds = array_values(array_filter(
                    $roleIds,
                    fn (int $id) => in_array($id, [
                        $systemRoleIds[Role::ADMIN] ?? null,
                        $systemRoleIds[Role::BOSS] ?? null,
                        $systemRoleIds[Role::MANAGER] ?? null,
                    ], true)
                ));
                $roleIds = $privilegedIds !== [] ? [reset($privilegedIds)] : [$systemRoleIds[Role::EMPLOYEE]];
            }

            DB::table('employee_role')->where('employee_id', $employeeId)->delete();
            foreach ($roleIds as $roleId) {
                DB::table('employee_role')->insert([
                    'employee_id' => $employeeId,
                    'role_id' => $roleId,
                ]);
            }
        }
    }

    /**
     * @param  list<int>  $keepRoleIds
     */
    protected function deleteObsoleteRoles(array $keepRoleIds): void
    {
        $obsoleteIds = Role::query()
            ->whereNotIn('id', $keepRoleIds)
            ->pluck('id');

        if ($obsoleteIds->isEmpty()) {
            return;
        }

        DB::table('employee_role')->whereIn('role_id', $obsoleteIds)->delete();
        DB::table('role_permission')->whereIn('role_id', $obsoleteIds)->delete();
        Role::query()->whereIn('id', $obsoleteIds)->delete();
    }
}
