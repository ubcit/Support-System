<?php

namespace App\Livewire\Ai\Models;

use App\Livewire\Concerns\AuthorizesActions;
use App\Models\AiModel;
use Livewire\Component;

class Create extends Component
{
    use AuthorizesActions;
    public $name = '';
    public $api_model_id = '';
    public $provider = 'openai';
    public $context_length = 0;
    public $is_active = true;
    public $supports_vision = false;
    public $supports_audio = false;
    public $supports_json = false;
    public $supports_tools = false;
    public $is_shadow_mode = false;

    protected $rules = [
        'name' => 'required|string|max:255',
        'api_model_id' => 'required|string|max:255',
        'provider' => 'required|string|in:openai,gemini,groq,anthropic,qwen,ollama,mock',
        'context_length' => 'nullable|integer',
        'is_active' => 'boolean',
        'supports_vision' => 'boolean',
        'supports_audio' => 'boolean',
        'supports_json' => 'boolean',
        'supports_tools' => 'boolean',
        'is_shadow_mode' => 'boolean',
    ];

    public function save()
    {
        $this->authorizePermission('ai.manage');
        $validatedData = $this->validate();

        AiModel::create($validatedData);

        session()->flash('success', 'Model created successfully.');
        return $this->redirectRoute('ai.models.index', navigate: true);
    }

    public function render()
    {
        return view('livewire.ai.models.create');
    }
}
