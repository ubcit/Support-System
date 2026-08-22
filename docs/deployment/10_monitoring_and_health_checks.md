# Monitoring and Health Checks

Operational excellence requires proactive monitoring of application health, server resources, and exception tracking.

## 1. Exception Tracking (Flare/Sentry)

All unhandled exceptions, AI provider failures, and WhatsApp sync errors must be automatically reported.
- **Tool:** Integrate [Flare](https://flareapp.io/) or [Sentry](https://sentry.io/).
- **Configuration:** Set `LOG_CHANNEL=stack` and ensure the tracking package captures stack traces, active user sessions, and context variables.

## 2. Server Resource Monitoring

Monitor CPU, RAM, Disk Space, and Nginx/PHP-FPM metrics.
- **Recommended Tools:** Datadog, New Relic, or a simple DigitalOcean/AWS CloudWatch agent.
- **Alerts:** Trigger critical alerts if Disk Space exceeds 85% (to prevent database corruption) or CPU sustains 90%+ for 10 minutes.

## 3. Application Health Endpoint

Laravel 11 provides a built-in health endpoint:
- **Endpoint:** `GET https://your-domain.com/up`
- **Response:** Returns `200 OK` if the framework has booted successfully.
- **Uptime Monitoring:** Configure an external tool (e.g., UptimeRobot, Pingdom) to hit this endpoint every 60 seconds.

## 4. Queue Monitoring

Monitoring the Redis queue backlog is critical for the asynchronous AI pipeline.
- **Filament Dashboard:** Use a Filament Queue Monitor plugin to visualize job processing in real-time.
- **Failed Jobs Alert:** Schedule a console command to email the development team if `DB::table('failed_jobs')->count() > 0`.

## 5. Slow Query Logging

Identify performance bottlenecks before they impact users.
- In `config/database.php`, slow query logging should be enabled:
  - Any query taking longer than 1000ms should be logged.
  - Check the `laravel.log` daily for `QueryException` or slow query warnings to evaluate database index efficiency.

## 6. Log Rotation

By default, Laravel is configured with `LOG_CHANNEL=daily` or `stack`.
- Logs are rotated daily and kept for a maximum of 14 days (`log_max_files` setting in `config/logging.php`) to prevent the `storage/logs` directory from consuming disk space indefinitely.
