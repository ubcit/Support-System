# Disaster Recovery Guide

In the event of a catastrophic server failure (e.g., hardware failure, datacenter outage, or severe compromise), follow this guide to provision a new server and restore services rapidly.

## Phase 1: Provisioning & Code Restoration

1. **Spin up a new Server** matching the hardware specifications in `01_server_requirements.md`.
2. **Update DNS Records:** Point the primary domain (`your-domain.com`) to the new server's IP address.
3. **Execute Deployment Script:** Follow `02_production_deployment_guide.md` precisely to install Nginx, PHP, MySQL, Redis, and Supervisor.
4. **Clone the Source Code:** Pull the latest production branch from Git.
5. **Install Dependencies:** Run `composer install --no-dev` and `npm ci && npm run build`.

## Phase 2: Environment Restoration

1. **Reconstruct `.env`:** Fetch the production `.env` variables from the secure vault (e.g., 1Password, AWS Secrets Manager). Do not proceed without the original `APP_KEY`—it is required to decrypt any encrypted database columns or existing sessions.
2. **Configure Nginx & SSL:** Reinstall Certbot and generate new SSL certificates for the domain as traffic begins to route to the new IP.

## Phase 3: Data Restoration

1. **Download the latest backup:** Access S3 and download the most recent nightly backup.
2. **Restore Database:** Execute the SQL dump into the fresh MySQL instance.
3. **Restore File Storage:** Copy the user uploaded files back into `storage/app/public` and ensure `chown www-data:www-data`.
4. **Link Storage:** Run `php artisan storage:link`.

## Phase 4: Service Verification

1. **Start Workers:** `sudo supervisorctl start all`
2. **Verify Connections:** Run `php artisan queue:monitor` or check database connectivity using `php artisan tinker`.
3. **Health Check:** Hit the `/up` health endpoint to verify the framework has bootstrapped.
4. **Manual UI Verification:** Log in via the browser, verify data integrity, test AI pipeline execution via the simulator, and ensure WhatsApp webhooks are properly received.
