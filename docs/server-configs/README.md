# Server Configuration — Deployment Guide

> **Server**: Intel Xeon 20-core, 16GB RAM, HDD, Ubuntu 22.04 LTS
> **Stack**: Nginx + PHP-FPM 8.3 + MySQL 8.x + Redis 7.x + Supervisor
> **Target**: 500 concurrent students, 3 exam sessions/day

---

## Prerequisites

```bash
# System
sudo apt update && sudo apt upgrade -y
sudo apt install -y nginx mysql-server redis-server supervisor curl sysstat

# PHP 8.3 + Extensions
sudo add-apt-repository ppa:ondrej/php
sudo apt install -y php8.3-fpm php8.3-mysql php8.3-redis php8.3-xml \
    php8.3-mbstring php8.3-curl php8.3-zip php8.3-bcmath php8.3-gd

# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Node.js 20 LTS (for Vite build)
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
```

---

## Installation Order

Follow this exact sequence. Each step depends on the previous.

### Step 1: MySQL

```bash
# Apply config
sudo cp my.cnf /etc/mysql/mysql.conf.d/mysqld.cnf
sudo systemctl restart mysql

# Create database & user
sudo mysql -e "
  CREATE DATABASE smk_lms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE USER 'smk_lms'@'localhost' IDENTIFIED BY '<STRONG_PASSWORD>';
  GRANT ALL PRIVILEGES ON smk_lms.* TO 'smk_lms'@'localhost';
  FLUSH PRIVILEGES;
"

# Verify
sudo mysql -e "SHOW VARIABLES LIKE 'innodb_buffer_pool_size';"
# Expected: 6442450944 (6GB)
```

### Step 2: Redis

```bash
# Apply config
sudo cp redis.conf /etc/redis/redis.conf
sudo systemctl restart redis-server

# Verify
redis-cli PING              # → PONG
redis-cli CONFIG GET maxmemory  # → 536870912 (512MB)
```

### Step 3: PHP-FPM

```bash
# Apply pool config
sudo cp www.conf /etc/php/8.3/fpm/pool.d/www.conf

# Create log directory
sudo mkdir -p /var/log/php-fpm
sudo chown www-data:www-data /var/log/php-fpm

sudo systemctl restart php8.3-fpm

# Verify
curl -s http://127.0.0.1:9001/fpm-status | head -5
# Should show: pool www, process manager static, max children 90
```

### Step 4: Deploy Application

```bash
# Clone / pull
cd /var/www
git clone <repo-url> smk-lms
cd smk-lms

# Install dependencies
composer install --optimize-autoloader --no-dev
npm ci && npm run build

# Environment
cp .env.example .env
# Edit .env:
#   APP_ENV=production
#   APP_DEBUG=false
#   DB_DATABASE=smk_lms
#   DB_USERNAME=smk_lms
#   DB_PASSWORD=<STRONG_PASSWORD>
#   REDIS_CLIENT=phpredis          ← CRITICAL: not predis
#   CACHE_STORE=redis
#   SESSION_DRIVER=redis
#   QUEUE_CONNECTION=redis

php artisan key:generate

# Database
php artisan migrate --force

# Cache
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan icons:cache 2>/dev/null || true

# Storage link
php artisan storage:link

# Permissions
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

### Step 5: Nginx

```bash
# Main config
sudo cp nginx.conf /etc/nginx/nginx.conf

# Site config
sudo cp cbt.conf /etc/nginx/sites-available/cbt.conf
sudo ln -sf /etc/nginx/sites-available/cbt.conf /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default

# Test & reload
sudo nginx -t
sudo systemctl restart nginx

# Verify
curl -I http://localhost
# Should return 200 OK
```

### Step 6: Supervisor (Queue Workers + Reverb)

```bash
# Apply config
sudo cp supervisor.conf /etc/supervisor/conf.d/smk-lms.conf

# Create log directories
sudo mkdir -p /var/log/supervisor

# Load config
sudo supervisorctl reread
sudo supervisorctl update

# Verify
sudo supervisorctl status smk-lms:*
# All processes should show RUNNING
```

### Step 7: Monitoring & Emergency Scripts

```bash
# Install scripts
sudo cp cbt-monitor.sh /usr/local/bin/
sudo cp cbt-emergency.sh /usr/local/bin/
sudo cp cbt-recovery.sh /usr/local/bin/

sudo chmod +x /usr/local/bin/cbt-monitor.sh
sudo chmod +x /usr/local/bin/cbt-emergency.sh
sudo chmod +x /usr/local/bin/cbt-recovery.sh

# Create monitoring log
sudo touch /var/log/cbt-monitor.log /var/log/cbt-alerts.log
sudo chmod 666 /var/log/cbt-monitor.log /var/log/cbt-alerts.log

# Add to cron (runs every minute)
echo "* * * * * /usr/local/bin/cbt-monitor.sh" | sudo tee -a /etc/crontab
```

---

## Pre-Production Checklist

Run through this before the first exam.

### Environment

- [ ] `APP_ENV=production` and `APP_DEBUG=false`
- [ ] `REDIS_CLIENT=phpredis` (NOT predis — 2-5x faster)
- [ ] `SESSION_DRIVER=redis`
- [ ] `QUEUE_CONNECTION=redis`
- [ ] `APP_URL` set to actual domain
- [ ] `.env` has no duplicate keys (check SESSION_DRIVER isn't overridden)

### MySQL

- [ ] `innodb_buffer_pool_size = 6G` verified
- [ ] `innodb_flush_log_at_trx_commit = 2` verified
- [ ] `slow_query_log = 1` enabled
- [ ] `skip-log-bin` set (unless replication needed)
- [ ] Composite indexes applied:
  ```sql
  -- Run these manually if migrations haven't added them:
  ALTER TABLE exam_attempts ADD INDEX idx_attempt_lookup (exam_session_id, user_id, status);
  ALTER TABLE student_answers ADD UNIQUE INDEX uniq_attempt_question (exam_attempt_id, question_id);
  ALTER TABLE exam_activity_logs ADD INDEX idx_attempt_event (exam_attempt_id, event_type);
  ```

### Redis

- [ ] `maxmemory 512mb` verified
- [ ] `save ""` (no RDB persistence during exams)
- [ ] `appendonly no` (no AOF)
- [ ] `redis-cli PING` returns `PONG`

### PHP-FPM

- [ ] `pm = static`, `pm.max_children = 90`
- [ ] `php8.3-redis` extension installed: `php -m | grep redis`
- [ ] Status page accessible: `curl http://127.0.0.1:9001/fpm-status`
- [ ] Slow log directory exists: `/var/log/php-fpm/`

### Nginx

- [ ] `nginx -t` passes
- [ ] Gzip enabled (check: `curl -H "Accept-Encoding: gzip" -I http://localhost`)
- [ ] Rate limit zones configured (autosave, api)
- [ ] Static assets return cache headers
- [ ] WebSocket proxy works (test Reverb connection)

### Supervisor

- [ ] All processes RUNNING: `supervisorctl status smk-lms:*`
- [ ] 3 default workers + 2 exam-persist workers
- [ ] Reverb running on port 8080
- [ ] Logs rotating (check `stdout_logfile_maxbytes`)

### Application

- [ ] `php artisan config:cache` — no errors
- [ ] `php artisan route:cache` — no errors
- [ ] `php artisan migrate:status` — all migrations ran
- [ ] Storage link exists: `ls -la public/storage`
- [ ] Queue processing: `php artisan queue:monitor default,exam-persist`

---

## File Reference

| File | Deploy Path | Service |
|------|-------------|---------|
| `my.cnf` | `/etc/mysql/mysql.conf.d/mysqld.cnf` | MySQL |
| `www.conf` | `/etc/php/8.3/fpm/pool.d/www.conf` | PHP-FPM |
| `redis.conf` | `/etc/redis/redis.conf` | Redis |
| `nginx.conf` | `/etc/nginx/nginx.conf` | Nginx (main) |
| `cbt.conf` | `/etc/nginx/sites-available/cbt.conf` | Nginx (site) |
| `supervisor.conf` | `/etc/supervisor/conf.d/smk-lms.conf` | Supervisor |
| `cbt-monitor.sh` | `/usr/local/bin/cbt-monitor.sh` | Cron (1 min) |
| `cbt-emergency.sh` | `/usr/local/bin/cbt-emergency.sh` | Manual |
| `cbt-recovery.sh` | `/usr/local/bin/cbt-recovery.sh` | Manual |

---

## Emergency Procedures

### Server under load mid-exam

```bash
sudo cbt-emergency.sh
# Reduces workers, stops non-critical services, blocks non-exam routes
```

### Recover after emergency

```bash
sudo cbt-recovery.sh
# Restores all services to normal operation
```

### Monitor in real-time

```bash
tail -f /var/log/cbt-monitor.log      # All metrics
tail -f /var/log/cbt-alerts.log       # Alerts only
supervisorctl status smk-lms:*        # Process status
redis-cli MONITOR                     # Redis commands (WARNING: high overhead)
```

### Quick health check

```bash
# PHP-FPM
curl -s http://127.0.0.1:9001/fpm-status | grep -E 'active|idle|listen'

# Redis
redis-cli INFO memory | grep used_memory_human

# MySQL
mysql -e "SHOW STATUS WHERE Variable_name IN ('Threads_connected','Threads_running');"

# Queue
php /var/www/smk-lms/artisan queue:monitor default,exam-persist
```
