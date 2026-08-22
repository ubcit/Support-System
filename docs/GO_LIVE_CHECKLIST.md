# Go-Live Checklist (Production)

This checklist enforces the operational requirements that must be verified before the system is opened to production users.

## 1. Environment & Infrastructure
- [ ] `APP_ENV` is set to `production`.
- [ ] `APP_DEBUG` is set to `false`.
- [ ] Database credentials point to the production clustered DB.
- [ ] Redis cache cluster is connected and verified.
- [ ] Storage disk (S3/Cloud) is configured and tested for attachment uploads.
- [ ] SSL certificates are valid and forcing HTTPS.
- [ ] Domain routing and Load Balancers are configured.

## 2. Platform Optimizations
- [ ] Run `php artisan config:cache`
- [ ] Run `php artisan route:cache`
- [ ] Run `php artisan view:cache`
- [ ] Run `php artisan event:cache`
- [ ] Run `npm run build` to compile front-end assets.

## 3. Worker & Scheduler Verification
- [ ] Supervisor is actively monitoring Queue Workers (`php artisan queue:work`).
- [ ] Verify `php artisan schedule:run` is injected into the server's crontab (running every minute).
- [ ] AI processing queue (`queue=ai`) has dedicated workers assigned to prevent starvation.
- [ ] Notification queue (`queue=notifications`) is running.

## 4. Security & Access
- [ ] Default Admin passwords have been rotated.
- [ ] API Keys (WhatsApp, AI) are securely stored in environment variables (NOT committed).
- [ ] `OPENAI_API_KEY`, `GEMINI_API_KEY` are set if AI features are enabled.
- [ ] `WHATSAPP_APP_SECRET` is set (webhook signature verification fails closed in production).
- [ ] CORS policies restrict access exclusively to trusted origins.
- [ ] Rate limiters are actively defending the API and Webhook endpoints.

## 5. Database
- [ ] Run `php artisan migrate --force` to apply all pending migrations.
- [ ] Run `php artisan workspace:backfill` to assign existing rows to the default workspace.

## 6. Monitoring & Backup
- [ ] Application error monitoring (e.g., Sentry/Bugsnag) is active.
- [ ] Server metrics monitoring (e.g., Datadog/NewRelic) is active.
- [ ] Automated database backups are scheduled and a test-restore has been performed.

> **Sign-Off:**
> 
> Signature: _______________________ Date: ___________
