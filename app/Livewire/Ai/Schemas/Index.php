<?php

namespace App\Livewire\Ai\Schemas;

use App\Livewire\Concerns\AuthorizesActions;
use App\Models\AiSchema;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use AuthorizesActions, WithPagination;

    public $search = '';

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function delete($id)
    {
        $this->authorizePermission('ai.manage');
        $schema = AiSchema::findOrFail($id);
        $schema->delete();
        session()->flash('success', 'Schema deleted successfully.');
    }

    public function render()
    {
        $schemas = AiSchema::when($this->search, function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('version', 'like', '%' . $this->search . '%');
            })->paginate(10);

        return view('livewire.ai.schemas.index', [
            'schemas' => $schemas,
        ]);
    }
}
