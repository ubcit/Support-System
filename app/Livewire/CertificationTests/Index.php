<?php

namespace App\Livewire\CertificationTests;

use App\Models\CertificationTest;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public $search = '';
    public $category = '';
    public $is_golden = '';

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingCategory()
    {
        $this->resetPage();
    }

    public function updatingIsGolden()
    {
        $this->resetPage();
    }

    public function delete($id)
    {
        $test = CertificationTest::findOrFail($id);
        $test->delete();
        session()->flash('success', 'Test deleted successfully.');
    }

    public function render()
    {
        $tests = CertificationTest::when($this->search, function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%');
            })
            ->when($this->category, function ($query) {
                $query->where('category', $this->category);
            })
            ->when($this->is_golden !== '', function ($query) {
                $query->where('is_golden', $this->is_golden);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('livewire.certification-tests.index', [
            'tests' => $tests,
        ]);
    }
}
