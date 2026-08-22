# User Acceptance Checklist

Use this checklist during manual User Acceptance Testing (UAT). Each workflow must be verified and checked off before production deployment.

## Administrator Workflow
- [ ] Employee Creation validates unique email and sends password-reset invite.
- [ ] System prevents offboarding an Employee with open tasks (error message shown).
- [ ] Admin permissions restrict destructive actions properly (403 on unauthorized mutation).
- [ ] Workspace Settings saves all fields and syncs role permissions.

## Boss Workflow
- [ ] Can create new Projects and assign existing Employees.
- [ ] Dashboards render live metrics correctly without static delays.
- [ ] Can view Reports & Analytics natively.
- [ ] Operations Dashboard loads without errors.

## Employee Workflow
- [ ] Employee Dashboard restricts view strictly to assigned tasks.
- [ ] Kanban Board correctly prevents WIP limit violations.
- [ ] Employee can change task status, simulating completion.
- [ ] Comments on Tasks successfully trigger activity logs.
- [ ] File Manager uploads attach to the current user's workspace.

## Customer CRM
- [ ] Customer profile metadata (AI Summaries) populates correctly.
- [ ] Linked tasks render directly inside the Customer CRM.
- [ ] Global search resolves customer phone numbers accurately.
- [ ] Create / Edit / Delete customer requires `customers.manage` permission.

## Issues Hub
- [ ] Issues list page loads at `/admin/issues`.
- [ ] Search and status filter work correctly.

## Automation & AI
- [ ] Inbound WhatsApp message successfully triggers AI Pipeline.
- [ ] AI creates an Issue and extracts metadata.
- [ ] `SendOutboundMessage` queue properly retries on simulated failure.

## System Global
- [ ] Notification badges accurately increment/decrement upon reading.
- [ ] Error pages (403, 404, 419, 500) display branded layout.
- [ ] Global Command Palette searches across all database tables.
- [ ] Onboarding wizard completes all 8 steps and creates workspace.

> **Sign-Off:**
> 
> Signature: _______________________ Date: ___________
