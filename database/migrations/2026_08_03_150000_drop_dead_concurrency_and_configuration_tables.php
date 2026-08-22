<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5 cleanup: `Modules\Concurrency` (`LockManager`, `IdempotencyService`,
 * `OutboxMessage`, `ProcessOutboxMessagesJob`) and `Modules\Configuration`
 * (`FeatureManager`, `FeatureFlag`) were confirmed via grep to have zero
 * callers anywhere outside their own singleton registration in
 * `ModuleServiceProvider` — pure inert scaffolding. Both modules were removed
 * in this change; this migration drops their now-orphaned tables.
 *
 * `Modules\Telemetry` was NOT removed: `MetricsRegistry` feeds the real
 * `OperationsDashboard`, and `DomainEvent`/`domain_events` is read by
 * `GlobalTimeline`. Only the unused `ContextRegistry` service was deleted
 * (it had no backing table to drop).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('outbox_messages');
        Schema::dropIfExists('feature_flags');
    }

    public function down(): void
    {
        // Intentionally not recreated — the code that used these tables
        // (Modules\Concurrency, Modules\Configuration) was removed in the
        // same change.
    }
};
