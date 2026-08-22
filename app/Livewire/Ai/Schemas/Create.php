<?php

namespace App\Livewire\Ai\Schemas;

use App\Livewire\Concerns\AuthorizesActions;
use App\Models\AiSchema;
use Livewire\Component;

class Create extends Component
{
    use AuthorizesActions;
    public $name = '';
    public $version = 'v1';
    public $is_active = true;
    public $schema_json = '';

    protected $rules = [
        'name' => 'required|string|max:255',
        'version' => 'required|string|max:255',
        'is_active' => 'boolean',
        'schema_json' => 'required|string',
    ];

    public function save()
    {
        $this->authorizePermission('ai.manage');
        $validatedData = $this->validate();

        $decoded = json_decode($this->schema_json, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            $this->addError('schema_json', 'Invalid JSON format.');

            return;
        }

        $validatedData['schema_json'] = $decoded;

        AiSchema::create($validatedData);

        session()->flash('success', 'Schema created successfully.');

        return $this->redirectRoute('ai.schemas.index', navigate: true);
    }

    public function render()
    {
        return view('livewire.ai.schemas.create');
    }
}
