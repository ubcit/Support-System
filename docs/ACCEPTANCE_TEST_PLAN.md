# Acceptance Test Plan

This document outlines the end-to-end acceptance scenarios for validating the platform. Every scenario tests the workflow from inception to completion, verifying database, API, and UI behavior.

## 1. Administrator Workflow
**Preconditions:** Logged in as Admin.
**Steps:**
1. Navigate to Employee Management.
2. Create a new employee with 'Employee' role.
3. Archive the employee.
**Expected Result:** Employee created, then deactivated.
**Database Verification:** `users` table inserts new record. `employees` table inserts new record. `deleted_at` populated on archive.
**UI Verification:** Employee appears in the active list, then moves to archived.
**Failure Cases:** Try creating without an email; expect validation error.

## 2. Boss Workflow
**Preconditions:** Logged in as Boss.
**Steps:**
1. Navigate to Project Hub.
2. Create a new Workspace and Project.
3. Assign an Admin and Employee to the Project.
**Expected Result:** Project created, members assigned.
**Database Verification:** `projects` and `project_members` tables inserted.
**UI Verification:** Project appears in Project Hub metrics. Members count increases.
**Failure Cases:** Try assigning a non-existent employee.

## 3. Employee Workflow
**Preconditions:** Logged in as Employee.
**Steps:**
1. Open Task Dashboard.
2. Drag task from 'To Do' to 'In Progress' in Kanban.
3. Complete the task and add a comment.
**Expected Result:** Task status changes, completion time recorded.
**Database Verification:** `tasks.completed_at` populated. `task_activity_logs` updated.
**UI Verification:** Task moves to completed column.
**Failure Cases:** Attempt to move a task breaking a WIP limit; expect system to block move.

## 4. Customer Workflow
**Preconditions:** Customer exists in CRM.
**Steps:**
1. Open Customer CRM.
2. Search for the customer.
3. View Linked Tasks and AI Summary.
**Expected Result:** Customer details render dynamically.
**Database Verification:** `$customer->metadata['ai_summary']` is retrieved successfully.
**UI Verification:** Status badge highlights active/inactive.
**Failure Cases:** Search with invalid ID returns empty state gracefully.

## 5. AI Pipeline
**Preconditions:** Active webhook payload ready.
**Steps:**
1. Simulate incoming problem string from Customer.
2. Dispatch `ProcessIncomingMessage` job.
**Expected Result:** AI interprets problem and auto-creates a Task assigned to best employee.
**Database Verification:** `messages`, `issues`, and `tasks` created.
**UI Verification:** Admin sees new Task in Kanban.
**Failure Cases:** AI fails parsing; fallback to manual triage queue.

## 6. WhatsApp Pipeline
**Preconditions:** WhatsApp Cloud API configured.
**Steps:**
1. Receive inbound WhatsApp text.
2. Agent replies via Conversation Center.
**Expected Result:** Two-way message sync.
**Database Verification:** `messages` status transitions from `unread` to `sent` to `delivered`.
**UI Verification:** Real-time chat bubbles render with correct timestamps.
**Failure Cases:** API timeout; expect Job to retry 3 times before failing.

## 7. Task Lifecycle
**Preconditions:** Project active.
**Steps:**
1. Create task with urgent priority.
2. Set due date.
3. Mark complete.
**Expected Result:** Analytics track velocity.
**Database Verification:** `created_at` and `completed_at` diff is valid.
**UI Verification:** Reports Hub reflects average completion time.
**Failure Cases:** Completion without required fields triggers alert.

## 8. Project Lifecycle
**Preconditions:** Boss logged in.
**Steps:**
1. Create Milestone.
2. Assign 5 Tasks to Milestone.
3. Complete all 5 Tasks.
**Expected Result:** Milestone auto-completes.
**Database Verification:** `milestones.status` updates.
**UI Verification:** Project Hub shows 100% completion.
**Failure Cases:** Attempting to delete an active project with unresolved tasks blocks deletion.

## 9. Conversation Lifecycle
**Preconditions:** Active conversation.
**Steps:**
1. Customer sends attachment.
2. Agent opens Conversation Center.
3. Agent views attachment and replies.
4. Agent closes conversation.
**Expected Result:** Attachment stored securely.
**Database Verification:** `metadata['attachment_url']` exists. `conversations.status` = closed.
**UI Verification:** Attachment UI renders clickable link.
**Failure Cases:** Unsupported file type triggers error handling.

## 10. Notification Lifecycle
**Preconditions:** Event triggered (e.g. Task Assigned).
**Steps:**
1. Dispatch notification.
2. User logs in to see bell icon.
3. User marks as read.
**Expected Result:** Bell counter decrements.
**Database Verification:** `notifications.read_at` timestamp populated.
**UI Verification:** Notification panel highlights unread correctly.
**Failure Cases:** Dispatched to archived employee fails gracefully.
