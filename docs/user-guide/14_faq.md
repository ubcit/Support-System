# 14. Frequently Asked Questions (FAQ)

## General & Access
**1. What is THE-SPACE-MANAGEMENT?**  
It is a unified Work OS that combines WhatsApp customer support, AI triage, and Kanban task management into one platform.

**2. I forgot my password. How do I reset it?**  
If automated resets are disabled, contact your SysAdmin. They can issue a temporary password via the Employee Management panel.

**3. Why can't I see the "Settings" tab?**  
You are likely logged in as an Employee or Boss. Only Administrators have access to global system settings.

**4. Can I use the platform on my mobile phone?**  
Yes, the interface is fully responsive. You can access your Dashboard and Kanban board via Safari or Chrome on your mobile device.

**5. Is there a native iOS or Android app?**  
No, it is a Progressive Web App (PWA). You can "Add to Home Screen" from your mobile browser for an app-like experience.

## Tasks & Kanban
**6. How do I assign a task to multiple people?**  
Currently, a single task is assigned to a single owner for clear accountability. Use subtasks or checklists if multiple people must contribute.

**7. I accidentally moved a task to "Done", can I move it back?**  
Yes, but you shouldn't. If the problem reappears, create a new task. Moving it back corrupts your completion velocity metrics.

**8. What does "WIP Limit Exceeded" mean?**  
Your manager has restricted how many tasks can be "In Progress" simultaneously to prevent burnout. Finish an active task before starting a new one.

**9. Can I delete a task?**  
No. You can only Archive it or move it to Done. This preserves historical data.

**10. How do I attach a file to a task?**  
Open the task details and click the "Upload" button or drag-and-drop the file into the attachments zone.

**11. What happens if I miss a task due date?**  
The task card will turn red, and it will be flagged as "Overdue" on the Boss's Project Dashboard.

**12. Can I create recurring tasks?**  
Yes. In the task creation menu, set the "Recurrence" interval (e.g., Weekly, Monthly).

**13. What is a "Boss Note"?**  
A private instruction left by your manager. The customer never sees this.

**14. How do I find an old task?**  
Use `CMD + K` to open the Global Search and type the task name or ID.

**15. Can I export my tasks to Excel?**  
Yes, Admins and Bosses can export table views to CSV/Excel from the Reports Hub.

## Projects & Workspaces
**16. What is the difference between a Workspace and a Project?**  
A Workspace is a broad department (e.g., "IT Support"). A Project is a specific initiative within that department (e.g., "Network Upgrade 2026").

**17. Why can't I assign an employee to my Project?**  
They must first belong to the same Workspace that the Project is created under.

**18. How is "Project Health" calculated?**  
It compares the ratio of completed tasks against overdue tasks and total duration remaining.

**19. What is a Sprint?**  
A defined timeframe (usually 2 weeks) where the team focuses solely on a specific batch of assigned tasks.

**20. Can a task exist without a project?**  
Technically yes, but it is highly discouraged as it ruins reporting metrics. Always attach tasks to Projects or Customers.

## Conversation Center (WhatsApp)
**21. Can I send a message first to a customer?**  
Yes, but WhatsApp enforces a 24-hour window. If 24 hours have passed since their last message, you must use a pre-approved Meta Template Message to initiate contact.

**22. Are my internal notes visible to the customer?**  
No. Internal Notes are strictly for your team. Only messages typed in the main reply box are sent to WhatsApp.

**23. Can the customer see when I am typing?**  
No, the WhatsApp Cloud API does not transmit typing indicators from our platform.

**24. Why did my message fail to send?**  
Check if the WhatsApp session expired, or if there is a temporary Meta API outage. The system will automatically retry 3 times.

**25. Can I send Voice Notes?**  
You can receive and play inbound voice notes, but outbound voice notes are not currently supported by the standard text editor.

**26. How do I handle an angry customer?**  
Read the AI summary to understand the context quickly, remain professional, and if needed, tag your Boss in an Internal Note to review the chat.

**27. Does the system support group chats?**  
No. The platform is designed for direct 1-on-1 business-to-customer communication via the WhatsApp Business API.

**28. How large can an attachment be?**  
WhatsApp limits media attachments to roughly 16MB. The platform strictly enforces a 20MB limit.

**29. Can I delete a message I sent by mistake?**  
No. Once it is sent to the WhatsApp API, it cannot be recalled.

**30. Why is a conversation marked "Archived"?**  
Conversations inactive for more than 14 days are auto-archived to keep your inbox clean. They will un-archive instantly if the customer messages again.

## Artificial Intelligence
**31. Does the AI reply to customers automatically?**  
Never. The AI only reads and summarizes internally. A human must always press "Send".

**32. The AI suggested the wrong task priority. Why?**  
The AI guesses based on language. If a customer says "It's slightly broken but I'm FURIOUS," the AI might mistake emotion for mechanical urgency. Always verify its suggestions.

**33. How long does the AI take to process a message?**  
Usually between 2 to 8 seconds depending on the length of the conversation history.

**34. Is our customer data being used to train public AI models?**  
No. We utilize Enterprise API agreements (Zero Data Retention policies) with OpenAI/Anthropic, meaning your data is not used for model training.

**35. Can I turn the AI off?**  
Yes. Bosses can ignore it, and Admins can disable the AI Pipeline webhook completely in Settings.

**36. What is "Shadow Mode"?**  
A testing phase where AI analyzes messages but hides its suggestions from users. It allows Admins to test accuracy safely.

**37. Why didn't the AI generate a summary for this chat?**  
If the customer only sent "Hello" or a thumbs-up emoji, the AI skips processing to save token costs.

**38. Can the AI translate languages?**  
Yes. The LLMs natively understand dozens of languages and will usually summarize them into your system's default language (English/Arabic).

## Metrics & Reports
**39. How is "Average Response Time" calculated?**  
The exact time from Task Creation (`created_at`) to Task Completion (`completed_at`).

**40. Why did our velocity chart drop to zero on Sunday?**  
Velocity only tracks *completed* tasks. If nobody clicks "Done" on Sunday, the velocity is zero, even if work was happening.

**41. Can I track how much time I spent actively working on a task?**  
Currently, the system tracks overall lifecycle duration, not stop-watch style active hours.

**42. Who can see my performance metrics?**  
You and your Boss. Employees cannot see each other's performance metrics.

**43. How often do the dashboards update?**  
The data is real-time. If a task is marked done, the dashboard reflects it on the next page load.

## System & Troubleshooting
**44. The system is very slow. What's wrong?**  
First, check your internet connection. If stable, the server's Redis Cache may be under heavy load. Contact your Admin.

**45. I keep getting logged out. Why?**  
For security, sessions expire after a set period of inactivity (typically 2-12 hours).

**46. How do I toggle Dark Mode?**  
Click the Sun/Moon icon in the top right navigation bar.

**47. What does "404 Not Found" mean?**  
You clicked a link to a Task or Project that has been permanently deleted or you do not have permission to view it.

**48. Are there keyboard shortcuts?**  
Yes! Press `CMD + K` (Mac) or `CTRL + K` (Windows) to open the Command Palette.

**49. Can I change my profile picture?**  
Yes, in your Personal Profile settings (bottom left corner).

**50. I found a bug. How do I report it?**  
Do not message the customer support number. Tell your direct Boss, who will log an internal Issue for the SysAdmin to resolve.
