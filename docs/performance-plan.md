# CBT Performance Optimization Plan — 500 Concurrent Students

> **Server**: Intel Xeon 20-core, 16GB RAM, HDD, Nginx, PHP 8.3 FPM, Laravel 12, MySQL 8.x, Redis (predis)
> **Load**: 1500 siswa total, 3 sesi × 500 siswa. Peak: 500 concurrent. Auto-save ~1000 req/min. Thundering herd on submit.
> **Codebase**: 591 tests passing. Auto-save → Redis tiap 30s → MySQL persist via queue tiap 60s. All questions loaded client-side at exam start.

---

## Table of Contents

1. [MySQL Tuning](#1-mysql-tuning-mycnf)
2. [PHP-FPM Tuning](#2-php-fpm-tuning)
3. [Redis Tuning](#3-redis-tuning)
4. [Laravel Application Optimization](#4-laravel-application-optimization)
5. [Thundering Herd Prevention](#5-thundering-herd-prevention)
6. [Frontend Optimization](#6-frontend-optimization)
7. [Load Testing Strategy](#7-load-testing-strategy)
8. [Nginx Tuning](#8-nginx-tuning)
9. [Monitoring Production](#9-monitoring-production)
10. [Contingency Plan](#10-contingency-plan)
11. [Implementation Priority](#11-implementation-priority)

---

## 1. MySQL Tuning (my.cnf)

### RAM Budget Analysis

| Component | Allocation |
|---|---|
| OS + system | 2 GB |
| Redis | 0.5 GB |
| PHP-FPM (see §2) | 4.5 GB |
| Reverb WebSocket | 0.3 GB |
| Nginx | 0.2 GB |
| **MySQL** | **~8.5 GB** |

### Recommended my.cnf Configuration

```ini
[mysqld]
# === InnoDB Buffer Pool ===
# 8.5GB tersedia untuk MySQL. Buffer pool = ~70% dari alokasi MySQL.
# HDD makes buffer pool CRITICAL — semua hot data harus fit di RAM.
innodb_buffer_pool_size = 6G
innodb_buffer_pool_instances = 4

# === InnoDB Log ===
# Larger log = fewer disk flushes pada HDD. 512MB per file × 2 files = 1GB redo log.
innodb_log_file_size = 512M
innodb_log_buffer_size = 64M

# === Flush Strategy (CRITICAL untuk HDD) ===
# Value 2: flush ke OS buffer tiap commit, disk write tiap 1 detik.
# Trade-off: kehilangan max 1 detik data jika server crash (acceptable untuk ujian karena Redis backup).
innodb_flush_log_at_trx_commit = 2

# O_DIRECT: bypass OS file cache (karena InnoDB punya buffer pool sendiri).
# Mengurangi double-caching yang menghabiskan RAM.
innodb_flush_method = O_DIRECT

# === Write Performance (HDD) ===
# Agresif merge I/O operations untuk HDD sequential write.
innodb_io_capacity = 200
innodb_io_capacity_max = 400
innodb_write_io_threads = 8
innodb_read_io_threads = 8

# === Connections ===
# 500 concurrent × auto-save + queue workers + admin + buffer
# PHP-FPM max_children (90) + queue workers (4) + cron + admin = ~110
max_connections = 150
wait_timeout = 60
interactive_timeout = 120

# === Temp Tables (HDD sensitive) ===
# Temp tables yang exceed ini akan write ke disk — sangat lambat di HDD.
tmp_table_size = 128M
max_heap_table_size = 128M

# === Join/Sort Buffers ===
# Per-connection allocation, jangan terlalu besar.
join_buffer_size = 4M
sort_buffer_size = 4M
read_rnd_buffer_size = 2M

# === Query Cache (MySQL 8.x — DISABLED) ===
# MySQL 8.0+ sudah remove query cache. Tidak perlu setting.

# === Table Open Cache ===
table_open_cache = 4000
table_definition_cache = 2000

# === Binary Log (opsional, disable jika tidak pakai replication) ===
skip-log-bin
```

### Index Audit — Current State

#### `exam_attempts` table
```
Current indexes:
  - PRIMARY (id)
  - FK index: exam_session_id
  - FK index: user_id
  - Composite: (exam_session_id, user_id)  ✅
  - Single: status                          ✅
```

**Assessment**: Index `(exam_session_id, user_id)` sudah cover query pattern utama:
- `saveAnswers`: `WHERE exam_session_id = ? AND user_id = ? AND status = ?`
- `submit`: same pattern

> [!WARNING]
> Query pattern utama pakai 3 kolom tapi composite index hanya cover 2. MySQL bisa pakai index `(exam_session_id, user_id)` lalu filter `status` dari result, tapi optimal jika:

**Rekomendasi**: Tambah composite index `(exam_session_id, user_id, status)` — menggantikan index `(exam_session_id, user_id)` yang existing.

```sql
ALTER TABLE exam_attempts DROP INDEX exam_attempts_exam_session_id_user_id_index;
ALTER TABLE exam_attempts ADD INDEX idx_attempt_lookup (exam_session_id, user_id, status);
```

#### `student_answers` table
```
Current indexes:
  - PRIMARY (id)
  - FK index: exam_attempt_id
  - FK index: question_id
  - Composite: (exam_attempt_id, question_id)  ✅
```

**Assessment**: Composite index sudah optimal untuk `updateOrCreate` pattern.

**Rekomendasi**: Convert ke UNIQUE constraint (seharusnya unique secara bisnis):
```sql
ALTER TABLE student_answers DROP INDEX student_answers_exam_attempt_id_question_id_index;
ALTER TABLE student_answers ADD UNIQUE INDEX uniq_attempt_question (exam_attempt_id, question_id);
```
Ini juga mempercepat `updateOrCreate` karena MySQL detect conflict via unique index.

#### `exam_activity_logs` table
```
Current indexes:
  - PRIMARY (id)
  - Single: exam_attempt_id  ✅
```

**Assessment**: Cukup untuk current query pattern. Tapi `logActivity` method query `WHERE exam_attempt_id = ? AND event_type = 'tab_switch'` untuk count.

**Rekomendasi**: Tambah composite index:
```sql
ALTER TABLE exam_activity_logs ADD INDEX idx_attempt_event (exam_attempt_id, event_type);
```

---

## 2. PHP-FPM Tuning

### RAM Calculation

| Component | RAM Used |
|---|---|
| OS + system | 2.0 GB |
| MySQL | 8.5 GB |
| Redis | 0.5 GB |
| Reverb | 0.3 GB |
| Nginx | 0.2 GB |
| **Available for PHP-FPM** | **~4.5 GB** |

### Per-Worker Memory

Laravel 12 + Inertia SSR-less request (API auto-save):
- Typical: **~30 MB** per worker (auto-save endpoint, lightweight Redis write)
- Peak: **~50 MB** per worker (exam start — loads semua questions + eager load)
- Average: **~35 MB** per worker

### Worker Count

```
Available RAM: 4.5 GB = 4608 MB
Per worker (average): 35 MB
Safe max_children: 4608 / 35 = ~130

Tapi kita pakai safety margin 70%:
Practical max_children: 90
```

### Recommended PHP-FPM Pool Config (`/etc/php/8.3/fpm/pool.d/www.conf`)

```ini
; === Process Manager ===
; STATIC lebih baik untuk predictable exam load.
; Dynamic punya overhead fork/kill yang tidak perlu saat peak.
pm = static
pm.max_children = 90

; === Max Requests (memory leak prevention) ===
; Recycle worker setiap 500 requests untuk prevent memory creep.
pm.max_requests = 500

; === Timeouts ===
; Auto-save harus cepat (<1s). Submit bisa ~5-10s (grading).
; Global timeout: 30 detik cukup.
request_terminate_timeout = 30s

; === Slow Log ===
slowlog = /var/log/php-fpm/slow.log
request_slowlog_timeout = 5s

; === Status Page (untuk monitoring) ===
pm.status_path = /fpm-status
pm.status_listen = 127.0.0.1:9001
```

> [!IMPORTANT]
> Dengan `pm = static` dan 90 workers, PHP-FPM akan pre-fork 90 proses saat startup. Ini menggunakan ~3.15 GB RAM baseline. Pastikan MySQL dan Redis sudah start duluan sebelum PHP-FPM.

### Jika RAM terasa kurang (fallback ke dynamic)

```ini
pm = dynamic
pm.max_children = 90
pm.start_servers = 30
pm.min_spare_servers = 20
pm.max_spare_servers = 50
pm.max_requests = 500
```

---

## 3. Redis Tuning

### Memory Estimation per Siswa

| Data | Estimasi Size |
|---|---|
| Answers JSON (40-50 soal × ~50 bytes per answer) | ~2.5 KB |
| Flags JSON (array of IDs) | ~0.2 KB |
| Last save timestamp | ~0.05 KB |
| Session data (Laravel session) | ~1 KB |
| Cache keys (exam session lock, etc) | ~0.5 KB |
| **Total per siswa** | **~4.25 KB** |

### Total untuk 500 Concurrent

```
500 siswa × 4.25 KB = 2.125 MB (exam data)
Laravel cache overhead  = ~20 MB
Session data (500 users) = ~5 MB
Queue metadata (Redis)  = ~10 MB
Reverb connection state = ~5 MB
Buffer/fragmentation    = ~50 MB
─────────────────────────────────
Total: ~92 MB
```

### Recommended Redis Config (`/etc/redis/redis.conf`)

```ini
# === Memory ===
maxmemory 512mb
# allkeys-lru: evict least recently used keys ketika memory penuh.
# Ini safe karena exam data punya TTL dan bisa re-save dari client.
maxmemory-policy allkeys-lru

# === Persistence ===
# DISABLE RDB save di production exam time — save ke disk pada HDD = blocking.
# Data exam critical di-backup ke MySQL via PersistAnswersJob.
save ""

# Disable AOF juga — Redis dipakai sebagai cache+buffer, bukan primary storage.
appendonly no

# === Connection ===
maxclients 1000
timeout 300
tcp-keepalive 60

# === Performance ===
# Disable transparent hugepages (Linux)
# (set via sysctl, bukan redis.conf)
hz 10
```

### Key TTL Strategy

| Key Pattern | TTL | Rationale |
|---|---|---|
| `exam:{id}:student:{id}:answers` | `exam_ends_at + 24h` | Keep sampai post-exam cleanup |
| `exam:{id}:student:{id}:flags` | `exam_ends_at + 24h` | Same as answers |
| `exam:{id}:student:{id}:last_save` | `exam_ends_at + 24h` | Same as answers |
| `exam_session:{id}:session_id` | `86400` (24h) | Device lock cache |
| Laravel session keys | `7200` (2h) | Session lifetime during exam |

> [!TIP]
> Current code sudah set TTL correctly di `saveAnswersToRedis()`: `max(3600, ends_at diff + 86400)`. Ini sudah baik.

### Production .env Change

```env
# CRITICAL: Ganti predis → phpredis (C extension, 2-5x faster)
REDIS_CLIENT=phpredis
```

Install phpredis:
```bash
sudo apt install php8.3-redis
sudo systemctl restart php8.3-fpm
```

---

## 4. Laravel Application Optimization

### 4.1 Caching Commands (Production Deploy Script)

```bash
#!/bin/bash
# deploy.sh — jalankan setiap deploy
php artisan config:cache      # Config → single cached file
php artisan route:cache        # Routes → cached file (skip route registration)
php artisan view:cache         # Blade/Inertia views pre-compiled
php artisan event:cache        # Event-listener mapping cached
php artisan icons:cache        # Jika pakai blade-icons

# Composer optimized autoloader
composer install --optimize-autoloader --no-dev
```

### 4.2 Queue Worker Tuning

Current issue: `QUEUE_CONNECTION=redis` (baik), tapi butuh tuning worker count.

```bash
# Production: jalankan via Supervisor
# /etc/supervisor/conf.d/laravel-worker.conf
[program:laravel-worker-default]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/smk-lms/artisan queue:work redis --queue=default --sleep=3 --tries=3 --max-time=3600 --memory=128 --backoff=10,30,60
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
numprocs=3
redirect_stderr=true
stdout_logfile=/var/log/supervisor/worker-default.log

[program:laravel-worker-exam]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/smk-lms/artisan queue:work redis --queue=exam-persist --sleep=3 --tries=5 --max-time=3600 --memory=128 --backoff=5,15,30
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/supervisor/worker-exam.log
```

**Rationale**:
- **3 default workers**: handle general jobs (grading, notifications, exports)
- **2 exam-persist workers**: dedicated untuk `PersistAnswersJob` — pisahkan supaya tidak diblok oleh slow general jobs
- `--memory=128`: restart worker jika memory > 128MB (leak prevention)
- `--max-time=3600`: recycle tiap 1 jam
- `--backoff=10,30,60`: exponential backoff pada retry

### 4.3 PersistAnswersJob — Batch Upsert Optimization

> [!CAUTION]
> **BOTTLENECK KRITIS**: Current `PersistAnswersJob` melakukan individual `updateOrCreate()` per answer per student. Dengan 500 siswa × 40 soal = **20,000 individual queries** per run. Pada HDD, ini bisa take 30-60 detik.

**Current code** (`PersistAnswersJob.php:63-73`):
```php
foreach ($answers as $questionId => $answer) {
    StudentAnswer::updateOrCreate(
        ['exam_attempt_id' => $attempt->id, 'question_id' => (int) $questionId],
        ['answer' => $answer, 'answered_at' => now()]
    );
}
```

**Optimized approach**: Batch upsert via `DB::statement` dengan `INSERT ... ON DUPLICATE KEY UPDATE`:
```php
// Collect all upsert data
$values = [];
$now = now()->toDateTimeString();
foreach ($answers as $questionId => $answer) {
    $values[] = [
        'exam_attempt_id' => $attempt->id,
        'question_id' => (int) $questionId,
        'answer' => $answer,
        'answered_at' => $now,
        'updated_at' => $now,
    ];
}

// Single batch upsert (requires unique index on exam_attempt_id + question_id)
StudentAnswer::upsert(
    $values,
    ['exam_attempt_id', 'question_id'],  // unique key
    ['answer', 'answered_at', 'updated_at']  // columns to update
);
```

**Impact**: 20,000 queries → ~500 queries (1 per student batch). **40x improvement**.

### 4.4 submitExam — Same N+1 Problem

`ExamAttemptService::submitExam()` lines 263-274 have the same issue. Apply identical `upsert()` fix.

### 4.5 Eager Loading Audit

| Location | Current | Issue | Fix |
|---|---|---|---|
| `ExamController::saveAnswers()` | `$attempt->examSession` | Lazy load on every auto-save | Pre-load `examSession` in the initial query or cache session_id |
| `ExamController::logActivity()` | `$attempt->load('examSession')` | Extra query per log call | Include `examSession` in initial query |
| `ExamAttemptService::autoGrade()` | `$answers->with(['question.options', 'question.matchingPairs', 'question.keywords'])` | ✅ Already eager loaded | No change needed |
| `ExamController::index()` | `ExamSession::whereHas(...)` | `whereHas` generates subquery | Fine for listing, not exam-critical path |

**Key optimization**: Cache `exam_session_id` → `ExamSession` mapping di Redis selama exam berlangsung:
```php
// Di saveAnswersToRedis, cache session object:
$session = Cache::remember(
    "exam_session:{$attempt->exam_session_id}",
    3600,
    fn() => $attempt->examSession
);
```

### 4.6 auto-save saveAnswers Endpoint — Reduce DB Queries

Current `saveAnswers` does:
1. Validate request
2. Query `exam_attempts` WHERE session + user + status (1 query)
3. Check `isExpired()` → accesses `examSession` relation (1 query, lazy)
4. `saveAnswersToRedis()` → 3 Redis SETEX calls
5. Count `attemptQuestions` (1 query)
6. Fire event

**Total: 3 DB queries + 3 Redis calls per auto-save.**

Dengan 500 siswa × 2 saves/menit = 1000 req/min = **3000 DB queries/min** just for auto-save.

**Fix**: Cache the attempt object in Redis during exam:
```php
$attempt = Cache::remember(
    "active_attempt:{$ujian->id}:{$request->user()->id}",
    300, // 5 min TTL
    fn() => ExamAttempt::where('exam_session_id', $ujian->id)
        ->where('user_id', $request->user()->id)
        ->where('status', ExamAttemptStatus::InProgress)
        ->with('examSession')
        ->first()
);
```

**Impact**: 3000 DB queries/min → ~500/min (cache miss hanya saat cold start + tiap 5 menit).

### 4.7 Laravel Octane Evaluation

| Factor | Assessment |
|---|---|
| Benefit | Keep app in memory, skip boot per request. ~2-3x throughput. |
| Risk | Memory leaks, singleton state issues, Inertia SSR compatibility unclear. |
| Effort | Medium — needs audit semua service providers, no static state mutation. |
| **Verdict** | **NOT RECOMMENDED untuk production pertama.** Terlalu risky untuk exam scenario dimana stability > speed. Current architecture (Redis auto-save) sudah cukup mengurangi PHP load. Evaluasi setelah first production run jika masih bottleneck. |

---

## 5. Thundering Herd Prevention

### 5.1 Scenario Analysis

Timer habis → 500 siswa auto-submit simultan → 500 concurrent:
1. `forceSave()` ke Redis (manageable)
2. POST `/submit` → `submitExam()` yang melakukan:
   - Redis GET answers
   - Loop `updateOrCreate` per answer (N+1!)
   - `autoGrade()` — load semua answers + relations
   - UPDATE attempt status
   - Redis DEL 3 keys

**Per submit: ~50-100 DB queries. 500 submit = 25,000-50,000 queries dalam 1-2 detik.**

### 5.2 Client-Side Stagger (Auto-Submit Delay)

Di `useExamTimer.ts` — saat timer < 60 detik, tambahkan random delay:

```typescript
// Di onExpire callback:
function handleAutoSubmit() {
    // Random delay 0-30 detik saat timer mendekati habis
    const jitter = Math.floor(Math.random() * 30000); // 0-30s
    setTimeout(() => {
        actuallySubmit();
    }, jitter);
}
```

**Impact**: Spread 500 submit over 30 detik → ~17 submit/detik instead of 500/detik.

### 5.3 Queue-Based Grading

**CRITICAL**: Jangan grade synchronous saat submit. Move `autoGrade()` ke queue job.

```php
// submitExam() — JANGAN panggil autoGrade() langsung
// Ganti menjadi:
public function submitExam(ExamAttempt $attempt, bool $isForceSubmit = false): void
{
    // ... persist answers (batch upsert) ...
    
    $attempt->update([
        'status' => ExamAttemptStatus::Submitted,
        'submitted_at' => now(),
        'is_force_submitted' => $isForceSubmit,
    ]);
    
    // Grade async via queue
    GradeExamJob::dispatch($attempt)->onQueue('default');
    
    $this->clearRedisKeys($attempt);
}
```

**Impact**: Submit time turun dari ~5-10s → ~0.5-1s per request. Grading diproses secara asynchronous.

### 5.4 Database Write Batching on Submit

Combined with §4.3 batch upsert, submit sekarang:
1. Redis GET (1 call)
2. Batch upsert answers (1 query instead of 40-50)
3. Update flags (1 query)
4. Update attempt status (1 query)
5. Dispatch grade job (1 Redis PUSH)
6. Redis DEL (3 calls)

**Total: 3 DB queries + 5 Redis calls. Down from 50-100 queries.**

### 5.5 ForceSubmitExpiredExams Command

**Current issue** (`ForceSubmitExpiredExams.php`):
```php
$expired = ExamAttempt::where('status', InProgress)
    ->with('examSession')
    ->get()  // Loads ALL into memory!
    ->filter(fn($a) => $a->isExpired());
```

**Problems**:
1. `->get()` loads semua in-progress attempts ke RAM
2. `->filter()` via PHP instead of SQL
3. No chunking — memory spike

**Optimized version**:
```php
public function handle(ExamAttemptService $attemptService): int
{
    $count = 0;
    
    ExamAttempt::where('status', ExamAttemptStatus::InProgress)
        ->with('examSession')
        ->chunkById(50, function ($attempts) use ($attemptService, &$count) {
            foreach ($attempts as $attempt) {
                if ($attempt->isExpired()) {
                    $attemptService->submitExam($attempt, true);
                    $count++;
                }
            }
        });
    
    $this->info("Force submitted {$count} expired exam(s).");
    return self::SUCCESS;
}
```

**Chunk size 50**: Dengan batch upsert, 50 attempts × 1 upsert query = manageable. Process 500 expired attempts dalam **~30-60 detik** (vs current yang bisa OOM atau take 10+ menit).

---

## 6. Frontend Optimization

### 6.1 Vite Bundle — Code Splitting

Current `vite.config.ts` has single entry `resources/js/app.ts`. Semua pages bundled together.

**Rekomendasi**: Inertia + Vue + Vite sudah auto code-split per page via dynamic imports. Verify di `app.ts`:
```typescript
// Pastikan resolveComponent menggunakan dynamic import:
resolve: (name) => {
    const pages = import.meta.glob('./pages/**/*.vue');
    return pages[`./pages/${name}.vue`]();
},
```

Ini sudah lazy-load non-exam pages secara otomatis.

### 6.2 Exam Payload Size

40-50 soal with images:
- Question content (HTML): ~2-5 KB per soal = ~100-250 KB
- Options per soal: ~0.5-1 KB = ~25-50 KB
- **JSON payload total: ~150-300 KB** (tanpa gambar)
- Images: served separately via `<img>` tags pointing to `/storage/...`

**Rekomendasi**: 
- Enable Brotli/gzip compression di Nginx (§8) — 300KB → ~50KB compressed
- Images: ensure proper sizing (max 800px width, WebP format, lazy loading)

### 6.3 Auto-Save Retry — Exponential Backoff

Current `useAutoSave.ts` sudah punya retry, tapi fixed interval 10s. Improve:

```typescript
// Exponential backoff: 2s → 4s → 8s (max 3 retries)
const backoffMs = Math.min(2000 * Math.pow(2, retryCount), 10000);
setTimeout(() => save(), backoffMs);
```

### 6.4 Static Asset Caching

Handle di Nginx config (§8). Vite sudah generate hashed filenames (`app-[hash].js`), jadi bisa set `Cache-Control: max-age=31536000`.

### 6.5 Gzip/Brotli

Covered in Nginx config §8. Expected savings: **60-80% reduction** pada text-based assets.

---

## 7. Load Testing Strategy

### 7.1 Tool: k6 (Recommended)

**Why k6**: Open source, scriptable JavaScript, CLI-based, low resource usage, proper distributed load testing. Better than Artillery (Node overhead) dan Laravel Stress (PHP-based, single threaded).

### 7.2 Install

```bash
# Linux/WSL
sudo gpg -k
sudo gpg --no-default-keyring --keyring /usr/share/keyrings/k6-archive-keyring.gpg --keyserver hkp://keyserver.ubuntu.com:80 --recv-keys C5AD17C747E3415A3642D57D77C6C491D6AC1D68
echo "deb [signed-by=/usr/share/keyrings/k6-archive-keyring.gpg] https://dl.k6.io/deb stable main" | sudo tee /etc/apt/sources.list.d/k6.list
sudo apt-get update && sudo apt-get install k6

# Windows
winget install k6 --source winget
```

### 7.3 Test Script (`tests/load/exam-scenario.js`)

```javascript
import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate, Trend } from 'k6/metrics';

const errorRate = new Rate('errors');
const autoSaveTime = new Trend('auto_save_duration');

export const options = {
    stages: [
        { duration: '1m', target: 100 },   // Ramp to 100
        { duration: '2m', target: 300 },   // Ramp to 300
        { duration: '2m', target: 500 },   // Ramp to 500
        { duration: '3m', target: 500 },   // Hold 500
        { duration: '1m', target: 0 },     // Ramp down
    ],
    thresholds: {
        http_req_duration: ['p(50)<200', 'p(95)<1000', 'p(99)<3000'],
        errors: ['rate<0.01'],  // <1% error rate
    },
};

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000';

export default function () {
    // 1. Login
    const loginRes = http.post(`${BASE_URL}/login`, {
        email: `student${__VU}@test.com`,
        password: 'password',
    });
    check(loginRes, { 'login ok': (r) => r.status === 200 || r.status === 302 });

    const cookies = loginRes.cookies;

    // 2. Start exam (atau resume)
    const startRes = http.get(`${BASE_URL}/siswa/ujian/1/start`, {
        cookies: cookies,
    });

    // 3. Auto-save loop (simulasi 30-detik interval, dipadatkan)
    for (let i = 0; i < 6; i++) {
        const answers = {};
        for (let q = 1; q <= 40; q++) {
            answers[q.toString()] = ['A', 'B', 'C', 'D'][Math.floor(Math.random() * 4)];
        }

        const saveRes = http.post(
            `${BASE_URL}/siswa/ujian/1/save-answers`,
            JSON.stringify({ answers, flags: [] }),
            {
                headers: { 'Content-Type': 'application/json' },
                cookies: cookies,
            }
        );

        autoSaveTime.add(saveRes.timings.duration);
        errorRate.add(saveRes.status !== 200);
        check(saveRes, { 'save ok': (r) => r.status === 200 });

        sleep(5);  // Simulate 5s between saves (compressed for testing)
    }

    // 4. Submit (dengan random jitter, simulasi thundering herd)
    sleep(Math.random() * 5);
    const submitRes = http.post(`${BASE_URL}/siswa/ujian/1/submit`, {}, {
        cookies: cookies,
    });
    check(submitRes, { 'submit ok': (r) => r.status === 200 || r.status === 302 });
}
```

### 7.4 Run Test

```bash
# Local development (reduced load)
k6 run --vus 50 --duration 2m tests/load/exam-scenario.js

# Full simulation
k6 run tests/load/exam-scenario.js

# Dengan environment variable
k6 run -e BASE_URL=http://192.168.1.100 tests/load/exam-scenario.js
```

### 7.5 Metrics to Monitor

| Metric | Target | Alert Threshold |
|---|---|---|
| Response time p50 | < 200ms | > 500ms |
| Response time p95 | < 1000ms | > 2000ms |
| Response time p99 | < 3000ms | > 5000ms |
| Error rate | < 1% | > 5% |
| MySQL active connections | < 100 | > 130 |
| PHP-FPM active workers | < 70 | > 85 |
| Redis memory | < 200MB | > 400MB |
| CPU usage | < 70% | > 90% |
| RAM usage | < 85% | > 95% |
| Disk I/O wait | < 30% | > 50% |

### 7.6 Development Laptop Setup

```bash
# 1. Seed test data: 500 student accounts + 1 exam session + 40 questions
php artisan db:seed --class=LoadTestSeeder

# 2. Run reduced load (laptop biasanya 8-16GB RAM)
k6 run --vus 20 --duration 1m tests/load/exam-scenario.js

# 3. Monitor via Laravel Pulse dan MySQL slow query
# Fokus pada query patterns, bukan absolute numbers
```

> [!NOTE]
> Laptop test tidak akan replicate server performance (beda CPU, RAM, disk). Tujuannya: validasi tidak ada error/crash, identifikasi N+1 queries, verify queue processing. Absolute latency numbers hanya valid dari server test.

---

## 8. Nginx Tuning

### Recommended `/etc/nginx/nginx.conf`

```nginx
worker_processes auto;  # = jumlah CPU core (20 pada Xeon)
worker_rlimit_nofile 65535;

events {
    worker_connections 2048;
    multi_accept on;
    use epoll;
}

http {
    # === Basic ===
    sendfile on;
    tcp_nopush on;
    tcp_nodelay on;
    types_hash_max_size 2048;
    server_tokens off;

    # === Timeouts ===
    keepalive_timeout 30;
    keepalive_requests 1000;
    client_body_timeout 15;
    client_header_timeout 15;
    send_timeout 15;

    # === Buffer Sizes ===
    client_body_buffer_size 16k;
    client_header_buffer_size 1k;
    large_client_header_buffers 4 8k;
    client_max_body_size 10m;  # Upload soal + gambar

    # === Gzip ===
    gzip on;
    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_min_length 256;
    gzip_types
        text/plain text/css text/javascript
        application/json application/javascript
        application/xml application/xml+rss
        image/svg+xml font/woff2;

    # === Brotli (jika module tersedia) ===
    # brotli on;
    # brotli_comp_level 6;
    # brotli_types text/plain text/css application/json application/javascript;

    # === Rate Limiting ===
    # Auto-save: max 3 req/s per IP (1 save per 30s, generous buffer)
    limit_req_zone $binary_remote_addr zone=autosave:10m rate=3r/s;
    # General API: max 10 req/s per IP
    limit_req_zone $binary_remote_addr zone=api:10m rate=10r/s;

    # === Upstream ===
    upstream php-fpm {
        server unix:/run/php/php8.3-fpm.sock;
    }
}
```

### Site Config (`/etc/nginx/sites-enabled/cbt.conf`)

```nginx
server {
    listen 80;
    server_name cbt.sekolah.sch.id;
    root /var/www/smk-lms/public;
    index index.php;

    # === Static Assets (Vite hashed files) ===
    location /build/ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    location ~* \.(jpg|jpeg|png|gif|webp|ico|svg|woff2|woff|ttf|css|js)$ {
        expires 30d;
        add_header Cache-Control "public";
        access_log off;
    }

    # === Storage (uploaded images) ===
    location /storage/ {
        expires 7d;
        add_header Cache-Control "public";
    }

    # === Auto-save Rate Limit ===
    location ~ ^/siswa/ujian/\d+/save-answers$ {
        limit_req zone=autosave burst=5 nodelay;
        try_files $uri /index.php?$query_string;
    }

    # === WebSocket (Reverb) ===
    location /app/ {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_read_timeout 86400;
        proxy_send_timeout 86400;
    }

    # === PHP-FPM ===
    location ~ \.php$ {
        fastcgi_pass php-fpm;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_buffer_size 16k;
        fastcgi_buffers 4 16k;
        fastcgi_connect_timeout 5;
        fastcgi_read_timeout 30;
        fastcgi_send_timeout 15;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # === FPM Status (internal only) ===
    location /fpm-status {
        allow 127.0.0.1;
        deny all;
        fastcgi_pass php-fpm;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

---

## 9. Monitoring Production

### 9.1 Laravel Pulse

```bash
php artisan vendor:publish --provider="Laravel\Pulse\PulseServiceProvider"
# Di .env production:
PULSE_ENABLED=true
```

Dashboard: `https://cbt.sekolah.sch.id/pulse` — shows slow queries, slow requests, exceptions, queues.

### 9.2 MySQL Slow Query Log

```ini
# Di my.cnf
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow.log
long_query_time = 1
log_queries_not_using_indexes = 1
```

### 9.3 PHP-FPM Status

```bash
# Akses via curl dari server
curl -s http://127.0.0.1:9001/fpm-status?full

# Key metrics: active_processes, idle_processes, listen_queue
```

### 9.4 Redis INFO

```bash
redis-cli INFO memory    # used_memory, fragmentation_ratio
redis-cli INFO stats     # connected_clients, keyspace_hits/misses
redis-cli INFO clients   # connected_clients
```

### 9.5 Disk I/O Monitoring

```bash
# Install sysstat
sudo apt install sysstat

# Monitor HDD I/O (critical bottleneck)
iostat -xz 5    # Report setiap 5 detik
# Watch: %iowait, avgqu-sz, await (ms per I/O op)
# HDD normal: await < 20ms. Alert jika > 50ms.
```

### 9.6 Alert Thresholds

| Metric | Warning | Critical | Action |
|---|---|---|---|
| CPU > 70% sustained | ⚠️ | CPU > 90% 🔴 | Check PHP-FPM workers, MySQL queries |
| RAM > 85% | ⚠️ | RAM > 95% 🔴 | Reduce PHP-FPM workers, check MySQL |
| Disk I/O wait > 30% | ⚠️ | > 50% 🔴 | Queue backup, reduce persist frequency |
| MySQL connections > 100 | ⚠️ | > 130 🔴 | Connection leak, check wait_timeout |
| PHP-FPM listen_queue > 0 | ⚠️ | > 10 🔴 | Workers saturated, increase max_children |
| Redis memory > 300MB | ⚠️ | > 450MB 🔴 | Key leak, check TTL |
| Auto-save p95 > 1s | ⚠️ | > 3s 🔴 | DB contention, check slow queries |
| Queue size > 1000 | ⚠️ | > 5000 🔴 | Workers can't keep up, add workers |

### 9.7 Simple Monitoring Script

```bash
#!/bin/bash
# /usr/local/bin/cbt-monitor.sh — jalankan via cron tiap 1 menit
echo "=== $(date) ===" >> /var/log/cbt-monitor.log
echo "PHP-FPM:" >> /var/log/cbt-monitor.log
curl -s http://127.0.0.1:9001/fpm-status | grep -E 'active|idle|listen' >> /var/log/cbt-monitor.log
echo "Redis:" >> /var/log/cbt-monitor.log
redis-cli INFO memory | grep used_memory_human >> /var/log/cbt-monitor.log
echo "MySQL:" >> /var/log/cbt-monitor.log
mysql -e "SHOW STATUS WHERE Variable_name IN ('Threads_connected','Threads_running','Slow_queries');" >> /var/log/cbt-monitor.log
echo "Disk:" >> /var/log/cbt-monitor.log
iostat -d 1 1 | tail -n +4 >> /var/log/cbt-monitor.log
```

---

## 10. Contingency Plan

### 10.1 Redis Crash → Fallback

**Scenario**: Redis process dies mid-exam.

**Impact**: Auto-save gagal, session hilang, queue berhenti.

**Mitigation**:
1. **Auto-save fallback**: Di `useAutoSave.ts`, jika save ke Redis gagal 3x, fallback ke localStorage + direct MySQL write:
   ```typescript
   // Setelah MAX_RETRIES tercapai:
   // Save ke localStorage sebagai last resort
   localStorage.setItem(`exam_backup_${examSessionId}`, JSON.stringify(answers));
   ```
2. **Session fallback**: `.env` production harus set `SESSION_DRIVER=database` (currently sudah jadi database karena .env override bug — tapi ini sebenarnya BAIK untuk fallback)
3. **Queue fallback**: `config/queue.php` sudah punya `failover` connection — set `QUEUE_CONNECTION=failover` yang fall back ke database driver
4. **Recovery**: `sudo systemctl restart redis-server` — exam state bisa di-recover dari client localStorage + MySQL persist terakhir
5. **Supervisor auto-restart**: Supervisor akan auto-restart queue workers setelah Redis kembali

### 10.2 MySQL Connection Exhausted

**Scenario**: `max_connections` (150) tercapai.

**Mitigation**:
1. **Backpressure di PHP-FPM**: `fastcgi_read_timeout = 30s` di Nginx akan timeout long-running requests
2. **Queue pause**: `php artisan queue:pause` — temporarily stop queue workers yang consume MySQL connections
3. **Kill idle connections**: 
   ```sql
   -- Cari dan kill sleeping connections > 30 detik
   SELECT id, time, state FROM information_schema.processlist WHERE command = 'Sleep' AND time > 30;
   -- Kill specific: KILL <id>;
   ```
4. **Reduce PHP-FPM workers sementara**: Edit pool config, `pm.max_children = 50`, then `sudo systemctl reload php8.3-fpm`

### 10.3 PHP-FPM Workers Habis → Degraded Mode

**Scenario**: Semua 90 workers busy, requests queue up.

**Priority order** (implement via Nginx rate limiting):
1. **P1**: `save-answers` — jawaban siswa HARUS tersimpan
2. **P2**: `submit` — siswa bisa submit
3. **P3**: `log-activity` — bisa di-drop tanpa impact fatal
4. **P4**: Semua endpoint non-exam — temporarily block

```nginx
# Emergency: block non-exam traffic
location / {
    # Uncomment during emergency:
    # return 503 "Sistem dalam mode darurat. Ujian tetap berjalan.";
    try_files $uri $uri/ /index.php?$query_string;
}
```

### 10.4 Server Lag Mid-Exam (Without Restart)

Immediate actions tanpa restart:
1. **Kill slow MySQL queries**: `SHOW PROCESSLIST;` → `KILL <id>` untuk queries > 30s
2. **Flush MySQL query cache**: N/A di MySQL 8, tapi bisa `FLUSH TABLES;`
3. **Temporarily stop PersistAnswersJob**: `php artisan queue:pause exam-persist` — Redis sudah menyimpan, MySQL persist bisa ditunda
4. **Drop non-critical Reverb events**: Stop `AnswerProgressUpdated` broadcasting sementara (tidak affect exam functionality, hanya teacher dashboard)
5. **Increase PHP-FPM timeout**: `request_terminate_timeout = 60s` → reload FPM

### 10.5 Emergency Kill Switch

```bash
#!/bin/bash
# /usr/local/bin/cbt-emergency.sh
echo "🚨 EMERGENCY MODE ACTIVATED at $(date)"

# 1. Pause non-essential queue processing
php /var/www/smk-lms/artisan queue:pause default

# 2. Keep only exam-persist queue running
# (answers must keep saving)

# 3. Stop Reverb (teacher dashboard real-time updates)
supervisorctl stop reverb

# 4. Reduce PHP-FPM workers
sed -i 's/pm.max_children = 90/pm.max_children = 50/' /etc/php/8.3/fpm/pool.d/www.conf
systemctl reload php8.3-fpm

# 5. Block non-exam routes via Nginx
cp /etc/nginx/sites-enabled/cbt-emergency.conf /etc/nginx/sites-enabled/cbt.conf
nginx -s reload

echo "⚡ Emergency mode active. Only exam endpoints operational."
echo "Run cbt-recovery.sh to restore normal operation."
```

---

## 11. Implementation Priority

### 🔴 MUST DO (Before Production)

| # | Item | Impact | Effort | Section |
|---|---|---|---|---|
| 1 | **Switch REDIS_CLIENT to phpredis** | 2-5x Redis performance | 5 min | §3 |
| 2 | **Fix SESSION_DRIVER .env bug** (duplicate, database overrides redis) | Session stability | 5 min | §3 |
| 3 | **Batch upsert di PersistAnswersJob** | 40x fewer queries | 30 min | §4.3 |
| 4 | **Batch upsert di submitExam** | 40x fewer queries on submit | 30 min | §4.4 |
| 5 | **Move autoGrade to queue job** | Submit time 10x faster | 1 hr | §5.3 |
| 6 | **Client-side submit stagger** | Prevent thundering herd | 30 min | §5.2 |
| 7 | **ForceSubmitExpiredExams chunking** | Prevent OOM | 15 min | §5.5 |
| 8 | **MySQL my.cnf tuning** | HDD I/O optimization critical | 30 min | §1 |
| 9 | **PHP-FPM config** | Worker count + static mode | 15 min | §2 |
| 10 | **Add composite indexes** | Query speed on hot tables | 10 min | §1 |
| 11 | **Laravel cache/route/config/view/event caching** | Boot time, memory | 5 min | §4.1 |
| 12 | **Nginx config (gzip, static caching, WebSocket proxy, rate limit)** | Network throughput | 30 min | §8 |
| 13 | **Supervisor queue worker config** | Reliable queue processing | 20 min | §4.2 |
| 14 | **Redis config (maxmemory, save off)** | Prevent HDD blocking | 10 min | §3 |

### 🟡 SHOULD DO (Before Scale to 500)

| # | Item | Impact | Effort | Section |
|---|---|---|---|---|
| 15 | **Cache exam attempt in Redis** | 6x fewer DB queries on auto-save | 1 hr | §4.6 |
| 16 | **MySQL slow query log** | Debug production bottlenecks | 10 min | §9.2 |
| 17 | **PHP-FPM status page + monitoring script** | Visibility | 15 min | §9 |
| 18 | **Auto-save exponential backoff** | Graceful degradation | 15 min | §6.3 |
| 19 | **Contingency scripts (emergency, recovery)** | Safety net | 1 hr | §10 |
| 20 | **Load test with k6** | Validate all optimizations | 2 hr | §7 |

### 🟢 NICE TO HAVE (Post-Production Optimization)

| # | Item | Impact | Effort | Section |
|---|---|---|---|---|
| 21 | **localStorage fallback on Redis failure** | Resilience | 1 hr | §10.1 |
| 22 | **Laravel Pulse setup** | Ongoing monitoring | 30 min | §9.1 |
| 23 | **Brotli compression** | Better than gzip | 30 min | §8 |
| 24 | **Image optimization (WebP, lazy load)** | Exam load time | 2 hr | §6.2 |
| 25 | **Evaluate Laravel Octane** | 2-3x throughput | 1 week | §4.7 |

---

> [!IMPORTANT]
> Items 1-14 (🔴 MUST DO) harus selesai **sebelum** ujian pertama di production. Estimasi total effort: **~6 jam** kerja developer. Items 15-20 (🟡 SHOULD DO) sebaiknya selesai sebelum scale ke 500 concurrent. Items 21-25 bisa dilakukan iteratif setelah production berjalan.
