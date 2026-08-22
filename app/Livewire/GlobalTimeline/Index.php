<?php

namespace App\Livewire\GlobalTimeline;

use Livewire\Component;
use Modules\Telemetry\Models\DomainEvent;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

class Index extends Component
{
    #[Url]
    public ?string $search = '';

    public ?string $selectedEventId = null;
    
    public ?DomainEvent $selectedEvent = null;
    public Collection $contextEvents;

    public function mount()
    {
        $this->contextEvents = collect();
    }

    public function getEventsProperty()
    {
        return DomainEvent::query()
            ->when($this->search, function ($query) {
                $query->where('event_name', 'like', "%{$this->search}%")
                      ->orWhere('correlation_id', 'like', "%{$this->search}%")
                      ->orWhere('payload', 'like', "%{$this->search}%");
            })
            ->latest('created_at')
            ->limit(50)
            ->get();
    }

    public function selectEvent($eventId)
    {
        $this->selectedEventId = $eventId;
        $this->selectedEvent = DomainEvent::find($eventId);
        
        if ($this->selectedEvent && $this->selectedEvent->correlation_id) {
            $this->contextEvents = DomainEvent::where('correlation_id', $this->selectedEvent->correlation_id)
                ->orderBy('created_at', 'asc')
                ->get();
        } else {
            $this->contextEvents = collect();
        }
    }
    
    public function getColorForEvent(string $eventName): string
    {
        // Simple mapping based on the spec
        if (str_contains($eventName, 'Conversation')) return 'bg-blue-100 text-blue-800 border-blue-300';
        if (str_contains($eventName, 'AI')) return 'bg-purple-100 text-purple-800 border-purple-300';
        if (str_contains($eventName, 'Rule')) return 'bg-orange-100 text-orange-800 border-orange-300';
        if (str_contains($eventName, 'Task')) return 'bg-green-100 text-green-800 border-green-300';
        if (str_contains($eventName, 'Sync')) return 'bg-cyan-100 text-cyan-800 border-cyan-300';
        if (str_contains($eventName, 'Notif')) return 'bg-yellow-100 text-yellow-800 border-yellow-300';
        if (str_contains($eventName, 'Fail') || str_contains($eventName, 'Error')) return 'bg-red-100 text-red-800 border-red-300';
        
        return 'bg-gray-100 text-gray-800 border-gray-300';
    }

    public function render()
    {
        return view('livewire.global-timeline.index');
    }
}
