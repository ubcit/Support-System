# 11. Notifications

The platform relies on a robust notification engine to ensure critical updates are never missed. Notifications are dispatched across three primary channels.

## 1. In-App Notifications
This is the most common notification type.
- **Where:** The Bell Icon in the top right corner of the navigation bar.
- **When:** You are assigned a task, mentioned in a comment, or a task you are watching changes status.
- **Action:** Clicking the notification usually routes you directly to the relevant Task or Conversation.

## 2. Email Notifications
For critical alerts that need to reach you when you are not logged into the system.
- **Where:** Delivered to the email address associated with your Profile.
- **When:** Major system events, daily digest reports, or urgent task assignments.
- You can turn off Email notifications in your Personal Profile settings.

## 3. WhatsApp Notifications
The platform can communicate directly with customers via WhatsApp.
- **Where:** The customer's mobile device.
- **When:** An agent replies to their ticket via the Conversation Center.
- **Note:** The system does not currently text Employees on their personal WhatsApp numbers; this channel is strictly for Customer CRM communication.

## Failures & Retries
Sometimes, third-party services (like Meta's WhatsApp API or an SMTP Email server) experience downtime.
- The platform uses a resilient **Queue System**.
- If a WhatsApp message fails to send due to a network timeout, the system does not give up.
- It will automatically **Retry 3 times** behind the scenes, waiting a few moments between each attempt.

## Notification History
If you accidentally dismiss a notification, you can view your history.
- Open the Notification Drawer (Bell Icon).
- Even after marking an alert as "read", it remains in your history log for a set period before being permanently archived.

> [!WARNING]
> If you are not receiving Emails, check your Spam folder. If they are completely missing, notify your Administrator to verify the SMTP Server Health in the System Dashboard.
