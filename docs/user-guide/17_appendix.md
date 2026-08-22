# 17. Appendix

This appendix provides visual diagrams mapping the core workflows and relationships within THE-SPACE-MANAGEMENT.

## 1. Core Workflow Pipeline

```mermaid
flowchart TD
    A[Customer WhatsApp] -->|Webhook| B(Message Pipeline)
    B --> C{AI Copilot}
    C -->|Reads Context| D[Drafts Task & Summary]
    D --> E{Boss Triage}
    E -->|Approves/Edits| F[Task Created]
    F -->|Assigned To| G(Employee Dashboard)
    G -->|Executes Work| H[Task Done]
    H --> I[Boss Metrics Updated]
```

## 2. Task Lifecycle (Kanban)

```mermaid
stateDiagram-v2
    [*] --> ToDo: Created
    ToDo --> InProgress: Employee starts work
    InProgress --> ToDo: Work blocked/reverted
    InProgress --> UnderReview: Requires Boss Approval (Optional)
    UnderReview --> InProgress: Rejected
    UnderReview --> Done: Approved
    InProgress --> Done: Finished
    Done --> [*]: Archived (After 30 days)
```

## 3. Database High-Level Relationships

```mermaid
erDiagram
    WORKSPACE ||--o{ PROJECT : contains
    WORKSPACE ||--o{ EMPLOYEE : employs
    CUSTOMER ||--o{ PROJECT : requests
    CUSTOMER ||--o{ CONVERSATION : has
    CONVERSATION ||--o{ MESSAGE : contains
    PROJECT ||--o{ TASK : contains
    TASK ||--o{ TASK_LOG : tracks
    EMPLOYEE ||--o{ TASK : assigned_to
```

## 4. AI Prompting Architecture

```mermaid
sequenceDiagram
    participant Cust as Customer
    participant Sys as System
    participant LLM as AI Provider
    participant Boss as Manager
    
    Cust->>Sys: Sends WhatsApp Msg
    Sys->>LLM: Transmit recent chat history + System Prompt
    LLM-->>Sys: Return JSON (Intent, Summary, Task Draft)
    Sys->>Boss: Display AI Suggestion in UI
    Boss->>Sys: Approves Suggestion
    Sys->>Sys: Discards AI Draft, Converts to Real Task
```

## 5. Notification Flow

```mermaid
flowchart LR
    A[Event Triggered] --> B{Determine Channel}
    B -->|In-App| C[Update Bell Icon UI]
    B -->|Email| D[Dispatch SMTP Job]
    B -->|WhatsApp| E[Dispatch Meta API Job]
    E --> F{Success?}
    F -->|Yes| G[Mark Delivered]
    F -->|No| H[Retry Queue x3]
    H -->|Fails 3x| I[Mark Failed]
```
