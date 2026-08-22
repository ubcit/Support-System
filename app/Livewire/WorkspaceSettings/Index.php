<?php

namespace App\Livewire\WorkspaceSettings;

use App\Livewire\Concerns\AuthorizesActions;
use Livewire\Component;
use Modules\MultiTenancy\Models\Workspace;
use Modules\Security\Models\Permission;
use Modules\Security\Models\Role;

class Index extends Component
{
    use AuthorizesActions;

    public string $workspaceName = 'The Space Workspace';

    public string $whatsappPhone = '+9647700000000';

    public string $aiProvider = 'gemini';

    public int $customerCooldownMinutes = 5;

    public int $bossCooldownMinutes = 1;

    public bool $autoCreateTasks = true;

    public bool $autoReplyOnCompletion = true;

    public bool $autoReplyOnProcessing = true;

    public bool $staffNotifyOnSession = true;

    public float $autoCreateMinConfidence = 0.70;

    public $defaultDailyAiCostLimit = '';

    public array $rolePermissions = [];

    public function mount(): void
    {
        $workspace = Workspace::first();
        if ($workspace) {
            $this->workspaceName = $workspace->name;
            $this->aiProvider = $workspace->settings['ai_provider'] ?? 'gemini';
            $this->whatsappPhone = $workspace->settings['whatsapp_phone'] ?? '+9647700000000';
            $this->customerCooldownMinutes = (int) ($workspace->settings['customer_cooldown_minutes'] ?? 5);
            $this->bossCooldownMinutes = (int) ($workspace->settings['boss_cooldown_minutes'] ?? 1);
            $this->autoCreateTasks = (bool) ($workspace->settings['auto_create_tasks'] ?? true);
            $this->autoReplyOnCompletion = (bool) ($workspace->settings['auto_reply_on_completion'] ?? true);
            $this->autoReplyOnProcessing = (bool) ($workspace->settings['auto_reply_on_processing'] ?? true);
            $this->staffNotifyOnSession = (bool) ($workspace->settings['staff_notify_on_session'] ?? true);
            $this->autoCreateMinConfidence = (float) ($workspace->settings['auto_create_min_confidence'] ?? 0.70);
            $defaultLimit = $workspace->settings['default_daily_ai_cost_limit'] ?? null;
            $this->defaultDailyAiCostLimit = $defaultLimit === null || $defaultLimit === '' ? '' : (string) $defaultLimit;
        }

        if (Role::count() === 0) {
            Role::create(['name' => 'Admin', 'slug' => Role::ADMIN, 'is_system' => true]);
            Role::create(['name' => 'Boss', 'slug' => Role::BOSS, 'is_system' => true]);
            Role::create(['name' => 'Manager', 'slug' => Role::MANAGER, 'is_system' => true]);
            Role::create(['name' => 'Employee', 'slug' => Role::EMPLOYEE, 'is_system' => true]);
        }

        $permissionsList = $this->getPermissionsList();
        foreach ($permissionsList as $slug => $label) {
            Permission::firstOrCreate(['slug' => $slug], ['name' => $label]);
        }

        $roles = Role::with('permissions')->get();
        $allPermissionSlugs = array_keys($permissionsList);

        foreach ($roles as $role) {
            $assignedSlugs = $role->permissions->pluck('slug')->toArray();
            foreach ($allPermissionSlugs as $slug) {
                $this->rolePermissions[$role->id][$slug] = in_array($slug, $assignedSlugs);
            }
        }
    }

    public function getPermissionsList(): array
    {
        return [
            'tasks.view' => 'View Tasks',
            'tasks.create' => 'Create Tasks',
            'tasks.edit' => 'Edit Tasks',
            'tasks.delete' => 'Delete Tasks',
            'conversations.view' => 'View Conversations',
            'conversations.reply' => 'Reply WhatsApp',
            'projects.manage' => 'Manage Projects',
            'employees.manage' => 'Manage Employees',
            'ai.manage' => 'Manage AI Settings',
            'rules.manage' => 'Manage Rules',
            'settings.manage' => 'Manage Settings',
            'signup_requests.manage' => 'Manage Signup Requests',
        ];
    }

    public function toggleAllForRole($roleId)
    {
        $permissions = $this->getPermissionsList();

        $allChecked = true;
        foreach ($permissions as $slug => $label) {
            if (empty($this->rolePermissions[$roleId][$slug])) {
                $allChecked = false;
                break;
            }
        }

        $targetState = ! $allChecked;

        foreach ($permissions as $slug => $label) {
            $this->rolePermissions[$roleId][$slug] = $targetState;
        }
    }

    public function saveSettings()
    {
        $this->authorizePermission('settings.manage');
        $this->validate([
            'workspaceName' => 'required|string',
            'whatsappPhone' => 'required|string',
            'aiProvider' => 'required|string',
            'customerCooldownMinutes' => 'required|integer|min:0',
            'bossCooldownMinutes' => 'required|integer|min:0',
            'autoCreateTasks' => 'boolean',
            'autoReplyOnCompletion' => 'boolean',
            'autoReplyOnProcessing' => 'boolean',
            'staffNotifyOnSession' => 'boolean',
            'autoCreateMinConfidence' => 'required|numeric|min:0|max:1',
            'defaultDailyAiCostLimit' => 'nullable|numeric|min:0',
        ]);

        $workspace = Workspace::first();
        if ($workspace) {
            $settings = $workspace->settings ?? [];
            $settings['ai_provider'] = $this->aiProvider;
            $settings['whatsapp_phone'] = $this->whatsappPhone;
            $settings['customer_cooldown_minutes'] = $this->customerCooldownMinutes;
            $settings['boss_cooldown_minutes'] = $this->bossCooldownMinutes;
            $settings['auto_create_tasks'] = $this->autoCreateTasks;
            $settings['auto_reply_on_completion'] = $this->autoReplyOnCompletion;
            $settings['auto_reply_on_processing'] = $this->autoReplyOnProcessing;
            $settings['staff_notify_on_session'] = $this->staffNotifyOnSession;
            $settings['auto_create_min_confidence'] = $this->autoCreateMinConfidence;
            $settings['default_daily_ai_cost_limit'] = trim((string) $this->defaultDailyAiCostLimit) === ''
                ? null
                : (float) $this->defaultDailyAiCostLimit;
            $workspace->update([
                'name' => $this->workspaceName,
                'settings' => $settings,
            ]);

            foreach ($this->rolePermissions as $roleId => $permissions) {
                $role = Role::find($roleId);
                if ($role) {
                    $activeSlugs = array_keys(array_filter($permissions));
                    $permissionIds = Permission::whereIn('slug', $activeSlugs)->pluck('id');
                    $role->permissions()->sync($permissionIds);
                }
            }

            session()->flash('success', 'Settings Saved Successfully');
        }
    }

    public function render()
    {
        return view('livewire.workspace-settings.index', [
            'roles' => Role::all(),
            'permissions' => $this->getPermissionsList(),
        ]);
    }
}
