# 06. Conversation Center

The Conversation Center is the unified communications hub. It transforms chaotic WhatsApp threads into structured, actionable tickets.

## The Conversation List
The left side of the screen displays your active conversations.
- Conversations are sorted by the most recent message.
- Unread messages are bolded.
- A small badge indicates how many unread messages are in that thread.

## Filters & Search
You don't have to scroll endlessly to find a specific chat.
- **Search Bar:** Type a Customer's Name, Phone Number, or Email. The list updates instantly (debounced search).
- **Filters:** Toggle between `All`, `Unread`, `Active`, and `Archived` conversations.

## The Message Timeline
Clicking a conversation opens the Timeline in the center of the screen.
- **Green Bubbles:** Outbound messages sent by your team.
- **White Bubbles:** Inbound messages received from the customer.
- Every message includes an exact timestamp.
- The system automatically handles multi-line text and emojis.

## Media & Attachments
Customers frequently send images (e.g., screenshots of errors).
- The system intercepts WhatsApp media attachments securely.
- Inline buttons will appear reading **[View Attachment]**.
- Clicking this opens the image cleanly without downloading malicious files directly to your device.

## AI Summary
On the right side of the screen, you will find the **AI Copilot** panel.
- The AI automatically reads the entire conversation history.
- It generates a 2-3 sentence summary of the core issue.
- This saves you from reading 50 messages of "Hello, are you there?" to find the actual problem.

## Internal Notes
You can leave private notes on a conversation that the customer will never see.
- Useful for handing off a chat to another agent: *"Ahmed, this customer is very angry about a delayed shipment. Please handle."*

## Customer Profile
Also on the right panel is the **Customer Profile**.
- Displays the Customer's Name, Company, Phone Number, and VIP status.
- Shows a list of **Linked Tasks** currently active for this customer, preventing you from opening a duplicate task if one is already being worked on.

## Replying & History
To send a message back via WhatsApp:
1. Type your response in the bottom input bar.
2. Press **Enter** or click **Send**.
3. The message is instantly queued and dispatched via the WhatsApp Cloud API.
4. If a message fails to send (e.g., API timeout), the system will automatically retry 3 times behind the scenes.

> [!NOTE]
> All conversation history is retained indefinitely. You can always search for a customer 6 months later and see their complete interaction history.

[Screenshot: Conversation Center Interface]
