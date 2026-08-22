<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Extend tasks table
        Schema::table('tasks', function (Blueprint $table) {
            if (!Schema::hasColumn('tasks', 'start_date')) {
                $table->date('start_date')->nullable()->after('due_date');
            }
            if (!Schema::hasColumn('tasks', 'recurrence_rule')) {
                $table->string('recurrence_rule')->nullable()->after('start_date');
            }
            if (!Schema::hasColumn('tasks', 'recurrence_pattern')) {
                $table->json('recurrence_pattern')->nullable()->after('recurrence_rule');
            }
            if (!Schema::hasColumn('tasks', 'archived_at')) {
                $table->timestamp('archived_at')->nullable()->after('completed_at');
            }
            if (!Schema::hasColumn('tasks', 'wip_limit')) {
                $table->integer('wip_limit')->default(0)->after('archived_at');
            }
            if (!Schema::hasColumn('tasks', 'sort_order')) {
                $table->integer('sort_order')->default(0)->after('wip_limit');
            }
            if (!Schema::hasColumn('tasks', 'custom_fields')) {
                $table->json('custom_fields')->nullable()->after('metadata');
            }
        });

        // Add workflow_id to projects
        Schema::table('projects', function (Blueprint $table) {
            if (!Schema::hasColumn('projects', 'workflow_id')) {
                $table->foreignId('workflow_id')->nullable()->after('category_id')->constrained('workflows')->nullOnDelete();
            }
        });

        // 2. Checklists & Items
        Schema::create('task_checklists', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->string('title');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('task_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('checklist_id')->constrained('task_checklists')->cascadeOnDelete();
            $table->string('title');
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('employees')->nullOnDelete();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // 3. Task Templates
        Schema::create('task_templates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type')->default('task');
            $table->foreignId('workflow_id')->nullable()->constrained('workflows')->nullOnDelete();
            $table->string('default_priority')->default('medium');
            $table->json('checklists_template')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        // 4. Dependencies
        Schema::create('task_dependencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('depends_on_task_id')->constrained('tasks')->cascadeOnDelete();
            $table->string('type')->default('blocks'); // blocks, blocked_by, relates_to
            $table->timestamps();

            $table->unique(['task_id', 'depends_on_task_id', 'type']);
        });

        // 5. Task Stakeholders (Watchers, Followers, Reviewers, QA)
        Schema::create('task_stakeholders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('role')->default('watcher'); // watcher, follower, reviewer, qa
            $table->foreignId('assigned_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamps();

            $table->unique(['task_id', 'employee_id', 'role']);
        });

        // 6. Time Logs
        Schema::create('task_time_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->timestamp('start_time');
            $table->timestamp('end_time')->nullable();
            $table->integer('duration_minutes')->default(0);
            $table->boolean('is_running')->default(false);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // 7. Tags & Task-Tag pivot
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('color')->default('#6B7280');
            $table->timestamps();
        });

        Schema::create('task_tag', function (Blueprint $table) {
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();
            $table->primary(['task_id', 'tag_id']);
        });

        // 8. Milestones & Sprints
        Schema::create('milestones', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('project_id')->nullable()->constrained('projects')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->string('status')->default('planned');
            $table->timestamps();
        });

        Schema::create('sprints', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('project_id')->nullable()->constrained('projects')->cascadeOnDelete();
            $table->string('name');
            $table->text('goal')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status')->default('planned'); // planned, active, completed
            $table->timestamps();
        });

        // Add milestone_id & sprint_id to tasks if not present
        Schema::table('tasks', function (Blueprint $table) {
            if (!Schema::hasColumn('tasks', 'milestone_id')) {
                $table->foreignId('milestone_id')->nullable()->after('project_id')->constrained('milestones')->nullOnDelete();
            }
            if (!Schema::hasColumn('tasks', 'sprint_id')) {
                $table->foreignId('sprint_id')->nullable()->after('milestone_id')->constrained('sprints')->nullOnDelete();
            }
        });

        // 9. Custom Fields definitions
        Schema::create('task_custom_field_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained('projects')->cascadeOnDelete();
            $table->string('name');
            $table->string('field_type'); // text, number, date, select, checkbox
            $table->json('options')->nullable();
            $table->timestamps();
        });

        // 10. Task Activity Logs (History / Timeline)
        Schema::create('task_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('action'); // state_changed, field_updated, comment_added, assignment_changed, time_logged
            $table->string('field')->nullable();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        // 11. Employee Task Personal States (Bookmarks, Pinned, Recently Viewed)
        Schema::create('employee_task_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->boolean('is_bookmarked')->default(false);
            $table->boolean('is_pinned')->default(false);
            $table->timestamp('last_viewed_at')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'task_id']);
        });

        // 12. Kanban Boards & Saved Filters
        Schema::create('kanban_boards', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('project_id')->nullable()->constrained('projects')->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->cascadeOnDelete();
            $table->string('title');
            $table->json('columns_config')->nullable();
            $table->string('swimlane')->nullable(); // priority, assignee, type
            $table->json('filters')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('saved_filters', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('name');
            $table->string('entity_type')->default('task');
            $table->json('filter_criteria');
            $table->timestamps();
        });

        // 13. Notifications Center
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('type'); // task_assigned, mention, review_requested, due_soon, escalation
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('channel')->default('in_app'); // in_app, email, whatsapp, push
            $table->string('action_url')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('employee_id');
            $table->index('read_at');
        });

        // 14. Knowledge Base Architecture (Future RAG & Docs)
        Schema::create('knowledge_base_articles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('project_id')->nullable()->constrained('projects')->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('content')->nullable();
            $table->string('category')->default('general');
            $table->integer('version')->default(1);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_base_articles');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('saved_filters');
        Schema::dropIfExists('kanban_boards');
        Schema::dropIfExists('employee_task_states');
        Schema::dropIfExists('task_activity_logs');
        Schema::dropIfExists('task_custom_field_definitions');
        
        Schema::table('tasks', function (Blueprint $table) {
            if (Schema::hasColumn('tasks', 'sprint_id')) {
                $table->dropForeign(['sprint_id']);
                $table->dropColumn('sprint_id');
            }
            if (Schema::hasColumn('tasks', 'milestone_id')) {
                $table->dropForeign(['milestone_id']);
                $table->dropColumn('milestone_id');
            }
        });

        Schema::dropIfExists('sprints');
        Schema::dropIfExists('milestones');
        Schema::dropIfExists('task_tag');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('task_time_logs');
        Schema::dropIfExists('task_stakeholders');
        Schema::dropIfExists('task_dependencies');
        Schema::dropIfExists('task_templates');
        Schema::dropIfExists('task_checklist_items');
        Schema::dropIfExists('task_checklists');

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn([
                'start_date',
                'recurrence_rule',
                'recurrence_pattern',
                'archived_at',
                'wip_limit',
                'sort_order',
                'custom_fields',
            ]);
        });
    }
};
