# 09. AI Features

THE-SPACE-MANAGEMENT uses Artificial Intelligence not to replace human workers, but to augment their capabilities, acting as a tireless assistant that reads every message instantly.

## How AI Works
When a message arrives via WhatsApp, a background webhook triggers the AI pipeline. The AI uses a Large Language Model (LLM) to read the text, analyze the context, and output a structured JSON response containing summaries, urgencies, and task suggestions.

## What AI Does
- **Summarizes Context:** Turns a 15-message rant into a 2-sentence problem statement.
- **Extracts Intent:** Determines if the customer is reporting a bug, asking a billing question, or just saying "Thank you."
- **Drafts Tasks:** Auto-fills a proposed Task title, description, and suggests the correct priority.

## What AI Does NOT Do
- **It Does Not Auto-Assign:** The AI will draft a task, but a human Manager (Boss) must click "Approve" before an Employee is actually assigned.
- **It Does Not Auto-Reply:** The AI will never send an automated WhatsApp message to a customer posing as a human. All outbound communication must be triggered by an Employee.

## Confidence
When the AI makes a suggestion, it occasionally includes a "Confidence Score."
- If the confidence is low (e.g., the customer's message was extremely vague), the AI will flag the drafted task for mandatory human review.

## Shadow Mode
During initial rollout, Administrators can enable **Shadow Mode**.
- In Shadow Mode, the AI processes all messages and logs its suggestions, but *hides* them from the Boss's UI.
- This allows SysAdmins to review the AI's accuracy in the logs for a few days to ensure it behaves correctly before turning it on for the management team.

## Prompt Versions & Models
Administrators control the AI's behavior via **Prompts**.
- You can edit the System Prompt under settings to tell the AI exactly how to behave (e.g., "Always look for a 5-digit invoice number").
- The system supports different models (e.g., OpenAI GPT-4, Anthropic Claude). You can swap models in the `.env` settings depending on cost and performance needs.

## Costs
AI is not free. Every message processed consumes "tokens", which incur fractions of a cent in billing from your LLM provider (OpenAI/Anthropic). 
- To manage costs, the AI only processes the most recent unsummarized messages in a thread, rather than re-reading a 5-year history every time the customer says "Hello."

## How to Override AI
If the AI drafts a task that is entirely incorrect:
1. Ignore the AI suggestion button.
2. Click **Create Manual Task**.
3. Fill out the task details yourself. 

## How to Improve AI
If you notice the AI consistently categorizing "Network Outages" as "Hardware Failures":
- Contact your Administrator.
- They can adjust the System Prompt to give the AI explicit instructions on how to differentiate between those two categories.

[Screenshot: AI Copilot Interface in Conversation Center]
