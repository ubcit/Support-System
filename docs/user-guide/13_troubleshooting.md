# 13. Troubleshooting

When things go wrong, do not panic. The platform is designed to be highly resilient, and most issues have simple resolutions. 

If you encounter an issue not listed here, contact your Administrator.

## Common Problems & Resolutions

### 1. Queues Stopped (No automated background tasks running)
**Symptoms:** 
- WhatsApp messages show as "Pending" but never arrive on the customer's phone.
- AI is not generating new Tasks or summaries.
- Emails are not sending.
**Root Cause:** The background worker processes (the "Queues") have stalled or crashed.
**Resolution (For Admins):**
- Navigate to the Server metrics dashboard.
- Verify that `php artisan horizon` or `supervisor` is actively running.
- Restart the queue workers. The system will automatically catch up on the backlog.

### 2. AI Unavailable or Failing
**Symptoms:**
- The Conversation Center shows a red error next to the AI Copilot: "Analysis Failed."
- No draft tasks are appearing for new customer complaints.
**Root Cause:**
- The API key for OpenAI/Anthropic is invalid or expired.
- The LLM Provider is experiencing a global outage.
- Your account has run out of prepaid API credits.
**Resolution:**
- Check the System Logs in the Admin Dashboard for "401 Unauthorized" or "429 Too Many Requests" from the AI provider.
- Update billing or API keys in the `.env` settings.
- **Fallback:** Bosses must manually read customer messages and create Tasks until AI service is restored.

### 3. WhatsApp Disconnected
**Symptoms:**
- Customers report sending messages, but they do not appear in the Conversation Center.
- Attempting to send a message throws an API Error.
**Root Cause:**
- The Meta Access Token has expired.
- The webhook endpoint configuration in the Meta Developer Portal is incorrect or failing SSL checks.
**Resolution:**
- Re-generate a permanent Access Token from Meta.
- Update the token in the platform's Integration settings.
- Verify the Webhook URL is returning a HTTP 200 status.

### 4. Missing Tasks
**Symptoms:**
- An Employee says, "I can't see the task you assigned to me!"
**Root Cause:**
- The Task is assigned to a Project that belongs to a Workspace the Employee does not have access to.
- The Boss accidentally archived the Task instead of moving it to "To Do".
**Resolution:**
- Use the Global Search to find the Task ID.
- Check the Project Members list and ensure the Employee is actively added to that specific Project.

### 5. Permissions Denied (403 Forbidden)
**Symptoms:**
- Clicking a button or opening a page results in a "You do not have permission to access this page" error.
**Root Cause:**
- You are logged in with the `Employee` role, but attempting to access a `Boss` or `Admin` restricted page (like Project Creation or Settings).
**Resolution:**
- This is intentional. If you believe you need management access, request a role upgrade from your Administrator.

### 6. Failed Notifications
**Symptoms:**
- In-app bell notifications appear, but Email alerts are completely silent.
**Root Cause:**
- SMTP Mail Server credentials are wrong.
- Emails are landing in the spam folder.
**Resolution:**
- Check your personal Profile to ensure Email alerts aren't toggled off.
- Have the Admin send a test email via the system settings to verify SMTP connectivity.

### 7. Slow Performance / Laggy UI
**Symptoms:**
- Pages take 5+ seconds to load. 
- The Kanban board stutters when dragging tasks.
**Root Cause:**
- High server load.
- The Redis Cache is offline, forcing the system to query the database directly for every request.
**Resolution:**
- Admins should check the System Health Widget to verify Redis is "Connected".
- Clear local browser cache and refresh.

> [!WARNING]
> Never attempt to fix database inconsistencies by manually editing SQL rows unless you are a qualified SysAdmin. Always use the UI interfaces to prevent breaking Eloquent relationships.
