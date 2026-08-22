# 16. Glossary

This glossary defines the specific terminology used within THE-SPACE-MANAGEMENT platform.

## A
- **AI Copilot:** The background artificial intelligence that reads inbound messages, summarizes intent, and drafts task suggestions.
- **Archive:** A soft-delete state. Archived records (Tasks, Employees, Conversations) are hidden from active views but retained in the database for historical reporting.

## B
- **Boss:** A management role in the platform. Bosses triage AI suggestions, create projects, and assign work, but rarely execute the tasks themselves.
- **Boss Note:** A private, internal instruction field on a Task visible only to internal team members, not the customer.

## C
- **Command Palette:** A keyboard-driven global search and navigation tool opened via `CMD + K` or `CTRL + K`.
- **Conversation Center:** The unified inbox where all inbound and outbound WhatsApp communications are managed.

## D
- **Dark Mode:** A UI setting that flips the application background to dark gray/black to reduce eye strain.

## E
- **Employee:** An execution role in the platform. Employees view their assigned tasks on a personal Kanban board and execute the work.

## F
- **Feature Flag:** A system toggle used by Administrators to enable or disable new, experimental features globally without altering code.

## K
- **Kanban:** A visual workflow management method. Tasks are represented as cards and moved across columns (states) from left to right.
- **KPI (Key Performance Indicator):** A quantifiable measure of performance, such as "Average Response Time".

## M
- **Milestone:** A significant checkpoint within a Project. Milestones group related Tasks together.

## P
- **Project:** A temporary endeavor with a defined goal. Projects contain Tasks and belong to a Workspace.

## Q
- **Queue:** A background worker system that processes heavy tasks (like sending emails, hitting the WhatsApp API, or querying the AI) without freezing your web browser.

## R
- **RBAC (Role-Based Access Control):** The security architecture that ensures an Employee cannot access Administrator settings.

## S
- **Shadow Mode:** An AI testing phase where the AI processes messages and logs results, but does not display suggestions to the end-user.
- **Sprint:** A time-boxed period (e.g., 2 weeks) during which a specific subset of project tasks must be completed.

## T
- **Task:** The fundamental unit of work. It has an assignee, a status, a priority, and a due date.
- **Timeline:** The chronological view of a conversation history or the Gantt-chart view of overlapping task durations.

## U
- **User:** Anyone with a login to the system (Admin, Boss, or Employee). Customer contacts are *not* Users.

## W
- **Webhook:** An automated data payload sent from an external service (like Meta/WhatsApp) to our platform the millisecond a customer sends a message.
- **WIP Limit (Work In Progress Limit):** A strict cap on the number of tasks allowed in a specific Kanban column (e.g., only 3 tasks allowed "In Progress" per employee).
- **Workspace:** The highest level of data isolation. Represents a specific company branch or department (e.g., "IT Operations").
