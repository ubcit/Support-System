<?php

namespace App\Livewire\Ai\Prompts;

use App\Models\AiPrompt;
use Livewire\Component;

class View extends Component
{
    public AiPrompt $prompt;

    public function mount(AiPrompt $prompt)
    {
        $this->prompt = $prompt;
    }

    public function render()
    {
        return view('livewire.ai.prompts.view');
    }
}
