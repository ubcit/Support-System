<?php

namespace App\Livewire\Ai\Prompts;

use App\Livewire\Concerns\AuthorizesActions;
use App\Models\AiPrompt;
use App\Models\AiSchema;
use Livewire\Component;

class Create extends Component
{
    use AuthorizesActions;
    public $name = '';
    public $version = 'v1';
    public $purpose = '';
    public $ai_schema_id = null;
    public $temperature = 0.00;
    public $is_active = true;
    public $system_prompt = '';
    public $user_prompt_template = '';
    public $provider_overrides = '';

    protected $rules = [
        'name' => 'required|string|max:255',
        'version' => 'required|string|max:255',
        'purpose' => 'nullable|string|max:255',
        'ai_schema_id' => 'nullable|exists:ai_schemas,id',
        'temperature' => 'nullable|numeric',
        'is_active' => 'boolean',
        'system_prompt' => 'nullable|string',
        'user_prompt_template' => 'nullable|string',
        'provider_overrides' => 'nullable|string',
    ];

    public function save()
    {
        $this->authorizePermission('ai.manage');
        $validatedData = $this->validate();
        AiPrompt::create($validatedData);

        session()->flash('success', 'Prompt created successfully.');
        return $this->redirectRoute('ai.prompts.index', navigate: true);
    }

    public function render()
    {
        $schemas = AiSchema::all();
        return view('livewire.ai.prompts.create', [
            'schemas' => $schemas,
        ]);
    }
}
