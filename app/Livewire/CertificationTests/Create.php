<?php

namespace App\Livewire\CertificationTests;

use App\Models\CertificationTest;
use Livewire\Component;

class Create extends Component
{
    // Test Details
    public $name = '';
    public $description = '';
    public $category = 'functional';
    public $tags = ''; // We'll use comma-separated for simplicity in Livewire
    public $is_golden = false;
    public $is_active = true;

    // Inputs
    public $customer_phone = '077xxxxxxxx';
    public $project_name = 'Clinic ERP';
    public $boss_notes = 'Assign to Ahmed';
    public $inputMessages = 'The invoice page crashes when I click save.';

    // Expectations
    public $expected_data = '';

    protected $rules = [
        'name' => 'required|string|max:255',
        'description' => 'nullable|string',
        'category' => 'required|string|in:functional,chaos,golden,regression,performance',
        'tags' => 'nullable|string',
        'is_golden' => 'boolean',
        'is_active' => 'boolean',
        'customer_phone' => 'nullable|string',
        'project_name' => 'nullable|string',
        'boss_notes' => 'nullable|string',
        'inputMessages' => 'nullable|string',
        'expected_data' => 'nullable|string',
    ];

    public function save()
    {
        $this->validate();

        $tagsArray = array_filter(array_map('trim', explode(',', $this->tags)));

        $test = CertificationTest::create([
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category,
            'tags' => $tagsArray,
            'is_golden' => $this->is_golden,
            'is_active' => $this->is_active,
            'created_by' => auth()->id(),
        ]);

        $test->input()->create([
            'customer_phone' => $this->customer_phone,
            'project_name' => $this->project_name,
            'boss_notes' => $this->boss_notes,
            'messages' => $this->normalizeMessages($this->inputMessages),
        ]);

        if (! empty($this->expected_data)) {
            $decodedExpectation = json_decode($this->expected_data, true);
            $test->expectation()->create([
                'expected_data' => json_last_error() === JSON_ERROR_NONE ? $decodedExpectation : ['raw' => $this->expected_data],
            ]);
        }

        session()->flash('success', 'Certification Test created successfully.');

        return $this->redirectRoute('certification-tests.index', navigate: true);
    }

    /**
     * @return list<string>|array<string, mixed>
     */
    protected function normalizeMessages(?string $messages): array
    {
        $messages = trim((string) $messages);
        if ($messages === '') {
            return [];
        }

        $decoded = json_decode($messages, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        return preg_split("/\r\n|\n|\r/", $messages) ?: [$messages];
    }

    public function render()
    {
        return view('livewire.certification-tests.create');
    }
}
