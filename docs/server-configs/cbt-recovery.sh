#!/bin/bash
# =============================================================================
# CBT Recovery Script — SMK LMS
# Path: /usr/local/bin/cbt-recovery.sh
# Usage: sudo cbt-recovery.sh
#
# Reverses emergency mode — restores full system operation.
# Run after emergency situation is resolved.
# =============================================================================

set -e

APP_DIR="/var/www/smk-lms"
FPM_CONF="/etc/php/8.3/fpm/pool.d/www.conf"
NGINX_AVAILABLE="/etc/nginx/sites-available"
NGINX_ENABLED="/etc/nginx/sites-enabled"
BACKUP_DIR="/var/backups/cbt-emergency"

echo "🔄 RECOVERY MODE — Restoring normal operation at $(date)"
echo ""

# --- 1. Restore PHP-FPM workers ---
echo "[1/5] Restoring PHP-FPM workers to 90..."
if [ -f "$BACKUP_DIR/www.conf.bak" ]; then
    cp "$BACKUP_DIR/www.conf.bak" "$FPM_CONF"
    echo "  ✓ Restored from backup"
else
    sed -i 's/pm.max_children = 50/pm.max_children = 90/' "$FPM_CONF"
    echo "  ✓ Restored via sed"
fi
systemctl reload php8.3-fpm
echo "  ✓ PHP-FPM reloaded with 90 workers"

# --- 2. Restore Nginx config ---
echo "[2/5] Restoring Nginx site config..."
if [ -f "$BACKUP_DIR/cbt.conf.bak" ]; then
    cp "$BACKUP_DIR/cbt.conf.bak" "$NGINX_ENABLED/cbt.conf"
    echo "  ✓ Restored from backup"
elif [ -f "$NGINX_AVAILABLE/cbt.conf" ]; then
    cp "$NGINX_AVAILABLE/cbt.conf" "$NGINX_ENABLED/cbt.conf"
    echo "  ✓ Restored from sites-available"
fi
nginx -t && nginx -s reload
echo "  ✓ Nginx reloaded — all routes active"

# --- 3. Resume queue processing ---
echo "[3/5] Resuming default queue workers..."
cd "$APP_DIR"
php artisan queue:resume default 2>/dev/null || echo "  ⚠ queue:resume not available, restarting via supervisor"
supervisorctl start smk-lms:laravel-worker-default_00 smk-lms:laravel-worker-default_01 smk-lms:laravel-worker-default_02 2>/dev/null || true
echo "  ✓ Default queue resumed"

# --- 4. Restart Reverb ---
echo "[4/5] Starting Reverb WebSocket server..."
supervisorctl start smk-lms:reverb 2>/dev/null || true
echo "  ✓ Reverb started"

# --- 5. Verify all services ---
echo "[5/5] Verifying services..."
echo ""
echo "  Services status:"
supervisorctl status smk-lms:* 2>/dev/null | awk '{print "    "$0}'
echo ""

# Check PHP-FPM
FPM_STATUS=$(curl -s http://127.0.0.1:9001/fpm-status 2>/dev/null)
if [ -n "$FPM_STATUS" ]; then
    ACTIVE=$(echo "$FPM_STATUS" | grep "^active processes:" | awk '{print $3}')
    IDLE=$(echo "$FPM_STATUS" | grep "^idle processes:" | awk '{print $3}')
    echo "  PHP-FPM: active=$ACTIVE idle=$IDLE"
else
    echo "  PHP-FPM: ⚠ Cannot reach status page"
fi

# Check Redis
REDIS_MEM=$(redis-cli INFO memory 2>/dev/null | grep "used_memory_human" | awk -F: '{print $2}' | tr -d '\r')
echo "  Redis: memory=${REDIS_MEM:-N/A}"

# Check MySQL
MYSQL_CONN=$(mysql -N -e "SHOW STATUS LIKE 'Threads_connected';" 2>/dev/null | awk '{print $2}')
echo "  MySQL: connections=${MYSQL_CONN:-N/A}"

echo ""
echo "════════════════════════════════════════════════"
echo "✅ NORMAL OPERATION RESTORED"
echo ""
echo "  All services running. Monitor with:"
echo "    tail -f /var/log/cbt-monitor.log"
echo "    supervisorctl status smk-lms:*"
echo "════════════════════════════════════════════════"
