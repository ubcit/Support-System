# 10. Reports & Analytics

The Reports Hub is the command center for data-driven management. It aggregates tasks, customers, employees, and AI usage into one page at **Reports & Analytics**.

Use the period pills (**Last 7 days**, **Last 30 days**, **This month**) to slice every tab. Date ranges apply to completions, conversations, and AI spend. Backlog figures such as overdue and unassigned are current snapshots.

## Tabs

### Overview
Workspace throughput plus live AI budget health:
- **Completion Rate** of tasks created in the selected period
- **Average Resolution Time** (`created_at` → `completed_at`)
- Total tasks and active projects
- **AI Spend Today**, customers who already hit their daily cap, and sessions waiting for manual review
- **7-day completion velocity** bar chart

### Tasks
Period completion, overdue and unassigned backlog, average resolution, velocity, and breakdowns by priority and project.

### Employees
One row per person: active assignments, completions in the period, overdue work, load vs `max_workload`, and hours from time logs.

### Customers
Per account: conversations, sessions, needs-review count, tasks on their projects, period AI spend, and whether today’s daily budget is exhausted.

### AI Cost
Estimated LLM spend from `ai_request_logs` (token-based, not a provider invoice):
- Period spend, tokens, and request count
- Top **customers** and **projects** by spend
- Costliest **sessions**, with a link into Conversation Center

Set caps from **Manage daily limits**.

## Customer daily AI budgets

Each customer can have a **daily AI cost limit** (USD). Blank means unlimited, unless a **workspace default** is set under Workspace Settings or Customer AI Limits.

When the remaining daily balance is empty:

1. New WhatsApp messages from that customer are **not** sent to the LLM.
2. The collecting session is marked **Needs review** with the title `Manual review (daily AI budget)`.
3. Staff handle the thread in Conversation Center as usual.
4. The next calendar day AI turns back on automatically. Boss WhatsApp commands bypass the customer cap.

A limit of `0` always skips AI for that customer.

## Using Charts
Hover a velocity bar to see the exact completions for that day.

> [!TIP]
> Do not use these metrics solely to punish employees. A slow average turnaround might mean the team is understaffed, or that tasks wait on third-party vendors. Look at the data contextually.
