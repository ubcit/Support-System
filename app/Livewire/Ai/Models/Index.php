<?php

namespace App\Livewire\Ai\Models;

use App\Livewire\Concerns\AuthorizesActions;
use App\Models\AiModel;
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
        $model = AiModel::findOrFail($id);
        $model->delete();
        session()->flash('success', 'Model deleted successfully.');
    }

    public function render()
    {
        $models = AiModel::when($this->search, function ($query) {
            $query->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('provider', 'like', '%' . $this->search . '%');
        })->paginate(10);

        return view('livewire.ai.models.index', [
            'models' => $models,
        ]);
    }
}
