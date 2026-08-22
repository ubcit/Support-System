# End-to-End Manual Testing Workflow Guide (Fresh Slate)

Welcome to the clean slate! You have executed `php artisan migrate:fresh --seed` with only **User and Employee Seeders** enabled. 
There are **no existing Workspaces, Workflows, Projects, Tasks, or Conversations**.

This comprehensive guide takes you step-by-step through testing **every single page** in the application in logical operational order, from initial onboarding to executive reporting.

---

## 🔑 Login Credentials

- **Boss / Executive Account:** `boss@thespace.app` / `password123`
- **Developer Account:** `ahmed@thespace.app` / `password123`
- **QA Account:** `sara@thespace.app` / `password123`
- **Support Account:** `ali@thespace.app` / `password123`

---

## ⚡ Background Prerequisites

**WhatsApp Cloud API (real inbound messages):** follow [`docs/WHATSAPP_WEBHOOK_SETUP.md`](WHATSAPP_WEBHOOK_SETUP.md) before Phase messaging tests (Meta webhook, tunnel, queue, customer phone match).

Ensure your background workers and frontend dev server are running:

1. **Queue Listener:**
   ```bash
   php artisan queue:listen
   ```
2. **Vite Server:**
   ```bash
   npm run dev
   ```

---

## 📍 Phase 1: Workspace & Team Setup (Admin Panel: `/admin`)

### Page 1: Workspace Onboarding Wizard
**URL:** `http://127.0.0.1:8000/admin/workspace-onboarding`  
*Log in as Boss (`boss@thespace.app`).*

1. Navigate to **Workspace Onboarding Wizard** in the navigation menu.
2. Fill out the 10 multi-step wizard sections:
   - **Create Workspace:** Name: `Space Operations`, Timezone: `Asia/Baghdad`.
   - **Team Setup:** Enter your phone number (`+9647700000000`).
   - **Projects:** First Project Name: `Clinic ERP System`.
   - **Communication Channels:** Select WhatsApp Cloud API (optional tokens for simulator testing).
   - **AI Configuration:** Select Provider `OpenAI (GPT-4o)`, check **Enable AI Analysis**.
   - **Task Provider:** Select `Native Work Platform`.
   - **Workflow Selection:** Select `IT Support` or `Software Development`.
   - **Automation:** Check Auto-assign, Notify customer, Escalate overdue.
   - **Feature Flags:** Enable Advanced Analytics.
   - **Validation & Go Live:** Review checklist and click **"Go Live & Finalize Workspace"**.
3. **Verification:** Confirm success notification banner and redirection to Operations Dashboard.

### Page 2: Workspace Settings & Boss Workspace Page
**URL:** `http://127.0.0.1:8000/admin/workspace-settings-page`  
**URL:** `http://127.0.0.1:8000/admin/boss-workspace-page`

1. Navigate to **Workspace Settings**.
2. Verify that workspace metadata (`Space Operations`), time zone, and active configuration match what you onboarded.
3. Switch workspace context or inspect the active header indicator to ensure tenant isolation is bound.

### Page 3: Employee Management Page
**URL:** `http://127.0.0.1:8000/admin/employee-management-page`

1. Navigate to **Employee Management**.
2. Verify all seeded employees appear in the directory: `Yousif`, `Ahmed`, `Sara`, `Ali`.
3. Click to edit `Ahmed` (Backend Engineer): add skills (e.g., `Laravel`, `API`), verify availability toggle is `Active`.
4. Click to edit `Sara` (QA Lead): set max workload capacity.

### Page 4: Customer CRM Page
**URL:** `http://127.0.0.1:8000/admin/customer-c-r-m-page`

1. Navigate to **Customer CRM**.
2. Confirm the auto-created internal customer (`Internal (Space Operations)`) is listed.
3. Click **Create Customer** (or New Customer modal):
   - **Name:** `Al-Mansour Clinic`
   - **Email:** `contact@almansour-clinic.com`
   - **Phone:** `+9647701234567`
4. Save and verify the customer profile page displays contact info, metrics, and zero tickets initially.

### Page 5: Project Hub Page
**URL:** `http://127.0.0.1:8000/admin/project-hub-page`

1. Navigate to **Project Hub**.
2. Confirm `Clinic ERP System` project exists under `Al-Mansour Clinic` / internal customer.
3. Click **Create Project**:
   - **Name:** `Mobile Patient App`
   - **Category:** Mobile Development
   - **Customer:** `Al-Mansour Clinic`
4. Assign team members (`Ahmed` and `Sara`) to `Clinic ERP System`.
5. Verify project cards update with health indicators and member avatars.

---

## 📥 Phase 2: Ingestion & Copilot Triage

### Page 6: Message Simulator
**URL:** `http://127.0.0.1:8000/admin/message-simulator`

1. Navigate to **Message Simulator**.
2. Select Customer: `Al-Mansour Clinic` (`+9647701234567`).
3. Enter Message Content:
   > *"Emergency: The patient appointment portal is giving a 500 error when patients try to book online!"*
4. Click **Inject Simulated Message into Pipeline**.
5. Check your `php artisan queue:listen` terminal tab:
   - Confirm `ProcessIncomingMessage` processed.
   - Confirm `AnalyzeMessageThread` processed.

### Page 7: Conversation Center
**URL:** `http://127.0.0.1:8000/admin/conversation-center`

The live WhatsApp inbox is **Conversation Center** only (`/admin/conversation-explorer` redirects here).

1. Navigate to **Conversation Center**.
2. Select the incoming thread.
3. Inspect the **AI Copilot Sidebar**:
   - If the queue has not run yet, status is **Pending** (not a fake Clinic ERP summary).
   - After `AnalyzeMessageThread` / buffered AI finishes, summary, title, and matched project/employee come from `conversation.metadata.ai_analyses`.
4. Click **Approve & Create Task** when analysis is ready (or fill the form manually).

---

## 📋 Phase 3: Task Management & Employee Execution

### Page 9 & 10: Task Dashboard & Kanban Board
**URL:** `http://127.0.0.1:8000/admin/task-dashboard-page`  
**URL:** `http://127.0.0.1:8000/admin/kanban-board-page`

1. Open **Task Dashboard** or **Kanban Board**.
2. Locate the newly created task (*"Patient booking portal returning 500 error"*).
3. Click task to edit:
   - Assignee: `Ahmed`
   - Project: `Clinic ERP System`
   - Priority: `Urgent`
   - Status: `To Do`
4. Verify task appears under the `To Do` column on the Kanban board.

### Page 11: Employee Panel & Workspace Execution
**URL:** `http://127.0.0.1:8000/workspace`

1. Open a new Incognito tab or log out of `/admin`.
2. Log in as **Employee Ahmed** (`ahmed@thespace.app` / `password123`).
3. Access **Employee Dashboard** (`/workspace/employee-dashboard`).
4. **Action 1 (Status Change):** Drag the task from `To Do` to `In Progress`.
5. **Action 2 (Internal Communication):** Click the task, scroll to Internal Notes, add:
   > *"Investigating server logs. Database connection pool was exhausted during peak load."*
6. **Action 3 (Attachments):** Click Attachments tab, upload a sample screenshot or log file.
7. **Action 4 (Completion):** Drag task to `Done`.

---

## ⚙️ Phase 4: Automation & AI Engine Testing

### Page 12: Rules Center Page
**URL:** `http://127.0.0.1:8000/admin/rules-center-page`  
*Log back in as Boss (`boss@thespace.app`).*

1. Navigate to **Rules Center**.
2. Review active automation rules:
   - Auto-assignment based on employee skill tags.
   - Escalation trigger on SLA breaches.
3. Test toggling rule states or testing a rule dry-run.

### Page 13 to 17: AI Center & AI Resources
**URL:** `http://127.0.0.1:8000/admin/a-i-center-page`  
- `http://127.0.0.1:8000/admin/ai-models` (AI Model Resource)
- `http://127.0.0.1:8000/admin/ai-prompts` (AI Prompt Resource)
- `http://127.0.0.1:8000/admin/ai-schemas` (AI Schema Resource)
- `http://127.0.0.1:8000/admin/ai-request-logs` (AI Request Log Resource)

1. Open **AI Center Page**: Check operational status widgets, provider health, token usage summary.
2. Open **AI Models**: Verify configured model (`gpt-4o`) and shadow mode status.
3. Open **AI Prompts**: Inspect triage prompt templates and systemic instructions.
4. Open **AI Schemas**: Inspect JSON output contracts for message analysis.
5. Open **AI Request Logs**: Confirm log entries for recent simulation copilot runs with latency and token metrics.

### Page 18: Prompt Playground
**URL:** `http://127.0.0.1:8000/admin/prompt-playground`

1. Select prompt template: `Triage Analysis Prompt`.
2. Input sample variable values: `message = "Server disk space is 99% full"`.
3. Click **Execute Prompt**.
4. Verify real-time response generation and structured JSON output parsing.

### Page 19 & 20: Benchmark Dashboard & Certification Tests
**URL:** `http://127.0.0.1:8000/admin/benchmark-dashboard`  
**URL:** `http://127.0.0.1:8000/admin/certification-tests`

1. Open **Benchmark Dashboard**: Inspect system AI evaluation metric cards.
2. Open **Certification Tests**: Run or view certification test assertions and assertion pass rates.

---

## 📊 Phase 5: Audit, Analytics & Operations Dashboards

### Page 21: Pipeline Logs
**URL:** `http://127.0.0.1:8000/admin/pipeline-log-resources`

1. Navigate to **Pipeline Logs**.
2. Filter by trace ID or recent timestamp.
3. Verify step-by-step logs: `Inbound Webhook` → `Normalization` → `AI Analysis` → `Task Generation`.

### Page 22: Global Timeline
**URL:** `http://127.0.0.1:8000/admin/global-timeline`

1. Navigate to **Global Timeline**.
2. Verify chronological sequence of events (Workspace created, Customer created, Message ingested, Task created, Status moved to Done).

### Page 23: Reports Hub Page
**URL:** `http://127.0.0.1:8000/admin/reports-hub-page`

1. Navigate to **Reports Hub**.
2. Check analytical charts: Ticket resolution throughput, Customer satisfaction, Employee workload distribution.

### Page 24 & 25: Operations Dashboard & Boss Dashboard
**URL:** `http://127.0.0.1:8000/admin/operations-dashboard`  
**URL:** `http://127.0.0.1:8000/admin/boss-dashboard-page`

1. Navigate to **Operations Dashboard**.
2. Verify live dynamic metrics:
   - **Active Projects:** 2
   - **Total Completed Tasks:** 1
   - **Resolution Rate:** 100%
   - **Queue Status:** Green / Healthy
3. Navigate to **Boss Dashboard** for executive high-level summary.

---

> 🎉 **Validation Standard:** If all 25 pages/views pass without exceptions or 500 errors, your multi-tenant system pipeline, UI state machine, AI engine, and workspace isolation are 100% verified!
