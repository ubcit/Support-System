<?php

namespace App\Livewire\Ai\Models;

use App\Livewire\Concerns\AuthorizesActions;
use App\Models\AiModel;
use Livewire\Component;

class Edit extends Component
{
    use AuthorizesActions;
    public AiModel $model;

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

    protected function rules()
    {
        return [
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
    }

    public function mount(AiModel $model)
    {
        $this->model = $model;
        $this->name = $model->name;
        $this->api_model_id = $model->api_model_id ?? '';
        $this->provider = $model->provider;
        $this->context_length = $model->context_length;
        $this->is_active = $model->is_active;
        $this->supports_vision = $model->supports_vision;
        $this->supports_audio = $model->supports_audio;
        $this->supports_json = $model->supports_json;
        $this->supports_tools = $model->supports_tools;
        $this->is_shadow_mode = $model->is_shadow_mode;
    }

    public function save()
    {
        $this->authorizePermission('ai.manage');
        $validatedData = $this->validate();

        $this->model->update($validatedData);

        session()->flash('success', 'Model updated successfully.');
        return $this->redirectRoute('ai.models.index', navigate: true);
    }

    public function render()
    {
        return view('livewire.ai.models.edit');
    }
}
