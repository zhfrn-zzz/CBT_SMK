# Performance Audit — SMK LMS + CBT System

**Date**: 2026-03-23
**Target**: 500+ concurrent CBT users on Intel Xeon 20-core, 16GB RAM, HDD
**Total Files Audited**: 48 controllers, 5 middleware, 179 JS chunks, 10 composables, 15 type files

---

## Table of Contents

1. [Frontend Bundle Analysis](#1-frontend-bundle-analysis)
2. [Inertia Page Props Audit](#2-inertia-page-props-audit)
3. [Database Queries per Request](#3-database-queries-per-request)
4. [Eager Loading Audit](#4-eager-loading-audit)
5. [Redis Usage Audit](#5-redis-usage-audit)
6. [Vue Component Audit](#6-vue-component-audit)
7. [Middleware Overhead](#7-middleware-overhead)
8. [Asset Loading](#8-asset-loading)
9. [Prioritized Findings Summary](#9-prioritized-findings-summary)

---

## 1. Frontend Bundle Analysis

### Overview

| Metric | Value |
|--------|-------|
| Total raw size | 1,789 KB |
| Total gzip size | 592 KB |
| Total chunks | 179 files |
| CSS bundle | 123.82 KB (gzip: 19.75 KB) |
| Build time | 45.32s |
| Modules transformed | 3,516 |

### Top 10 Largest Chunks

| # | Chunk | Raw (KB) | Gzip (KB) | Notes |
|---|-------|----------|-----------|-------|
| 1 | `QuestionForm.vue` | 381.05 | 121.02 | **LARGEST** — Tiptap editor bundled here |
| 2 | `app.js` (vendor core) | 366.22 | 121.73 | Vue + Inertia + reka-ui + core |
| 3 | `app.css` | 123.82 | 19.75 | Tailwind CSS output |
| 4 | `AppLayout.vue` | 103.65 | 30.68 | Sidebar + nav + shared UI |
| 5 | `index.js` (reka-ui select) | 49.36 | 17.15 | Select/Combobox primitives |
| 6 | `SelectValue.vue` | 30.68 | 9.84 | Select component internals |
| 7 | `index.js` (reka-ui) | 30.04 | 9.47 | More primitives |
| 8 | `ExamInterface.vue` | 26.46 | 8.97 | CBT exam page |
| 9 | `Proctor.vue` | 15.96 | 5.33 | Real-time monitoring |
| 10 | `TwoFactor.vue` | 14.59 | 4.87 | 2FA setup page |

### Code Splitting Status

- **Pages**: ✅ All lazy-loaded via `import.meta.glob()` dynamic imports in `app.ts`
- **Vendor core**: ✅ Separated into `app.js` (366 KB gzip 121 KB)
- **Per-page chunks**: ✅ Each page gets its own chunk

### Critical Findings

#### F1.1 — Tiptap Editor NOT Lazy-Loaded

| Field | Value |
|-------|-------|
| **File** | `resources/js/components/Exam/TiptapEditor.vue` |
| **Current behavior** | Tiptap (@tiptap/vue-3, @tiptap/starter-kit, @tiptap/extension-image) imported synchronously |
| **Impact** | 381 KB chunk loaded for ANY page using QuestionForm, even when rich text editor not visible |
| **Recommended fix** | Use `defineAsyncComponent()` or dynamic `import()` to lazy-load Tiptap only when editor mounts |
| **Estimated impact** | **HIGH** — saves ~121 KB gzip for pages that don't use the editor |
| **Effort** | Easy |

#### F1.2 — Potentially Unused Dependencies

| Package | Size Estimate | Status |
|---------|--------------|--------|
| `@vueuse/core` | ~60 KB | ✅ Used by shadcn-vue components — **NOT unused** |
| `tw-animate-css` | ~5 KB | ✅ Used in `app.css` line 3 — **NOT unused** |
| `@tanstack/vue-table` | ~73 KB | ⚠️ Only `valueUpdater` utility imported — full library bundled? |

#### F1.3 — Axios Usage Without Direct Dependency

| Field | Value |
|-------|-------|
| **Files** | `useAutoSave.ts:2`, `useExamSecurity.ts:2`, `ExamInterface.vue:25` |
| **Current behavior** | 5 files import `axios` but it's not in `package.json` dependencies — loaded transitively via Inertia |
| **Recommended fix** | Replace `axios.post()` with `router.post()` (Inertia) for consistency, or add axios as explicit dependency |
| **Estimated impact** | **LOW** — functional but fragile |
| **Effort** | Easy |

---

## 2. Inertia Page Props Audit

### Per-Controller Props Analysis

#### CRITICAL: Over-Fetching

##### F2.1 — Guru/ExamSessionController::show()

| Field | Value |
|-------|-------|
| **File** | `app/Http/Controllers/Guru/ExamSessionController.php:108-123` |
| **Current behavior** | Loads `questionBank.questions` (ALL questions with full content/options), `attempts.user` (all attempts unbounded) |
| **Props sent** | Full exam session with entire question bank + all attempt records |
| **Estimated size** | 300-500 KB for exam with 100 questions |
| **Recommended fix** | Load only question metadata (id, order, type). Lazy-load question content. Paginate attempts. |
| **Estimated impact** | **HIGH** |
| **Effort** | Medium |

##### F2.2 — Admin/ClassroomController::show()

| Field | Value |
|-------|-------|
| **File** | `app/Http/Controllers/Admin/ClassroomController.php:93-141` |
| **Current behavior** | `availableStudents` (line 101-106), `availableTeachers` (line 124-128), `availableSubjects` (line 131-133) — all `SELECT *` with NO limits |
| **Props sent** | Could be 500+ student records, all teachers, all subjects |
| **Recommended fix** | Add `->limit(50)` or implement server-side search/autocomplete |
| **Estimated impact** | **HIGH** |
| **Effort** | Medium |

##### F2.3 — Admin/UserController::create()

| Field | Value |
|-------|-------|
| **File** | `app/Http/Controllers/Admin/UserController.php:126-135` |
| **Current behavior** | `academicYears`, `departments`, `classrooms`, `subjects` — all full SELECT * |
| **Recommended fix** | Select only `id, name` columns. Add `->limit()` |
| **Estimated impact** | **MEDIUM** |
| **Effort** | Easy |

##### F2.4 — Admin/UserController::index()

| Field | Value |
|-------|-------|
| **File** | `app/Http/Controllers/Admin/UserController.php:28-108` |
| **Current behavior** | Loads `classrooms.department`, `teachingAssignments.classroom.subject` for each user in paginated result |
| **Props sent** | 15 users × full relation graphs = ~150-200 KB |
| **Recommended fix** | Select only needed columns: `->with('classrooms:id,name,department_id')` |
| **Estimated impact** | **MEDIUM** |
| **Effort** | Easy |

#### Pagination Status

| Category | Paginated ✅ | Unpaginated ❌ | Filter Data Only |
|----------|-------------|---------------|-----------------|
| Admin Controllers | 6 | 4 | 1 |
| Guru Controllers | 8 | 10 | 7 |
| Siswa Controllers | 4 | 3 | 3 |
| Settings/Other | 2 | 1 | 7 |
| **Total** | **20** | **18** | **18** |

#### Dashboard Props Size Estimates

| Dashboard | Estimated Size | Cached? | TTL |
|-----------|---------------|---------|-----|
| Admin | 50-80 KB | ✅ | 300s |
| Guru | 400-600 KB | ✅ | 300s |
| Siswa | 200-300 KB | ✅ | 300s |
| Exam Interface (start) | 50-100 KB | ❌ | N/A |

---

## 3. Database Queries per Request

### Critical Endpoints

#### Admin Dashboard (`/admin/dashboard`)

| Query | Description | Cached? |
|-------|-------------|---------|
| 1 | `User::count()` | ✅ 300s |
| 2 | `User::where('role', Guru)->count()` | ✅ 300s |
| 3 | `User::where('role', Siswa)->count()` | ✅ 300s |
| 4 | `ExamSession::where('status', Active)->count()` | ✅ 300s |
| 5 | `ExamSession` date range count | ✅ 300s |
| 6 | `ExamSession::with(['subject', 'user'])` recent 5 | ✅ 300s |
| 7 | `Announcement::with('user')` recent 3 | ✅ 300s |
| 8 | `AuditLog::with('user')` recent 10 | ✅ 300s |
| 9 | `ExamSession::pluck('name')` active | ✅ 300s |

**Total on cache miss: ~9 queries. On cache hit: 1 (cache get). Status: ✅ GOOD**

#### Siswa Dashboard (`/siswa/dashboard`)

| Query | Description | Issue |
|-------|-------------|-------|
| 1-14 | See detailed report | 12-14 queries on cache miss |
| ⚠️ | `Announcement::published()->forStudent()` line 98-102 | Missing `with('user')` eager load |
| ⚠️ | `Attendance::whereIn()` line 106-109 | Missing `with('classroom', 'subject')` |

**Total on cache miss: ~14 queries. On cache hit: 1. Status: ⚠️ 2 N+1 risks**

#### Guru Dashboard (`/guru/dashboard`)

| Query | Description | Issue |
|-------|-------------|-------|
| 1-15 | See detailed report | 13-15 queries on cache miss |
| ⚠️ | `Announcement::take(3)->get()` line 89-92 | Missing `with('user')` |

**Total on cache miss: ~15 queries. On cache hit: 1. Status: ⚠️ Minor N+1**

#### Exam Auto-Save (`/siswa/ujian/{id}/save-answers`) — MOST CRITICAL PATH

| Query | Description | Issue |
|-------|-------------|-------|
| 1 | `ExamAttempt::where()->with('examSession')->first()` | ✅ Cached 5 min |
| 2 | `$attempt->attemptQuestions()->count()` line 258 | ❌ **N+1 — runs EVERY auto-save** |
| 3 | Redis GET/SET | ✅ Fast |

**Total: 1-2 queries per request. At 500 students × every 30s = 17 requests/sec. Line 258 adds 17 unnecessary queries/sec = 1,020/min on HDD.**

##### F3.1 — Extra count() on every auto-save

| Field | Value |
|-------|-------|
| **File** | `app/Http/Controllers/Siswa/ExamController.php:258` |
| **Current behavior** | `$attempt->attemptQuestions()->count()` called on every auto-save request |
| **Recommended fix** | Cache question count in Redis when exam starts, or include in cached attempt data |
| **Estimated impact** | **CRITICAL** — eliminates 1,020 queries/min at peak load |
| **Effort** | Easy |

#### Exam Activity Log (`/siswa/ujian/{id}/log-activity`)

| Query | Description | Issue |
|-------|-------------|-------|
| 1 | `ExamAttempt::where()->first()` | OK |
| 2 | `ExamActivityLog::create()` | OK |
| 3 | `$attempt->load('examSession')` | 1 query |
| 4 | `ExamActivityLog::where()->count()` line 326 | ❌ Count on every log event |
| 5 | `ExamActivityLog::where('tab_switch')->count()` line 341-343 | ❌ Count on every log event |

##### F3.2 — Activity log counting queries per event

| Field | Value |
|-------|-------|
| **File** | `app/Http/Controllers/Siswa/ExamController.php:326, 341-343` |
| **Current behavior** | Counts total events and tab switches on every activity log call |
| **Recommended fix** | Use Redis atomic counters for violation tracking |
| **Estimated impact** | **HIGH** — reduces 2 queries per activity event |
| **Effort** | Easy |

#### Grading Index (`/guru/penilaian`)

| Query | Description | Issue |
|-------|-------------|-------|
| 1 | Paginated exam sessions with counts | ✅ withCount |
| 2-31 | Transform loop: 2 queries per session × 15 per page | ❌ **N+1** |

##### F3.3 — GradingController::index transform N+1

| Field | Value |
|-------|-------|
| **File** | `app/Http/Controllers/Guru/GradingController.php:45-59` |
| **Current behavior** | For each paginated exam session (15/page): queries `attempts()->pluck('id')` then `StudentAnswer::whereIn()->count()` |
| **Total queries** | 15 × 2 = 30 extra queries per page load |
| **Recommended fix** | Refactor to single JOIN subquery or `withCount` with conditions |
| **Estimated impact** | **HIGH** — 50 teachers × 30 queries = 1,500 queries at peak |
| **Effort** | Medium |

---

## 4. Eager Loading Audit

### N+1 Query Issues Found

| # | File | Line | Relation | Context | Severity |
|---|------|------|----------|---------|----------|
| 1 | `Siswa/ExamController.php` | 258 | `attemptQuestions()->count()` | Every auto-save | **CRITICAL** |
| 2 | `Guru/GradingController.php` | 45-59 | `attempts()->pluck()` + `StudentAnswer::whereIn()` | Per exam session in loop | **HIGH** |
| 3 | `Siswa/ExamController.php` | 326, 341 | `ExamActivityLog::where()->count()` | Per activity event | **HIGH** |
| 4 | `Siswa/DashboardController.php` | 98-102 | Announcements missing `with('user')` | Dashboard load | **MEDIUM** |
| 5 | `Guru/DashboardController.php` | 89-92 | Announcements missing `with('user')` | Dashboard load | **MEDIUM** |
| 6 | `Siswa/DashboardController.php` | 106-109 | Attendance missing `with('classroom', 'subject')` | Dashboard load | **MEDIUM** |

### Proper Eager Loading (Verified ✅)

| File | Relations | Status |
|------|-----------|--------|
| `Siswa/ExamController.php` index | `subject`, `attempts` filtered by user | ✅ |
| `Guru/ExamSessionController.php` index | `subject`, `classrooms.department`, `user` | ✅ |
| `Guru/GradingController.php` manualGrading | `question.options` | ✅ |
| `Admin/DashboardController.php` | `ExamSession::with(['subject', 'user'])` | ✅ |
| `ForumController.php` | `threads` paginated, `user` eager loaded | ✅ |
| `Guru/MaterialController.php` | Service returns paginated materials | ✅ |

---

## 5. Redis Usage Audit

### ❌ CRITICAL: .env Configuration Conflict

##### F5.1 — Duplicate SESSION_DRIVER overrides Redis with Database

| Field | Value |
|-------|-------|
| **File** | `.env:29 and :33` |
| **Current behavior** | Line 29: `SESSION_DRIVER=redis` → Line 33: `SESSION_DRIVER=database` — **last value wins, sessions use DATABASE not Redis** |
| **Impact** | Every session read/write hits HDD instead of Redis. At 500+ users, each request = 1 session DB query = massive HDD I/O |
| **Recommended fix** | Remove line 33 (`SESSION_DRIVER=database`) |
| **Estimated impact** | **CRITICAL** — single biggest performance win possible |
| **Effort** | Trivial |

### Current Redis Usage

| Key Pattern | Location | TTL | Purpose |
|-------------|----------|-----|---------|
| `dashboard:admin` | `Admin/DashboardController.php:23` | 300s | Admin dashboard cache |
| `dashboard:guru:{id}` | `Guru/DashboardController.php:32` | 300s | Per-guru dashboard cache |
| `dashboard:siswa:{id}` | `Siswa/DashboardController.php:31` | 300s | Per-student dashboard cache |
| `user:{id}:unread_notifications` | `NotificationController.php:97` | 60s | Notification count |
| `calendar:{key}` | `CalendarEventController.php:30` | 600s | Calendar events |
| `exam_session:{attemptId}:session_id` | `Siswa/ExamController.php:171` | 86400s | Single-session enforcement |
| `exam:{sessionId}:student:{userId}:answers` | `ExamAttemptService.php:221` | 86400s | Answer buffer |
| `exam:{sessionId}:student:{userId}:last_save` | `ExamAttemptService.php:223` | 86400s | Last save timestamp |
| `exam:{sessionId}:student:{userId}:flags` | `ExamAttemptService.php:225` | 86400s | Flagged questions |

### Data NOT Cached (Should Be)

##### F5.2 — Sidebar/Permission Data Not Cached

| Field | Value |
|-------|-------|
| **Current behavior** | User classroom lists, permission checks queried fresh every relevant request |
| **Recommended fix** | Cache `user:{id}:classrooms` and `user:{id}:permissions` with 600s TTL, invalidate on role change |
| **Estimated impact** | **MEDIUM** |
| **Effort** | Medium |

##### F5.3 — Grading Data Not Cached

| Field | Value |
|-------|-------|
| **File** | `Guru/GradingController.php:31-63` |
| **Current behavior** | 30+ queries per page load, NOT cached |
| **Recommended fix** | Cache grading index per guru with 120s TTL, invalidate on grade submission |
| **Estimated impact** | **HIGH** |
| **Effort** | Medium |

##### F5.4 — Question Bank Queries Not Cached

| Field | Value |
|-------|-------|
| **Current behavior** | Question bank listings queried fresh every request |
| **Recommended fix** | Cache per-guru question bank list with 300s TTL |
| **Estimated impact** | **MEDIUM** |
| **Effort** | Easy |

### Config File Defaults

| Config | Default if env missing | File |
|--------|----------------------|------|
| `CACHE_STORE` | `database` | `config/cache.php:18` |
| `SESSION_DRIVER` | `database` | `config/session.php:21` |
| `QUEUE_CONNECTION` | `database` | `config/queue.php:16` |

**Risk**: If `.env` is missing or Redis is down, system silently falls back to database — catastrophic on HDD.

---

## 6. Vue Component Audit

### Re-Render Risks

#### F6.1 — Deep Watcher on Exam Answers

| Field | Value |
|-------|-------|
| **File** | `resources/js/composables/useExamState.ts:64-86` |
| **Current behavior** | `watch(() => ({ ...state.answers }), ..., { deep: true })` — creates new object reference on every render, deep watches all answers |
| **Impact** | With 100+ questions, frequent re-computation + localStorage writes |
| **Recommended fix** | Debounce the watch callback (300ms) or watch specific answer keys |
| **Estimated impact** | **MEDIUM** |
| **Effort** | Easy |

### Expensive Computed Properties

| File | Line | Expression | Items | Risk |
|------|------|-----------|-------|------|
| `Guru/Presensi/Show.vue` | 75-76 | `.filter(r => r.status === 'hadir')` | < 500 | ✅ LOW |
| `Siswa/Nilai/Show.vue` | 39-41 | Two `.reduce()` + one `.filter()` on answers | 30-100 | ✅ LOW |

### Large Components Needing Decomposition

| Component | LOC | Risk | Notes |
|-----------|-----|------|-------|
| `Guru/Ujian/Proctor.vue` | 514 | ⚠️ MEDIUM | WebSocket + many computed properties |
| `Siswa/Ujian/ExamInterface.vue` | 433 | ⚠️ MEDIUM | Timer + auto-save + security + answers |
| `Admin/Users/Create.vue` | 406 | ⚠️ MEDIUM | Cascading dropdowns |

##### F6.2 — ExamInterface.vue Should Be Decomposed

| Field | Value |
|-------|-------|
| **File** | `resources/js/pages/Siswa/Ujian/ExamInterface.vue` |
| **Current behavior** | 433 LOC single component handling: timer, auto-save, security, question display, navigation, answer state |
| **Recommended fix** | Extract into: ExamTimer.vue, ExamQuestion.vue, ExamNavigation.vue |
| **Estimated impact** | **MEDIUM** — reduces re-render scope, improves maintainability |
| **Effort** | Medium |

### Lazy-Loading Status for Heavy Components

| Component | Lazy-Loaded? | Recommendation |
|-----------|-------------|----------------|
| Tiptap Editor | ❌ No | `defineAsyncComponent()` |
| TanStack Table | ✅ N/A — only utils imported | OK |
| Calendar components | ✅ N/A — uses native HTML5 | OK |
| InputOTP (reka-ui) | ✅ Per-page chunk | OK |

---

## 7. Middleware Overhead

### Full Middleware Stack (per web request)

| Order | Middleware | DB Queries | Redis Ops | Notes |
|-------|-----------|-----------|-----------|-------|
| 1 | `EncryptCookies` | 0 | 0 | Crypto only |
| 2 | `HandleAppearance` | 0 | 0 | Cookie read |
| 3 | `HandleInertiaRequests` | 1 (user) + 0-1 (notifications) | 0-1 | Notifications cached 60s |
| 4 | `AddLinkHeadersForPreloadedAssets` | 0 | 0 | Header setup |
| 5 | `SecurityHeaders` | 0 | 0 | Header setup |
| 6 | `AuthenticateSession` | 0 | 0 | Session-based |
| 7 | `RoleMiddleware` (route-specific) | 0 | 0 | In-memory enum check |
| 8 | `SingleSessionExam` (route-specific) | 0-1 | 0-2 | Only during active exam |

### HandleInertiaRequests Shared Data

| Data | Source | Query Cost |
|------|--------|-----------|
| `auth.user` | `$request->user()` | 1 query (session-based, Laravel caches within request) |
| `auth.user.avatar` | `photo_url` accessor | 0 (computed from user model) |
| `auth.unread_notifications_count` | `Cache::remember()` 60s | 0-1 (cached) |
| `sidebarOpen` | Cookie | 0 |
| `flash` | Session | 0 |

**Total middleware cost: 1-2 DB queries + 0-2 Redis ops per request. Status: ✅ ACCEPTABLE**

##### F7.1 — SingleSessionExam Query During Active Exam

| Field | Value |
|-------|-------|
| **File** | `app/Http/Middleware/SingleSessionExam.php:22` |
| **Current behavior** | `$user->activeExamAttempt()` — 1 DB query on every request during exam |
| **Recommended fix** | Cache active attempt ID in Redis for exam duration |
| **Estimated impact** | **LOW** — only affects exam takers, 1 query overhead |
| **Effort** | Easy |

---

## 8. Asset Loading

### Browser Caching

##### F8.1 — No Cache-Control Headers for Static Assets

| Field | Value |
|-------|-------|
| **File** | `app/Http/Middleware/SecurityHeaders.php` — only security headers, no cache headers |
| **Current behavior** | No `Cache-Control`, `ETag`, or `Last-Modified` headers. Browser re-validates every request. |
| **Impact** | Every page load re-downloads CSS/JS. At 500 users: 500 × ~600 KB = 300 MB unnecessary network traffic per page |
| **Recommended fix** | Add `Cache-Control: public, max-age=31536000, immutable` for Vite hashed assets in web server config (Nginx/Apache) |
| **Estimated impact** | **HIGH** — major bandwidth and load time reduction |
| **Effort** | Easy (web server config) |

### Font Loading

| Aspect | Status |
|--------|--------|
| Preconnect | ✅ `<link rel="preconnect" href="https://fonts.bunny.net">` in `app.blade.php:40` |
| External font | ⚠️ Depends on `fonts.bunny.net` — adds ~100ms latency on first load |
| FOUT/FOIT | ⚠️ No `font-display: swap` explicitly set (depends on Bunny CDN default) |

##### F8.2 — External Font Dependency

| Field | Value |
|-------|-------|
| **File** | `resources/views/app.blade.php:40-41` |
| **Current behavior** | Fonts loaded from external CDN (fonts.bunny.net) |
| **Recommended fix** | Self-host fonts in `public/fonts/` for production — eliminates external dependency and DNS lookup |
| **Estimated impact** | **MEDIUM** — saves ~100-200ms on first load |
| **Effort** | Easy |

### Image Optimization

##### F8.3 — No Image Optimization Strategy

| Field | Value |
|-------|-------|
| **Current behavior** | No `loading="lazy"` on images, no WebP serving, no responsive images (`srcset`) |
| **Files affected** | `components/Exam/QuestionCard.vue` (exam media), `components/ui/avatar/AvatarImage.vue`, `components/UserInfo.vue` |
| **Recommended fix** | Add `loading="lazy"` to all non-critical images. Serve WebP format from storage. |
| **Estimated impact** | **MEDIUM** — reduces initial page weight for image-heavy pages |
| **Effort** | Easy |

### CSS Pipeline

| Aspect | Status |
|--------|--------|
| Tailwind v4 JIT | ✅ Efficient, tree-shaken |
| CSS output | 123.82 KB raw / 19.75 KB gzip |
| Critical CSS extraction | ❌ Not configured |
| Compression (gzip/brotli) | ❌ Not configured at app level (web server responsibility) |

### Service Worker / PWA

| Aspect | Status |
|--------|--------|
| Service Worker | ❌ None |
| Manifest | ❌ None |
| Offline support | ❌ None |

##### F8.4 — No Service Worker for Exam Resilience

| Field | Value |
|-------|-------|
| **Current behavior** | No service worker. If network drops during exam, no offline fallback. |
| **Recommended fix** | Add service worker to cache exam assets and queue auto-save requests for offline resilience |
| **Estimated impact** | **MEDIUM** — critical for exam reliability |
| **Effort** | Hard |

---

## 9. Prioritized Findings Summary

### CRITICAL (Fix Immediately)

| ID | Finding | File:Line | Impact | Effort |
|----|---------|-----------|--------|--------|
| F5.1 | `.env` duplicate `SESSION_DRIVER=database` overrides Redis | `.env:33` | Sessions hit HDD instead of Redis for ALL 500+ users | Trivial |
| F3.1 | `attemptQuestions()->count()` on every auto-save | `Siswa/ExamController.php:258` | 1,020 unnecessary queries/min at peak | Easy |
| F1.1 | Tiptap editor not lazy-loaded (381 KB chunk) | `components/Exam/TiptapEditor.vue` | 121 KB gzip loaded unnecessarily | Easy |

### HIGH (Fix This Sprint)

| ID | Finding | File:Line | Impact | Effort |
|----|---------|-----------|--------|--------|
| F3.3 | GradingController N+1: 30 queries/page | `Guru/GradingController.php:45-59` | 1,500 queries at peak with 50 teachers | Medium |
| F3.2 | Activity log counting 2 extra queries/event | `Siswa/ExamController.php:326,341` | Adds HDD I/O during exam | Easy |
| F2.1 | ExamSession::show() loads ALL questions | `Guru/ExamSessionController.php:108-123` | 300-500 KB props | Medium |
| F2.2 | Classroom::show() unbounded available lists | `Admin/ClassroomController.php:93-141` | 500+ records in props | Medium |
| F8.1 | No Cache-Control headers for static assets | Web server config | 300 MB unnecessary traffic at peak | Easy |
| F5.3 | Grading data not cached | `Guru/GradingController.php:31-63` | 30+ queries per uncached request | Medium |

### MEDIUM (Optimize Next)

| ID | Finding | File:Line | Impact | Effort |
|----|---------|-----------|--------|--------|
| F2.3 | UserController::create() full SELECT on all tables | `Admin/UserController.php:126-135` | Unnecessary data transfer | Easy |
| F2.4 | UserController::index() full relation graphs | `Admin/UserController.php:28-108` | 150-200 KB per page | Easy |
| F5.2 | Sidebar/permission data not cached | Multiple controllers | Repeated queries | Medium |
| F5.4 | Question bank queries not cached | Question controllers | Fresh queries every load | Easy |
| F6.1 | Deep watcher on exam answers | `composables/useExamState.ts:64-86` | Frequent localStorage writes | Easy |
| F6.2 | ExamInterface.vue 433 LOC single component | `pages/Siswa/Ujian/ExamInterface.vue` | Re-render scope too wide | Medium |
| F8.2 | External font dependency | `views/app.blade.php:40-41` | 100-200ms first load penalty | Easy |
| F8.3 | No image lazy loading | `QuestionCard.vue`, `AvatarImage.vue` | Unnecessary image downloads | Easy |
| F4.1 | Siswa dashboard announcements missing eager load | `Siswa/DashboardController.php:98-102` | N+1 on cache miss | Easy |
| F4.2 | Guru dashboard announcements missing eager load | `Guru/DashboardController.php:89-92` | N+1 on cache miss | Easy |
| F4.3 | Siswa dashboard attendance missing eager load | `Siswa/DashboardController.php:106-109` | N+1 on cache miss | Easy |

### LOW (Nice to Have)

| ID | Finding | File:Line | Impact | Effort |
|----|---------|-----------|--------|--------|
| F1.3 | Axios imported without explicit dependency | `useAutoSave.ts:2`, etc. | Fragile transitive dependency | Easy |
| F7.1 | SingleSessionExam DB query per request | `SingleSessionExam.php:22` | 1 query/request during exam | Easy |
| F8.4 | No service worker for offline exam resilience | Project root | No offline fallback | Hard |

---

## Appendix A: Missing Database Indexes

| Table | Column(s) | Migration File | Note |
|-------|-----------|---------------|------|
| `announcements` | `user_id` | `2026_03_18_030002_create_announcements_table.php` | `foreignId()` implicit, but explicit safer on HDD |
| `discussion_replies` | `user_id` | `2026_03_18_030001_create_discussion_replies_table.php` | Same |

**Note**: The `2026_03_23_072331_add_performance_indexes.php` migration already covers the most critical indexes (`student_answers`, `exam_activity_logs`, `exam_attempts`).

## Appendix B: Estimated Performance Impact at 500 Concurrent Users

### Current State (Worst Case)

| Metric | Current | After Fixes |
|--------|---------|-------------|
| Session queries/min | 500 × 2/min = 1,000 (HDD!) | 0 (Redis) |
| Auto-save extra queries/min | 1,020 | 0 (cached) |
| Activity log extra queries/min | ~500 | 0 (Redis counters) |
| Grading queries/teacher/page | 30 | 2-3 |
| Static asset re-downloads/page | 600 KB × 500 | 0 (cached) |
| Tiptap bundle waste | 121 KB gzip × pages | 0 (lazy-loaded) |

### Estimated Total Savings

- **DB queries**: ~2,500-3,000 fewer queries/min at peak
- **HDD I/O**: ~60-70% reduction from session driver fix alone
- **Network**: ~300 MB/min less at peak from asset caching
- **Frontend**: ~121 KB gzip less per user from Tiptap lazy-load
