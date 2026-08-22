<?php

namespace App\Livewire\PromptPlayground;

use App\Models\AiModel;
use App\Models\AiPrompt;
use App\Services\AI\AIManager;
use Livewire\Component;

class Index extends Component
{
    public ?int $ai_model_id = null;

    public ?int $ai_prompt_id = null;

    public string $customer_message = '';

    public bool $chaos_mode = false;

    public ?array $output = null;

    public ?string $outputLogUuid = null;

    protected $rules = [
        'ai_model_id' => 'required|exists:ai_models,id',
        'ai_prompt_id' => 'required|exists:ai_prompts,id',
        'customer_message' => 'required|string',
        'chaos_mode' => 'boolean',
    ];

    public function runPlayground(): void
    {
        $this->validate();

        $this->output = null;
        $this->outputLogUuid = null;

        try {
            $model = AiModel::findOrFail($this->ai_model_id);
            $prompt = AiPrompt::with('schema')->findOrFail($this->ai_prompt_id);

            if (! $prompt->schema) {
                throw new \Exception('Prompt has no associated Schema.');
            }

            $manager = new AIManager;
            $log = $manager->execute($this->customer_message, $model, $prompt, $prompt->schema, null, $this->chaos_mode, [
                'source' => 'playground',
            ]);

            $this->output = $log->toArray();
            $this->outputLogUuid = $log->uuid;

            session()->flash('success', 'AI Execution Complete');
        } catch (\Exception $e) {
            session()->flash('error', 'AI Execution Failed: '.$e->getMessage());
        }
    }

    public function render()
    {
        $models = AiModel::where('is_active', true)->get();
        $prompts = AiPrompt::where('is_active', true)->get();

        return view('livewire.prompt-playground.index', [
            'models' => $models,
            'prompts' => $prompts,
        ]);
    }
}
