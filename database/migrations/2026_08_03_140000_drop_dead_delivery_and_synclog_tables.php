<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5 cleanup: `Modules\Delivery` (CommunicationDispatcher, OutboundCommunication,
 * DeliverCommunicationJob, channel adapters) was fully dead code — nothing outside
 * the module ever called it, and its `MessageTemplate` model had no backing table
 * at all. `Modules\Synchronization\Models\SyncLog` / `Task::syncLogs()` was another
 * dangling relation that was never written to; the real, active sync log is
 * `Modules\Synchronization\Models\Synchronization` (`synchronizations` table).
 *
 * Both modules/models were removed in this change; this migration drops their
 * now-orphaned tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('outbound_communications');
        Schema::dropIfExists('sync_logs');
    }

    public function down(): void
    {
        // Intentionally not recreated — the code that used these tables
        // (Modules\Delivery, SyncLog) was removed in the same change.
    }
};
