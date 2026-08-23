# Server Requirements

This document outlines the hardware and software requirements for deploying **THE-SPACE-MANAGEMENT** in a production environment.

## Minimum Hardware Requirements
- **CPU:** 2 Cores (4 Cores recommended for high worker concurrency)
- **RAM:** 4 GB (8 GB recommended for Redis and database caching)
- **Storage:** 50 GB NVMe/SSD
- **Network:** 1 Gbps with a Static Public IP

## Software Stack
- **OS:** Ubuntu 22.04 LTS or Debian 12
- **Web Server:** Nginx (latest stable)
- **PHP:** PHP 8.3 or 8.4 (with `php-fpm`; production target: 8.4)
- **Database:** MySQL 8.0+ or PostgreSQL 15+
- **In-Memory Cache & Queue:** Redis 7.0+
- **Process Manager:** Supervisor
- **Package Managers:** Composer 2.x, Node.js 20.x & NPM

## Required PHP Extensions
Ensure the following extensions are installed and enabled in `php.ini`:
- `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `json`, `mbstring`, `openssl`, `pcre`, `pdo`, `tokenizer`, `xml`
- `pdo_mysql` (or `pdo_pgsql`)
- `redis` (PECL extension)
- `gd` or `imagick` (for file/image manipulations)
- `zip`

## Network Port Requirements
Ensure the following ports are open in the server firewall (e.g., UFW or AWS Security Groups):
- **TCP 80:** HTTP (Certbot verification and redirects)
- **TCP 443:** HTTPS (Application traffic, WhatsApp Webhooks)
- **TCP 22:** SSH (Admin access)

*(Ensure databases and Redis are bound ONLY to `127.0.0.1` and not exposed publicly).*
