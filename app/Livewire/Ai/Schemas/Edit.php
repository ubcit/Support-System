<?php

namespace App\Livewire\Ai\Schemas;

use App\Livewire\Concerns\AuthorizesActions;
use App\Models\AiSchema;
use Livewire\Component;

class Edit extends Component
{
    use AuthorizesActions;
    public AiSchema $schema;

    public $name = '';
    public $version = 'v1';
    public $is_active = true;
    public $schema_json = '';

    protected function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'version' => 'required|string|max:255',
            'is_active' => 'boolean',
            'schema_json' => 'required|string',
        ];
    }

    public function mount(AiSchema $schema)
    {
        $this->schema = $schema;
        $this->name = $schema->name;
        $this->version = $schema->version;
        $this->is_active = $schema->is_active;
        $this->schema_json = is_array($schema->schema_json)
            ? json_encode($schema->schema_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            : (string) ($schema->schema_json ?? '');
    }

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

        $this->schema->update($validatedData);

        session()->flash('success', 'Schema updated successfully.');

        return $this->redirectRoute('ai.schemas.index', navigate: true);
    }

    public function render()
    {
        return view('livewire.ai.schemas.edit');
    }
}
