<?php

namespace Modules\Communication\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Communication\Models\WorkCommunication;

class CommunicationCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public WorkCommunication $communication
    ) {}
}
