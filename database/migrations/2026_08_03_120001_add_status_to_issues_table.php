<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `Issue` casts `status` to `IssueStatus::class` and `IssueRepository`
 * queries/filters directly on a `status` column (getByStatus(), getOverdue()),
 * but the column was never actually added to the `issues` table, so:
 *   - status was always null in-memory (crashing IssueService::create()/
 *     IssueResource on `$issue->status->value`)
 *   - IssueService::changeStatus()'s `update(['status' => ...])` silently
 *     wrote nothing (mass-assignment can't create new columns)
 *   - IssueRepository::getByStatus()/getOverdue() would throw "no such
 *     column" if ever called
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->string('status')->default('new')->after('current_state_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });
    }
};
