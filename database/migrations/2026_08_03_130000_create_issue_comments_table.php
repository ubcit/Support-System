<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `Modules\Issues\Models\IssueComment` was already referenced by
 * `Issue::comments()`, `IssueService::addComment()`, and
 * `IssueCommentResource`, but neither this table nor the model existed, so
 * `POST /api/v1/issues/{uuid}/comments` and `GET /api/v1/issues/{uuid}/
 * comments` always threw a fatal error (same class of gap as the
 * IssueTimeline table fixed in 2026_08_03_120000).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issue_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_id')->constrained('issues')->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->text('body');
            $table->boolean('is_internal')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issue_comments');
    }
};
