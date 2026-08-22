# Pilot Test Scenarios (Internal Alpha)

During the Internal Alpha phase, the platform will be exposed to a controlled subset of real users. These scenarios represent the specific workflows they will execute.

## Objective
Verify the real-world stability of the platform under normal and edge-case operating conditions.

## 1. Scenario: Real-time Customer Triage
**Participant:** Frontline Agent (Employee)
**Task:** Monitor the Conversation Center for exactly 1 hour.
**Rules:**
- All incoming WhatsApp messages must be replied to within 5 minutes.
- If a message indicates a server issue, the agent must manually convert it to a Task.
- Check the AI Copilot to see if it correctly guessed the user's intent.
**Success Criteria:** Zero dropped WhatsApp webhooks. AI correctly identifies at least 80% of technical intents.

## 2. Scenario: The "Monday Morning" Bulk Load
**Participant:** Operations Manager (Boss)
**Task:** Create 1 new Project, assign 3 Employees, and bulk-generate 20 Tasks inside the Kanban Board.
**Rules:**
- Assign all tasks immediately.
- Trigger WIP limit by dropping 6 tasks into a "Doing" column that has a limit of 5.
**Success Criteria:** The Kanban engine explicitly rejects the 6th task drop and throws a visible warning.

## 3. Scenario: Field Technician Simulation
**Participant:** Technician (Employee)
**Task:** Execute tasks entirely from a mobile device (iOS/Android browser).
**Rules:**
- Navigate to the Employee Dashboard using Safari/Chrome on mobile.
- Mark 3 tasks as "In Progress" then "Done".
- Add an attachment to a completed task via mobile photo upload.
**Success Criteria:** Mobile UI (responsive Tailwind layout) does not break, modals render fully within viewport, and camera uploads succeed.

## 4. Scenario: System Recovery Test
**Participant:** SysAdmin (Admin)
**Task:** Observe the queue system behavior under failure.
**Rules:**
- Temporarily disable outbound internet access on the WhatsApp API worker.
- Have a user attempt to send 5 outbound messages.
- Wait 5 minutes. Re-enable internet.
**Success Criteria:** `SendOutboundMessage` job logs retries. Once connectivity is restored, all 5 messages process from the queue successfully without duplication.
