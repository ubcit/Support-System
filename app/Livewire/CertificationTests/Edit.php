<?php

namespace App\Livewire\CertificationTests;

use App\Models\CertificationTest;
use Livewire\Component;

class Edit extends Component
{
    public CertificationTest $test;

    // Test Details
    public $name = '';
    public $description = '';
    public $category = 'functional';
    public $tags = ''; 
    public $is_golden = false;
    public $is_active = true;

    // Inputs
    public $customer_phone = '';
    public $project_name = '';
    public $boss_notes = '';
    public $inputMessages = '';

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

    public function mount(CertificationTest $test)
    {
        $this->test = $test->load('input', 'expectation');

        $this->name = $test->name;
        $this->description = $test->description;
        $this->category = $test->category;
        $this->tags = is_array($test->tags) ? implode(', ', $test->tags) : '';
        $this->is_golden = $test->is_golden;
        $this->is_active = $test->is_active;

        if ($test->input) {
            $this->customer_phone = $test->input->customer_phone;
            $this->project_name = $test->input->project_name;
            $this->boss_notes = $test->input->boss_notes;
            $this->inputMessages = $this->messagesToString($test->input->messages);
        }

        if ($test->expectation) {
            $this->expected_data = is_array($test->expectation->expected_data)
                ? json_encode($test->expectation->expected_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                : (string) $test->expectation->expected_data;
        }
    }

    public function save()
    {
        $this->validate();

        $tagsArray = array_filter(array_map('trim', explode(',', $this->tags)));

        $this->test->update([
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category,
            'tags' => $tagsArray,
            'is_golden' => $this->is_golden,
            'is_active' => $this->is_active,
        ]);

        $this->test->input()->updateOrCreate(
            ['certification_test_id' => $this->test->id],
            [
                'customer_phone' => $this->customer_phone,
                'project_name' => $this->project_name,
                'boss_notes' => $this->boss_notes,
                'messages' => $this->normalizeMessages($this->inputMessages),
            ]
        );

        if (! empty($this->expected_data)) {
            $decodedExpectation = json_decode($this->expected_data, true);
            $this->test->expectation()->updateOrCreate(
                ['certification_test_id' => $this->test->id],
                [
                    'expected_data' => json_last_error() === JSON_ERROR_NONE
                        ? $decodedExpectation
                        : ['raw' => $this->expected_data],
                ]
            );
        } elseif ($this->test->expectation) {
            $this->test->expectation()->delete();
        }

        session()->flash('success', 'Certification Test updated successfully.');

        return $this->redirectRoute('certification-tests.index', navigate: true);
    }

    protected function messagesToString(mixed $messages): string
    {
        if (is_array($messages)) {
            if (array_is_list($messages)) {
                return implode("\n", array_map(
                    fn ($line) => is_scalar($line) ? (string) $line : json_encode($line),
                    $messages
                ));
            }

            return json_encode($messages, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '';
        }

        return (string) ($messages ?? '');
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
        return view('livewire.certification-tests.edit');
    }
}
