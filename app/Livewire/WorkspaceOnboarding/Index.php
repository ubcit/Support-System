<?php

namespace App\Livewire\WorkspaceOnboarding;

use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;
use Modules\Customers\Models\Customer;
use Modules\Employees\Models\Employee;
use Modules\MultiTenancy\Models\Workspace;
use Modules\Projects\Models\Project;

class Index extends Component
{
    use WithFileUploads;

    public int $currentStep = 1;

    public string $company_name = '';

    public string $timezone = 'Asia/Baghdad';

    public $logo;

    public string $owner_phone = '';

    public string $initial_project = 'Clinic ERP';

    public string $whatsapp_token = '';

    public string $whatsapp_phone_id = '';

    public string $ai_provider = 'gemini';

    public string $ai_api_key = '';

    public bool $enable_ai = true;

    public string $task_provider = 'native';

    public string $workflow_template = 'it_support';

    public bool $rule_auto_assign = true;

    public bool $rule_notify_customer = true;

    public bool $rule_escalate_overdue = true;

    protected $rules = [
        1 => [
            'company_name' => 'required|string',
            'timezone' => 'required|string',
            'logo' => 'nullable|image',
        ],
        2 => [
            'owner_phone' => 'nullable|string',
        ],
        3 => [
            'initial_project' => 'required|string',
        ],
        4 => [
            'whatsapp_token' => 'nullable|string',
            'whatsapp_phone_id' => 'nullable|string',
        ],
        5 => [
            'ai_provider' => 'required|string',
            'ai_api_key' => 'nullable|string',
            'enable_ai' => 'boolean',
        ],
        6 => [
            'task_provider' => 'required|string',
        ],
        7 => [
            'workflow_template' => 'required|string',
        ],
        8 => [
            'rule_auto_assign' => 'boolean',
            'rule_notify_customer' => 'boolean',
            'rule_escalate_overdue' => 'boolean',
        ],
    ];

    public function nextStep()
    {
        $this->validate($this->rules[$this->currentStep]);
        $this->currentStep++;
    }

    public function previousStep()
    {
        $this->currentStep--;
    }

    public function submit()
    {
        $workspaceName = $this->company_name ?: 'My Workspace';

        $logoPath = null;
        if ($this->logo) {
            $logoPath = $this->logo->store('workspaces', 'public');
        }

        // 1. Create Workspace
        $workspace = Workspace::create([
            'name' => $workspaceName,
            'slug' => Str::slug($workspaceName).'-'.uniqid(),
            'settings' => [
                'timezone' => $this->timezone,
                'ai_provider' => $this->ai_provider,
                'ai_api_key' => $this->ai_api_key ?: null,
                'enable_ai' => $this->enable_ai,
                'whatsapp_token' => $this->whatsapp_token ?: null,
                'whatsapp_phone_id' => $this->whatsapp_phone_id ?: null,
                'task_provider' => $this->task_provider,
                'workflow_template' => $this->workflow_template,
                'rule_auto_assign' => $this->rule_auto_assign,
                'rule_notify_customer' => $this->rule_notify_customer,
                'rule_escalate_overdue' => $this->rule_escalate_overdue,
                'logo_path' => $logoPath,
            ],
            'is_active' => true,
        ]);

        // 2. Link current user as Owner & assign unattached seeded employees to workspace
        if (auth()->check()) {
            $user = auth()->user();

            $userEmployee = Employee::where('user_id', $user->id)->first();
            if ($userEmployee) {
                $userEmployee->update([
                    'workspace_id' => $workspace->id,
                    'role' => 'Owner',
                    'phone' => $this->owner_phone ?: $userEmployee->phone,
                ]);
            } else {
                Employee::create([
                    'uuid' => (string) Str::uuid(),
                    'user_id' => $user->id,
                    'workspace_id' => $workspace->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $this->owner_phone ?: null,
                    'role' => 'Owner',
                    'is_available' => true,
                ]);
            }

            // Assign all unattached seeded team members to this new workspace
            Employee::whereNull('workspace_id')->update([
                'workspace_id' => $workspace->id,
            ]);
        }

        // 3. Create Default Customer & Initial Project
        if (! empty($this->initial_project)) {
            $customer = Customer::create([
                'uuid' => (string) Str::uuid(),
                'workspace_id' => $workspace->id,
                'name' => 'Internal ('.$workspaceName.')',
                'email' => 'admin@'.Str::slug($workspaceName).'.com',
            ]);

            Project::create([
                'uuid' => (string) Str::uuid(),
                'workspace_id' => $workspace->id,
                'customer_id' => $customer->id,
                'name' => $this->initial_project,
                'description' => 'Initial project created during onboarding.',
                'status' => 'active',
            ]);
        }

        session()->flash('success', 'Workspace Initialized Successfully');

        return redirect()->route('dashboard');
    }

    public function render()
    {
        return view('livewire.workspace-onboarding.index');
    }
}
