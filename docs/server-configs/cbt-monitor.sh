#!/bin/bash
# =============================================================================
# CBT Monitoring Script — SMK LMS
# Path: /usr/local/bin/cbt-monitor.sh
# Cron: * * * * * /usr/local/bin/cbt-monitor.sh
# Log:  /var/log/cbt-monitor.log
# =============================================================================

LOG="/var/log/cbt-monitor.log"
ALERT_LOG="/var/log/cbt-alerts.log"

echo "=== $(date '+%Y-%m-%d %H:%M:%S') ===" >> "$LOG"

# --- PHP-FPM Status ---
echo "[PHP-FPM]" >> "$LOG"
FPM_STATUS=$(curl -s http://127.0.0.1:9001/fpm-status 2>/dev/null)
if [ -n "$FPM_STATUS" ]; then
    echo "$FPM_STATUS" | grep -E 'active processes|idle processes|listen queue|max listen queue' >> "$LOG"

    LISTEN_QUEUE=$(echo "$FPM_STATUS" | grep "^listen queue:" | awk '{print $3}')
    ACTIVE=$(echo "$FPM_STATUS" | grep "^active processes:" | awk '{print $3}')

    if [ "${LISTEN_QUEUE:-0}" -gt 10 ]; then
        echo "🔴 CRITICAL: FPM listen queue = $LISTEN_QUEUE" >> "$ALERT_LOG"
    elif [ "${LISTEN_QUEUE:-0}" -gt 0 ]; then
        echo "⚠️  WARNING: FPM listen queue = $LISTEN_QUEUE" >> "$ALERT_LOG"
    fi

    if [ "${ACTIVE:-0}" -gt 85 ]; then
        echo "🔴 CRITICAL: FPM active workers = $ACTIVE/90" >> "$ALERT_LOG"
    elif [ "${ACTIVE:-0}" -gt 70 ]; then
        echo "⚠️  WARNING: FPM active workers = $ACTIVE/90" >> "$ALERT_LOG"
    fi
else
    echo "  ERROR: Cannot reach FPM status" >> "$LOG"
fi

# --- Redis ---
echo "[Redis]" >> "$LOG"
REDIS_MEM=$(redis-cli INFO memory 2>/dev/null | grep "used_memory_human" | tr -d '\r')
REDIS_CLIENTS=$(redis-cli INFO clients 2>/dev/null | grep "connected_clients" | tr -d '\r')
REDIS_KEYS=$(redis-cli INFO keyspace 2>/dev/null | grep "^db0" | tr -d '\r')

echo "  $REDIS_MEM" >> "$LOG"
echo "  $REDIS_CLIENTS" >> "$LOG"
echo "  $REDIS_KEYS" >> "$LOG"

REDIS_MB=$(redis-cli INFO memory 2>/dev/null | grep "used_memory:" | awk -F: '{printf "%.0f", $2/1048576}' | tr -d '\r')
if [ "${REDIS_MB:-0}" -gt 450 ]; then
    echo "🔴 CRITICAL: Redis memory = ${REDIS_MB}MB / 512MB" >> "$ALERT_LOG"
elif [ "${REDIS_MB:-0}" -gt 300 ]; then
    echo "⚠️  WARNING: Redis memory = ${REDIS_MB}MB / 512MB" >> "$ALERT_LOG"
fi

# --- MySQL ---
echo "[MySQL]" >> "$LOG"
MYSQL_STATUS=$(mysql -N -e "SHOW STATUS WHERE Variable_name IN ('Threads_connected','Threads_running','Slow_queries');" 2>/dev/null)
if [ -n "$MYSQL_STATUS" ]; then
    echo "$MYSQL_STATUS" >> "$LOG"

    THREADS=$(echo "$MYSQL_STATUS" | grep "Threads_connected" | awk '{print $2}')
    if [ "${THREADS:-0}" -gt 130 ]; then
        echo "🔴 CRITICAL: MySQL connections = $THREADS / 150" >> "$ALERT_LOG"
    elif [ "${THREADS:-0}" -gt 100 ]; then
        echo "⚠️  WARNING: MySQL connections = $THREADS / 150" >> "$ALERT_LOG"
    fi
else
    echo "  ERROR: Cannot query MySQL" >> "$LOG"
fi

# --- Disk I/O ---
echo "[Disk]" >> "$LOG"
if command -v iostat &>/dev/null; then
    iostat -d 1 1 | tail -n +4 >> "$LOG"
fi

# --- System ---
echo "[System]" >> "$LOG"
echo "  Load: $(cat /proc/loadavg 2>/dev/null || echo 'N/A')" >> "$LOG"
echo "  Memory: $(free -m | grep Mem | awk '{printf "Used: %sMB / %sMB (%.0f%%)", $3, $2, $3/$2*100}')" >> "$LOG"

# --- Laravel Queue ---
echo "[Queue]" >> "$LOG"
QUEUE_SIZE=$(redis-cli LLEN queues:default 2>/dev/null | tr -d '\r')
EXAM_QUEUE_SIZE=$(redis-cli LLEN queues:exam-persist 2>/dev/null | tr -d '\r')
echo "  default queue: ${QUEUE_SIZE:-0} jobs" >> "$LOG"
echo "  exam-persist queue: ${EXAM_QUEUE_SIZE:-0} jobs" >> "$LOG"

if [ "${QUEUE_SIZE:-0}" -gt 5000 ]; then
    echo "🔴 CRITICAL: Default queue size = $QUEUE_SIZE" >> "$ALERT_LOG"
elif [ "${QUEUE_SIZE:-0}" -gt 1000 ]; then
    echo "⚠️  WARNING: Default queue size = $QUEUE_SIZE" >> "$ALERT_LOG"
fi

# --- Supervisor ---
echo "[Supervisor]" >> "$LOG"
supervisorctl status smk-lms:* 2>/dev/null | awk '{print "  "$0}' >> "$LOG"

echo "" >> "$LOG"
