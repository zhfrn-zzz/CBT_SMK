#!/bin/bash
# =============================================================================
# CBT Emergency Kill Switch — SMK LMS
# Path: /usr/local/bin/cbt-emergency.sh
# Usage: sudo cbt-emergency.sh
# Reversal: sudo cbt-recovery.sh
#
# Activates degraded mode to preserve exam-critical endpoints only.
# Run when server is under extreme load mid-exam.
# =============================================================================

set -e

APP_DIR="/var/www/smk-lms"
FPM_CONF="/etc/php/8.3/fpm/pool.d/www.conf"
NGINX_AVAILABLE="/etc/nginx/sites-available"
NGINX_ENABLED="/etc/nginx/sites-enabled"
BACKUP_DIR="/var/backups/cbt-emergency"

echo "🚨 EMERGENCY MODE ACTIVATED at $(date)"
echo ""

# Create backup directory
mkdir -p "$BACKUP_DIR"

# --- 1. Backup current configs ---
echo "[1/5] Backing up current configs..."
cp "$FPM_CONF" "$BACKUP_DIR/www.conf.bak"
cp "$NGINX_ENABLED/cbt.conf" "$BACKUP_DIR/cbt.conf.bak" 2>/dev/null || true
echo "  ✓ Configs backed up to $BACKUP_DIR"

# --- 2. Pause non-essential queue processing ---
echo "[2/5] Pausing default queue workers..."
cd "$APP_DIR"
php artisan queue:pause default 2>/dev/null || echo "  ⚠ queue:pause not available, stopping via supervisor"
# Keep exam-persist queue running — answers must keep saving

# --- 3. Stop Reverb (teacher dashboard real-time updates) ---
echo "[3/5] Stopping Reverb WebSocket server..."
supervisorctl stop smk-lms:reverb 2>/dev/null || true
echo "  ✓ Reverb stopped (teacher dashboard won't update in real-time)"

# --- 4. Reduce PHP-FPM workers ---
echo "[4/5] Reducing PHP-FPM workers 90 → 50..."
sed -i 's/pm.max_children = 90/pm.max_children = 50/' "$FPM_CONF"
systemctl reload php8.3-fpm
echo "  ✓ PHP-FPM reloaded with 50 workers"

# --- 5. Switch Nginx to emergency config (block non-exam routes) ---
echo "[5/5] Switching Nginx to emergency mode..."
if [ -f "$NGINX_AVAILABLE/cbt-emergency.conf" ]; then
    cp "$NGINX_AVAILABLE/cbt-emergency.conf" "$NGINX_ENABLED/cbt.conf"
    nginx -t && nginx -s reload
    echo "  ✓ Nginx reloaded — only exam endpoints active"
else
    echo "  ⚠ cbt-emergency.conf not found, skipping Nginx switch"
    echo "  → Manually block non-exam traffic if needed"
fi

echo ""
echo "════════════════════════════════════════════════"
echo "⚡ EMERGENCY MODE ACTIVE"
echo ""
echo "  ✓ Default queue paused"
echo "  ✓ Reverb stopped"
echo "  ✓ PHP-FPM reduced to 50 workers"
echo "  ✓ Non-exam routes blocked"
echo ""
echo "  Exam endpoints still operational:"
echo "    - /siswa/ujian/{id}/save-answers"
echo "    - /siswa/ujian/{id}/submit"
echo "    - /siswa/ujian/{id}/start"
echo "    - /siswa/ujian/{id}/take"
echo ""
echo "  Run 'sudo cbt-recovery.sh' to restore normal operation."
echo "════════════════════════════════════════════════"
