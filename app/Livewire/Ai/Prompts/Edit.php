<?php

namespace App\Livewire\Ai\Prompts;

use App\Livewire\Concerns\AuthorizesActions;
use App\Models\AiPrompt;
use App\Models\AiSchema;
use Livewire\Component;

class Edit extends Component
{
    use AuthorizesActions;
    public AiPrompt $prompt;

    public $name = '';
    public $version = 'v1';
    public $purpose = '';
    public $ai_schema_id = null;
    public $temperature = 0.00;
    public $is_active = true;
    public $system_prompt = '';
    public $user_prompt_template = '';
    public $provider_overrides = '';

    protected function rules()
    {
        return [
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
    }

    public function mount(AiPrompt $prompt)
    {
        $this->prompt = $prompt;
        $this->name = $prompt->name;
        $this->version = $prompt->version;
        $this->purpose = $prompt->purpose;
        $this->ai_schema_id = $prompt->ai_schema_id;
        $this->temperature = $prompt->temperature;
        $this->is_active = $prompt->is_active;
        $this->system_prompt = $prompt->system_prompt;
        $this->user_prompt_template = $prompt->user_prompt_template;
        $this->provider_overrides = $prompt->provider_overrides;
    }

    public function save()
    {
        $this->authorizePermission('ai.manage');
        $validatedData = $this->validate();
        $this->prompt->update($validatedData);

        session()->flash('success', 'Prompt updated successfully.');
        return $this->redirectRoute('ai.prompts.index', navigate: true);
    }

    public function render()
    {
        $schemas = AiSchema::all();
        return view('livewire.ai.prompts.edit', [
            'schemas' => $schemas,
        ]);
    }
}
