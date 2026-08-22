<?php

namespace App\Livewire\Ai\Models;

use App\Models\AiModel;
use Livewire\Component;

class View extends Component
{
    public AiModel $model;

    public function mount(AiModel $model)
    {
        $this->model = $model;
    }

    public function render()
    {
        return view('livewire.ai.models.view');
    }
}
