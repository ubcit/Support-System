<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `Modules\Issues\Models\IssueTimeline` was already referenced by
 * `Issue::timeline()` and `IssueService::addTimelineEntry()`, but neither
 * this table nor the model existed, so `POST /api/v1/issues` (and
 * changeStatus/assign/addComment/timeline) always threw a fatal error.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issue_timelines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_id')->constrained('issues')->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('action');
            $table->string('old_value')->nullable();
            $table->string('new_value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issue_timelines');
    }
};
