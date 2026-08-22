# 07. Task Platform

Tasks are the fundamental units of work in THE-SPACE-MANAGEMENT. If a problem isn't captured as a Task, it doesn't exist.

## The Task Lifecycle
A Task moves through a standardized lifecycle defined by your company's Workflow.
1. **Creation:** Generated manually by a Boss or automatically by the AI Pipeline.
2. **Assignment:** Delegated to a specific Employee.
3. **Execution:** Moved to "In Progress" while work occurs.
4. **Completion:** Moved to "Done".

## Statuses & Workflows
Tasks do not have hardcoded statuses like "Open" or "Closed". Instead, they use dynamic **Workflow States**.
- Typical columns: `To Do` ➔ `In Progress` ➔ `Under Review` ➔ `Done`.
- **WIP Limits (Work In Progress):** The system prevents employees from becoming overwhelmed. If the "In Progress" column has a WIP Limit of 3, the system will actively block you from dragging a 4th task into that column.

## Subtasks & Checklists
Complex tasks can be broken down.
- Add a **Checklist** to a task (e.g., "1. Check power. 2. Replace toner. 3. Print test page.").
- Subtasks must be completed before the parent task can be marked Done.

## Dependencies
Tasks can block other tasks.
- If Task B depends on Task A, Task B cannot be moved to "In Progress" until Task A is "Done".

## Watchers & Reviewers
- **Assignee:** The person doing the work.
- **Reviewer:** The Boss who must approve the work before it is finalized.
- **Watchers:** Anyone who wishes to receive notifications when the task is updated.

## Time Tracking
The system handles time tracking automatically.
- It records the exact timestamp when a task is created (`created_at`).
- It records the exact timestamp when a task transitions to the final 'Done' state (`completed_at`).
- The difference between these two timestamps calculates your **Response Time KPIs**.

## Attachments
You can upload files directly to a Task.
- Maximum file size is typically 20MB.
- Supported formats: Images (JPG/PNG), PDFs, and standard Office documents.

## Recurring Tasks
For preventative maintenance or regular audits, you can set a task to recur.
- e.g., "Server Backup Audit" ➔ Recurs every Friday at 9:00 AM.
- The system will automatically spawn a fresh task clone at the specified interval.

## Archive vs. Delete
- You should almost **never** delete a Task. Deleting destroys historical metrics.
- Instead, Tasks are automatically **Archived** once they remain in the "Done" column for a specific duration (e.g., 30 days). Archived tasks remain fully searchable but vanish from active Kanban boards.

## Search & Bulk Actions
- Use the **Global Search** to find any task by its Title or ID (e.g., `TSK-1042`).
- In the Table view, you can select multiple tasks using checkboxes to apply **Bulk Actions** (e.g., Reassign 10 tasks to a new employee simultaneously).

## Views: Kanban, Calendar, Timeline, Table
You are not restricted to one view.
- **Kanban:** Best for daily execution (drag and drop cards).
- **Calendar:** Best for visualizing Due Dates.
- **Timeline (Gantt):** Best for seeing overlapping task durations.
- **Table:** Best for bulk editing and dense data sorting.

[Screenshot: Task Kanban Board]
