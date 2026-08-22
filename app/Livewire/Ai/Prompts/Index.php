<?php

namespace App\Livewire\Ai\Prompts;

use App\Livewire\Concerns\AuthorizesActions;
use App\Models\AiPrompt;
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
        $prompt = AiPrompt::findOrFail($id);
        $prompt->delete();
        session()->flash('success', 'Prompt deleted successfully.');
    }

    public function render()
    {
        $prompts = AiPrompt::with('schema')
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('version', 'like', '%' . $this->search . '%');
            })->paginate(10);

        return view('livewire.ai.prompts.index', [
            'prompts' => $prompts,
        ]);
    }
}
