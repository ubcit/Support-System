# WhatsApp Cloud API → Webhook Setup

Configure Meta WhatsApp so customer text, documents, and voice notes land in this app at `/api/v1/webhooks/whatsapp`.

Credentials are **server `.env` only**. Workspace Settings / Onboarding WhatsApp fields do **not** persist access tokens or phone number IDs.

## Flow

```
Customer → Meta WhatsApp Cloud API
        → GET/POST {APP_URL}/api/v1/webhooks/whatsapp
        → ProcessIncomingMessage (queue)
            ├─ text (known customer) → buffer → AI → tasks/issues
            ├─ image/audio/document/video → DownloadAttachment → ProcessAttachment
            │                              → AnalyzeMessageThread → issues/tasks
            └─ unknown phone → draft customer + “Unknown Number” issue (no media/AI)
```

## 1. Meta Developer app

1. Open [Meta for Developers](https://developers.facebook.com/) → create or select an app → add the **WhatsApp** product.
2. **WhatsApp → API Setup** — copy:
   - **Phone number ID** → `WHATSAPP_PHONE_NUMBER_ID`
   - **Temporary or permanent access token** → `WHATSAPP_ACCESS_TOKEN`
3. **App settings → Basic** — copy **App secret** → `WHATSAPP_APP_SECRET`  
   (Required in production. If empty, signature verification fails open and only logs a warning.)
4. Invent a verify string (example: `space-verify-2026`) → `WHATSAPP_VERIFY_TOKEN`.

## 2. `.env` keys

Names must match [`config/services.php`](../config/services.php) exactly (see [`.env.example`](../.env.example)):

```env
WHATSAPP_VERSION=v19.0
WHATSAPP_PHONE_NUMBER_ID=
WHATSAPP_ACCESS_TOKEN=
WHATSAPP_VERIFY_TOKEN=space-verify-2026
WHATSAPP_APP_SECRET=

# Media transcription / AI triage
OPENAI_API_KEY=
OPENAI_BASE_URL=https://api.openai.com/v1
AI_MODEL=gpt-4o

# Local/dev without Redis: database. Production (Google VM / VPS): redis
QUEUE_CONNECTION=database
# QUEUE_CONNECTION=redis
APP_URL=https://YOUR-PUBLIC-HOST
```

Do **not** use quotes unless the value contains spaces or `#`.  
Do **not** use the obsolete name `WHATSAPP_API_TOKEN` — the app reads `WHATSAPP_ACCESS_TOKEN`.

On production, set `QUEUE_CONNECTION=redis` (and run Supervisor workers). Webhooks only dispatch jobs; without a worker they will not process. See [`deployment/11_google_cloud_vm_checklist.md`](deployment/11_google_cloud_vm_checklist.md).

After editing:

```bash
php artisan config:clear
```

## 3. Public HTTPS URL (required for Meta)

Meta cannot call `http://localhost`. Options:

| Environment | What to do |
|-------------|------------|
| Local | Prefer **Cloudflare Tunnel** (ngrok may block some regions/IPs with `ERR_NGROK_9040`): `cloudflared tunnel --url http://127.0.0.1:8000` → set `APP_URL` to the printed `https://….trycloudflare.com` URL. Fallback: `lt --port 8000` (localtunnel; less reliable for Meta). |
| Staging / production | Set `APP_URL` to your real HTTPS domain |

Webhook callback URL (exact):

```text
https://YOUR-PUBLIC-HOST/api/v1/webhooks/whatsapp
```

Controller: [`WebhookController::handleWhatsApp`](../app/Modules/Communication/Controllers/WebhookController.php)  
Route: `GET|POST` under `routes/api.php` → `/api/v1/webhooks/whatsapp`.

### Meta webhook configuration

1. **Callback URL** = URL above  
2. **Verify token** = exact value of `WHATSAPP_VERIFY_TOKEN`  
3. Subscribe to field: **`messages`**  
4. Click **Verify and save**

GET handshake: Meta sends `hub_verify_token` + `hub_challenge`. The app echoes `hub_challenge` as `text/plain` when the token matches.

### Quick local verify (optional)

With the app running and env loaded:

```bash
curl -s "http://127.0.0.1:8000/api/v1/webhooks/whatsapp?hub_verify_token=space-verify-2026&hub_challenge=test123"
# expect: test123
```

Through the tunnel, use the same path on the public host.

## 4. Runtime processes

```bash
php artisan serve            # or your usual host (XAMPP / Nginx)
php artisan queue:listen     # required — inbound processing is queued
npm run dev                  # for UI walkthrough
```

Failed jobs: `php artisan queue:failed` / `php artisan queue:retry all`.

## 5. Customer phone matching

1. Optional clean slate: `php artisan migrate:fresh --seed`
2. Login Boss: `boss@thespace.app` / `password123`
3. Create a **Customer** whose phone / `whatsapp_id` matches the sender’s WhatsApp number (same digits Meta sends, typically country code without `+` or as your CRM stores E.164).

| Sender | Behavior |
|--------|----------|
| **Known customer** | Text + media download + AI path |
| **Unknown number** | Auto customer + draft “Unknown Number” issue; **media download and AI are skipped** |

The live inbox is **Conversation Center** (`/admin/conversation-center`) only.

## 6. Smoke checklist

| # | Test | Expect |
|---|------|--------|
| 1 | Meta **Verify and save** | Success |
| 2 | Customer sends a **text** problem | Message in **Conversation Center** (`/admin/conversation-center`); after buffer/AI → issue/tasks |
| 3 | **Voice** note | Attachment downloaded; Whisper (or provider) → `ai_transcript` |
| 4 | **Document** / PDF | File stored; no OCR yet — AI mostly sees caption or “Media Received (document)” |
| 5 | **Image** | Vision description into transcript path |

### Without Meta (local only)

- UI: `/admin/message-simulator`
- CLI: `php artisan simulate:whatsapp "your text" --phone=2010XXXXXXXX`

Simulator / CLI are text-oriented; they do not fully replace Meta media payloads.

## 7. Known limitations

- Documents: download only (no OCR).
- Voice/image transcripts may not always be passed into the AI analysis prompt.
- Onboarding WhatsApp token fields are decorative (not written to env).
- Signature check skipped if `WHATSAPP_APP_SECRET` is empty.
- Multi-tenant: webhook cooldown uses the first workspace; no phone-number-ID → workspace routing yet.

## 8. Troubleshooting

| Symptom | Check |
|---------|--------|
| Meta verify fails | `WHATSAPP_VERIFY_TOKEN` matches Meta; `config:clear`; URL path includes `/api/v1/webhooks/whatsapp` |
| Verify OK, no messages | Queue worker running; subscribed to `messages`; `storage/logs/laravel.log` |
| 401 on POST | `WHATSAPP_APP_SECRET` must match Meta App Secret; `X-Hub-Signature-256` present |
| Media never appears | Customer must be known; `OPENAI_API_KEY` for transcription; queue not stuck |
| Outbound reply fails | Token + `WHATSAPP_PHONE_NUMBER_ID`; 24-hour WhatsApp session window |

## Related

- Clean-data UI walkthrough order: [`E2E_MANUAL_TESTING_GUIDE.md`](E2E_MANUAL_TESTING_GUIDE.md) (prefer Livewire `/admin/...` routes)
- Queue notes: [`deployment/05_queue_and_retry_policy.md`](deployment/05_queue_and_retry_policy.md)
- Env template: [`.env.example`](../.env.example)
