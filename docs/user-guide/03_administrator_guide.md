# 03. Administrator Guide

As an Administrator (SysAdmin), you hold the keys to the platform. Your role is not to manage daily tasks, but to ensure the system is configured correctly, securely, and is running smoothly.

> [!WARNING]
> With great power comes great responsibility. Actions taken in the Administrator panels can alter the platform's behavior for all users. Proceed with caution.

## Workspace Settings
Workspaces divide your platform into logical business units. 
- Navigate to **Settings > Workspaces**.
- You can create multiple workspaces (e.g., "IT Support", "HR Management").
- Projects and Tasks belong to specific workspaces, allowing you to isolate data between departments.

## Company Information
Maintain your global company profile under **Settings > Company**.
- Upload your company logo (this will reflect in the top left corner of the sidebar).
- Set your primary timezone. (This is critical for accurate SLA and response time calculations).

## Employees, Roles & Permissions
You control who has access to the platform.
- **Adding Employees:** Navigate to **Employee Management**. Create a new user by providing their Name, Email, and Job Title.
- **Roles:** The system uses Role-Based Access Control (RBAC). 
  - `Admin`: Full system access.
  - `Boss`: Management access (Dashboards, Project creation, AI approval).
  - `Employee`: Execution access (Kanban board, specific task assignments).
- **Archiving:** You cannot hard-delete an employee to preserve historical task records. Instead, you **Archive** them, which revokes their login access immediately.

## Projects
While Bosses manage daily projects, Administrators define the global Project parameters.
- Ensure that Projects are mapped correctly to Customers in the CRM.

## AI Configuration
The platform's AI Copilot requires proper configuration.
- **API Keys:** Navigate to **Integrations > AI Settings**. Enter your OpenAI / Anthropic API keys securely.
- **Prompt Engineering:** You can adjust the "System Prompt" that dictates how the AI behaves. If you want the AI to be more formal with customers, or extract specific serial numbers, adjust the prompt here.

## WhatsApp Configuration
Credentials live in server `.env` (not a Settings UI screen today):

- Set `WHATSAPP_PHONE_NUMBER_ID`, `WHATSAPP_ACCESS_TOKEN`, `WHATSAPP_VERIFY_TOKEN`, and `WHATSAPP_APP_SECRET`.
- Point Meta’s webhook at `{APP_URL}/api/v1/webhooks/whatsapp` and subscribe to **messages**.
- Full steps, tunnel, smoke tests, and troubleshooting: [`docs/WHATSAPP_WEBHOOK_SETUP.md`](../WHATSAPP_WEBHOOK_SETUP.md).

If that connection breaks, incoming messages will fail to appear in the Conversation Center.

## Email Configuration
The system sends transactional emails (e.g., "You have been assigned a task").
- Configure your SMTP host, port, username, and password in the **Email Settings**.

## Workflow Configuration & Rules Engine
Workflows dictate the columns on the Kanban board.
- Navigate to **Workflows**.
- You can define custom states (e.g., `To Do`, `In Progress`, `Awaiting Parts`, `Done`).
- **WIP Limits (Work In Progress):** You can set a strict limit on specific columns. For example, setting a WIP limit of `3` on "In Progress" prevents a Boss from assigning a 4th simultaneous task to an Employee, preventing burnout.

## Feature Flags
Testing a new workflow? Use Feature Flags.
- Under **Settings > Feature Flags**, you can toggle specific experimental features on or off globally without requiring a developer to deploy code.

## Health Monitor & Logs
Your primary dashboard includes the **System Status Widget**.
- **Database Status:** Checks if the platform can read/write data.
- **Redis Cache:** Checks if the high-speed queue system is responsive.
- **Application Logs:** View real-time error logs to diagnose issues (e.g., "Failed to send WhatsApp message").

## Backups & System Maintenance
- Database backups run automatically, but you should regularly verify their integrity.
- If a background queue stalls (e.g., tasks are not being assigned by AI), you can monitor queue workers from the server metrics dashboard.

[Screenshot: Administrator Settings Panel]
