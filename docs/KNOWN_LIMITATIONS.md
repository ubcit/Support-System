# Known Limitations

This document tracks known limitations and constraints of the platform as we enter the Internal Alpha Validation Phase. These limitations are acknowledged and accepted for this release cycle, to be addressed in future phases.

## 1. Third-Party Integrations
- **WhatsApp Cloud API Rate Limits:** The system currently relies on Meta's WhatsApp Cloud API tier 1 limits. Sustained bursts exceeding 250 outbound template messages per second may result in provider throttling.
- **AI Processing Latency:** The AI Copilot currently processes payloads synchronously or via immediate queue. Complex intent extraction on large conversation histories can take up to 8 seconds, delaying the webhook response slightly.

## 2. Platform Features
- **File Upload Limits:** Attachment size in the Conversation Center is strictly capped at 20MB. Videos exceeding this will fail gracefully.
- **Multi-Tenant Data Isolation:** While RBAC and Workspace models are present, full database-level row-security (RLS) is not yet active. Data isolation relies on Eloquent Global Scopes.
- **Reporting Historical Limits:** The 7-Day Velocity Chart on the Executive Dashboard only caches the last 7 days natively. Historical drill-down beyond 30 days is not currently indexed for fast retrieval.

## 3. UI/UX Constraints
- **Dark Mode Flash:** In certain older browsers, navigating between specific modules may cause a brief (50ms) flash of light mode before the local storage preference correctly applies.
- **Mobile Kanban Board:** Dragging and dropping Kanban cards on highly compact mobile screens (e.g., iPhone SE) can occasionally trigger the browser's native pull-to-refresh.

## 4. Operational Boundaries
- **Backup Window:** Automated database backups run via cron at 03:00 UTC. There is a potential 1-minute read lock during deep logical dumps.
- **Concurrency:** The system does not currently utilize optimistic locking on Kanban tasks. If two employees attempt to drag the same task at the exact same millisecond, the final write will overwrite without notifying the first employee.
