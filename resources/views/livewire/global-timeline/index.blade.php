<div>
    <x-common.page-breadcrumb pageTitle="Global Timeline" />
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 h-[80vh]">

        <!-- Left Panel: Event Stream -->
        <div class="lg:col-span-4 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden flex flex-col h-full">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center bg-gray-50 dark:bg-gray-800">
                <h2 class="text-lg font-semibold tracking-tight flex items-center gap-2">
                    <x-heroicon-s-bolt class="w-5 h-5 text-amber-500"/> Live Stream
                </h2>
                <div class="flex items-center gap-2">
                    <span class="flex h-3 w-3 relative">
                      <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                      <span class="relative inline-flex rounded-full h-3 w-3 bg-red-500"></span>
                    </span>
                    <span class="text-xs font-medium text-gray-500 uppercase tracking-wider">LIVE</span>
                </div>
            </div>

            <div class="p-3 border-b border-gray-200 dark:border-gray-700">
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search UUID, Action, Payload..." class="w-full p-1 text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 focus:ring-brand-500 focus:border-brand-500">
            </div>

            <div class="flex-1 overflow-y-auto p-4 space-y-3" wire:poll.2s>
                @forelse($this->events as $event)
                    <div wire:click="selectEvent({{ $event->id }})"
                         class="cursor-pointer border rounded-lg p-3 transition hover:shadow-md {{ $this->getColorForEvent($event->event_name) }} {{ $selectedEventId === $event->id ? 'ring-2 ring-brand-500 shadow-md transform scale-[1.02]' : 'opacity-80 hover:opacity-100' }}">
                        <div class="flex justify-between items-start mb-1">
                            <span class="font-bold text-sm">{{ $event->event_name }}</span>
                            <span class="text-xs opacity-75 font-mono">{{ $event->created_at->format('H:i:s') }}</span>
                        </div>
                        <div class="text-xs opacity-90 truncate">
                            {{ $event->aggregate_type }} #{{ $event->aggregate_id }}
                        </div>
                    </div>
                @empty
                    <div class="text-center text-gray-500 dark:text-gray-400 py-8 text-sm">
                        No events found matching criteria.
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Center Panel: Event Details -->
        <div class="lg:col-span-5 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden flex flex-col h-full">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 flex justify-between items-center">
                <h2 class="text-lg font-semibold tracking-tight flex items-center gap-2">
                    <x-heroicon-o-document-magnifying-glass class="w-5 h-5 text-gray-500"/> Payload Inspector
                </h2>
                @if($selectedEvent)
                    <div class="flex gap-2">
                        <button class="inline-flex items-center justify-center gap-2 rounded-lg bg-gray-100 dark:bg-gray-800 px-3 py-1.5 text-xs font-bold text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 transition shadow-sm border border-gray-200 dark:border-gray-700">
                            <x-heroicon-m-arrow-path class="w-4 h-4"/> Replay
                        </button>
                    </div>
                @endif
            </div>

            <div class="flex-1 overflow-y-auto p-6">
                @if($selectedEvent)
                    <div class="mb-6">
                        <h3 class="text-2xl font-bold mb-2">{{ $selectedEvent->event_name }}</h3>
                        <div class="flex flex-wrap gap-2 text-xs font-mono">
                            <span class="px-2 py-1 rounded bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400">ID: {{ $selectedEvent->id }}</span>
                            <span class="px-2 py-1 rounded bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400">Corr: {{ $selectedEvent->correlation_id ?? 'N/A' }}</span>
                            <span class="px-2 py-1 rounded bg-purple-50 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400">Agg: {{ $selectedEvent->aggregate_type }}#{{ $selectedEvent->aggregate_id }}</span>
                        </div>
                    </div>

                    <div class="mb-4">
                        <h4 class="text-sm font-semibold uppercase tracking-wider text-gray-500 mb-2">JSON Payload</h4>
                        <div class="bg-gray-900 rounded-lg p-4 overflow-x-auto">
                            <pre class="text-sm font-mono text-green-400"><code>{{ json_encode($selectedEvent->payload, JSON_PRETTY_PRINT) }}</code></pre>
                        </div>
                    </div>
                @else
                    <div class="flex flex-col items-center justify-center h-full text-gray-400 gap-4">
                        <x-heroicon-o-cursor-arrow-rays class="w-16 h-16 opacity-20"/>
                        <p>Select an event from the stream to view details</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Right Panel: Correlation Context -->
        <div class="lg:col-span-3 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden flex flex-col h-full">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 flex justify-between items-center">
                <h2 class="text-lg font-semibold tracking-tight flex items-center gap-2">
                    <x-heroicon-o-link class="w-5 h-5 text-gray-500"/> Context Graph
                </h2>
                @if($selectedEvent && count($contextEvents) > 0)
                    <button class="inline-flex items-center justify-center gap-2 rounded-md bg-indigo-600 px-2 py-1 text-[10px] font-bold text-white hover:bg-indigo-700 transition shadow-sm">
                        <x-heroicon-m-play class="w-3 h-3"/> Playback
                    </button>
                @endif
            </div>

            <div class="flex-1 overflow-y-auto p-4">
                @if($selectedEvent)
                    @if(count($contextEvents) > 0)
                        <div class="relative border-l-2 border-gray-200 dark:border-gray-700 ml-3 space-y-6 mt-4">
                            @foreach($contextEvents as $contextEvent)
                                <div class="relative pl-6">
                                    <div class="absolute w-3 h-3 rounded-full -left-[7px] top-1.5 {{ $selectedEventId === $contextEvent->id ? 'bg-brand-500 ring-4 ring-brand-500/30' : 'bg-gray-300 dark:bg-gray-600' }}"></div>
                                    <div class="{{ $selectedEventId === $contextEvent->id ? 'text-brand-600 dark:text-brand-400 font-semibold' : 'text-gray-600 dark:text-gray-400' }}">
                                        <div class="text-sm">{{ $contextEvent->event_name }}</div>
                                        <div class="text-xs opacity-70 font-mono">{{ $contextEvent->created_at->format('H:i:s.v') }}</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-gray-500">No correlation context available for this event.</p>
                    @endif
                @else
                    <div class="flex flex-col items-center justify-center h-full text-gray-400 gap-4">
                        <x-heroicon-o-rectangle-stack class="w-12 h-12 opacity-20"/>
                        <p class="text-center text-sm px-4">The full execution path will appear here when an event is selected.</p>
                    </div>
                @endif
            </div>
        </div>

    </div>
</div>
