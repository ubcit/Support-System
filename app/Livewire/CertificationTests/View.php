<?php

namespace App\Livewire\CertificationTests;

use App\Models\CertificationTest;
use Livewire\Component;

class View extends Component
{
    public CertificationTest $test;

    public function mount(CertificationTest $test)
    {
        $this->test = $test->load('input', 'expectation');
    }

    public function render()
    {
        return view('livewire.certification-tests.view');
    }
}
