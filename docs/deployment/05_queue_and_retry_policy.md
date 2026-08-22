# Queue and Retry Policy

This document details the queuing architecture, timeout policies, and failure handling mechanisms for **THE-SPACE-MANAGEMENT**.

## Queue Architecture

The production environment leverages **Redis** as the queue driver (`QUEUE_CONNECTION=redis`) for ultra-fast task dispatching and memory efficiency.

We operate two distinct queue partitions via Supervisor:
1. **Default Queue (`default`)**: Handles critical business operations (WhatsApp webhooks, AI requests, ClickUp Sync). 
   - Workers: 4 processes
   - Max Tries: 3
2. **Shadow Queue (`shadow`)**: Handles asynchronous non-blocking Shadow Mode AI evaluation.
   - Workers: 2 processes
   - Max Tries: 1 (Fails fast to avoid blocking system resources).

## Retry Policies & Failure Handling

### 1. Job Retries
- Standard jobs are configured to retry **up to 3 times** before failing permanently.
- The `retry_after` config in `config/queue.php` must be set higher than the longest-running job (e.g., `90` seconds).

### 2. Dead-Letter Handling (Failed Jobs)
- When a job exceeds its retry limit, it is moved to the `failed_jobs` database table.
- A scheduled command alerts administrators when failed jobs accumulate.
- To retry a failed job manually:
  ```bash
  php artisan queue:retry <id>
  ```

### 3. AI Timeout Handling
- **OpenAI/Provider Timeout:** The `AIManager` enforces a strict 30-second HTTP timeout.
- If the AI times out, the pipeline catches the exception, marks the AI stage as `failed`, logs the duration, and allows the Job to be retried automatically by the queue worker.

### 4. WhatsApp Webhook Retry Handling
- WhatsApp expects a `200 OK` response within seconds. 
- The incoming webhook controller immediately dispatches `ProcessIncomingMessage` to the queue and returns `200 OK` to prevent WhatsApp from retrying the delivery excessively.

### 5. ClickUp/Sync Rate Limiting & Retries
- API calls to ClickUp are wrapped in retry mechanisms using Laravel's `Http::retry()`.
- Rate limiting (`429 Too Many Requests`) is caught, and the job is released back onto the queue with an exponential backoff delay.
