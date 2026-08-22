<?php

namespace App\Livewire\Ai\Schemas;

use App\Models\AiSchema;
use Livewire\Component;

class View extends Component
{
    public AiSchema $schema;

    public function mount(AiSchema $schema)
    {
        $this->schema = $schema;
    }

    public function render()
    {
        return view('livewire.ai.schemas.view');
    }
}
