# Phase 4: Analytics & Polish — Detailed Implementation Plan

## Context

Phase 1-3 of the SMK LMS + CBT system are complete (25 models, 10 services, ~80 Vue pages). Phase 4 adds analytics, notifications, audit trail, and data exchange features. The plan will be written to `docs/phase4-plan.md` as requested.

This plan covers all Phase 4 tasks with detailed migrations, services, controllers, Vue pages, algorithms, acceptance criteria, and edge cases. The output is a single `docs/phase4-plan.md` file — no code implementation.

---

## Implementation Order

1. **Task 4.3 (Audit)** first — infrastructure trait used across other tasks
2. **Task 4.2 (Notifications)** second — notification classes needed before analytics/exchange can trigger them
3. **Task 4.1 (Analytics + KD)** third — core analytics features
4. **Task 4.4 (Data Exchange)** last — builds on all other data

## Package Installations

```bash
npx shadcn-vue@latest add chart-bar chart-line     # shadcn-vue charts (uses Chart.js under the hood)
composer require barryvdh/laravel-dompdf            # PDF generation (pure PHP, no binary dependency)
php artisan notifications:table && php artisan migrate  # Laravel notifications table
```

**Chart choice**: shadcn-vue `chart` components — follows existing "no external UI libraries" rule, auto-configured for Tailwind 4 + dark mode.
**PDF choice**: `barryvdh/laravel-dompdf` — pure PHP (no wkhtmltopdf binary needed on production server), sufficient for text-heavy exam PDF layouts.
**Audit choice**: Custom `Auditable` trait — simpler than `owen-it/laravel-auditing`, full control over schema.

---

## Task 4.3: Audit Trail & System

### 4.3.1 Migration: `audit_logs`

```sql
audit_logs
├── id (bigint, PK)
├── user_id (FK → users, nullable)       -- null = system action (queue/cron)
├── action (varchar 50)                   -- created, updated, deleted, login, export, import
├── auditable_type (varchar 100, nullable) -- e.g. App\Models\ExamSession
├── auditable_id (bigint, nullable)
├── old_values (json, nullable)           -- only changed fields, not entire record
├── new_values (json, nullable)
├── ip_address (varchar 45, nullable)
├── user_agent (varchar 500, nullable)
├── description (text, nullable)          -- human-readable summary
├── created_at (timestamp)                -- NO updated_at, logs are immutable
├── INDEX(user_id, created_at)
├── INDEX(auditable_type, auditable_id)
├── INDEX(action)
├── INDEX(created_at)
```

### 4.3.2 Model: `AuditLog`

- `$timestamps = false`, only `created_at` via `const CREATED_AT = 'created_at'`
- Casts: `old_values` => array, `new_values` => array
- Relationship: `user(): BelongsTo`

### 4.3.3 Trait: `app/Traits/Auditable.php`

Hooks into Eloquent `created`, `updated`, `deleted` events via `bootAuditable()`.

**On created**: log `new_values` = all attributes (excluding sensitive)
**On updated**: log `old_values` = original values of changed fields, `new_values` = dirty fields only. Skip if no auditable changes.
**On deleted**: log `old_values` = all attributes

**Excluded fields** (never logged): `password`, `remember_token`, `two_factor_secret`, `two_factor_recovery_codes`. Models can add custom exclusions via `$auditExclude` property.

**Models to add trait**: `User`, `ExamSession`, `QuestionBank`, `Question`, `Material`, `Assignment`, `Announcement`
**Do NOT add to**: `AuditLog` (infinite loop), `StudentAnswer` (too high volume), `ExamActivityLog` (already audit-like)

### 4.3.4 Service: `app/Services/AuditService.php`

For explicit logging outside model events (logins, exports, imports):

```php
log(string $action, ?string $auditableType, ?int $auditableId, array $data, ?string $description): void
logExport(string $resource, int $resourceId): void
logImport(string $resource, int $count): void
logLogin(User $user, string $ip): void
```

### 4.3.5 Controller: `Admin\AuditLogController`

**`index()`** — paginated audit logs with filters: `user_id`, `action`, `auditable_type`, `date_from`, `date_to`

Props: `auditLogs` (PaginatedData), `users` (for filter dropdown), `filters`, `actionTypes`, `auditableTypes` (PHP class → human label map)

### 4.3.6 Vue Page: `Admin/AuditLog/Index.vue`

- Filter bar: user select, action type, auditable type, date range
- TanStack Table: Waktu, Pengguna, Aksi, Model, ID, Deskripsi, IP
- "Lihat Detail" button → Dialog showing old_values / new_values as formatted diff table
- Pattern: follows `Admin/Users/Index.vue`

### 4.3.7 Backup Script

- `app/Console/Commands/BackupDatabaseCommand.php` — runs `mysqldump` via `Process::run()`
- Stores to `storage/app/backups/smk_lms_{date}.sql.gz`
- Retains last 30 backups, deletes older
- Register in scheduler: `Schedule::command('backup:database')->dailyAt('02:00')`
- Admin trigger: `POST /admin/backup` dispatches job

### 4.3.8 Routes

```
GET  /admin/audit-log           → AuditLogController::index
POST /admin/backup              → BackupController::store
```

### 4.3.9 Sidebar Addition

Admin: `{ title: 'Log Audit', icon: ShieldCheck, href: '/admin/audit-log' }`

### 4.3.10 TypeScript Types: `types/audit.ts`

```ts
type AuditLogEntry = {
    id: number; user: { id: number; name: string; role: string } | null;
    action: string; action_label: string;
    auditable_type_label: string | null; auditable_id: number | null;
    old_values: Record<string, unknown> | null; new_values: Record<string, unknown> | null;
    ip_address: string | null; description: string | null; created_at: string;
};
type AuditFilters = { user_id: number | null; action: string | null; auditable_type: string | null; date_from: string | null; date_to: string | null; };
```

### 4.3.11 Acceptance Criteria

- [ ] CRUD on ExamSession generates audit_log rows with correct old/new values
- [ ] Password fields never appear in audit logs
- [ ] Admin can filter by user, action, model type, date range
- [ ] Detail dialog shows side-by-side diff of old vs new values
- [ ] Automated backup runs daily at 02:00, retains 30 backups

### 4.3.12 Edge Cases

- Queue jobs have no auth context: trait uses `auth()->id() ?? null`, `request()?->ip()`
- Bulk `::whereIn()->update()` bypasses Eloquent events — document as known limitation
- `SoftDeletes` models: `deleted` event fires correctly on soft delete

---

## Task 4.2: Notifications

### 4.2.1 Migration

Run `php artisan notifications:table` — standard Laravel notifications table (uuid PK, type, notifiable morph, data json, read_at, timestamps)

### 4.2.2 Notification Classes (5 new in `app/Notifications/`)

All implement `ShouldQueue`, use `database` channel only.

| Class | Trigger | Recipients | data payload |
|-------|---------|-----------|-------------|
| `UjianDijadwalkanNotification` | Guru publishes exam (`is_published` → true) | All siswa in target classrooms | `type: ujian_dijadwalkan`, `action_url: /siswa/ujian` |
| `DeadlineTugasNotification` | Daily cron 07:00 | Siswa who haven't submitted, deadline in 24h | `type: deadline_tugas`, `action_url: /siswa/tugas/{id}` |
| `NilaiDipublikasiNotification` | Guru publishes results | Siswa with graded attempts | `type: nilai_dipublikasi`, `action_url: /siswa/nilai` |
| `MateriBaruNotification` | Guru publishes material | Siswa in target classroom | `type: materi_baru`, `action_url: /siswa/materi/{id}` |
| `PengumumanBaruNotification` | Guru creates announcement | Siswa in target classroom (or all) | `type: pengumuman_baru`, `action_url: /siswa/pengumuman/{id}` |

### 4.2.3 Dispatch Points (modify existing controllers)

- `GradingController::publishResults()` → dispatch `NilaiDipublikasiNotification`
- `ExamSessionController::store/update` (when `is_published` transitions true) → dispatch `UjianDijadwalkanNotification`
- `MaterialController::store/update` (when `is_published` transitions true) → dispatch `MateriBaruNotification`
- `AnnouncementController::store()` → dispatch `PengumumanBaruNotification`

Guard: only dispatch when value transitions `false → true` (check `wasChanged('is_published') && $model->is_published`)

### 4.2.4 Scheduled Command: `SendDeadlineRemindersCommand`

- Signature: `notifications:send-deadline-reminders`
- Logic: find assignments with deadline in 23-25h, notify students who haven't submitted
- Schedule: `dailyAt('07:00')`

### 4.2.5 Controller: `NotificationController` (shared, all roles)

JSON endpoints (not Inertia) for bell dropdown:

```
GET    /notifications             → paginated list (20/page) + unread_count
GET    /notifications/list        → Inertia full page
POST   /notifications/read-all    → markAllAsRead
POST   /notifications/{id}/read   → markAsRead
DELETE /notifications/{id}        → destroy
```

### 4.2.6 Shared Props

In `HandleInertiaRequests`, add to `auth` shared prop:

```php
'unread_notifications_count' => Cache::remember("user:{$userId}:unread_notifications", 60, fn() => $user->unreadNotifications()->count())
```

Clear cache on mark-as-read actions.

### 4.2.7 Component: `NotificationBell.vue`

- Bell icon (lucide) with numeric badge when unread > 0
- DropdownMenu that fetches `/notifications` via axios on open
- Shows 10 recent notifications: icon per type, title, message (truncated), relative time (`@vueuse/core` `useTimeAgo`), unread = blue dot
- "Tandai semua dibaca" button, "Lihat semua" link → `/notifications/list`
- Click notification → mark read + navigate to `action_url`

**Placement**: Add to `AppSidebarLayout.vue` header area

### 4.2.8 Page: `Notifications/Index.vue`

Full-page paginated notification list. Breadcrumb: `[Dashboard, Notifikasi]`.

### 4.2.9 TypeScript Types: `types/notification.ts`

```ts
type NotificationType = 'ujian_dijadwalkan' | 'deadline_tugas' | 'nilai_dipublikasi' | 'materi_baru' | 'pengumuman_baru';
type NotificationData = { type: NotificationType; title: string; message: string; action_url: string; };
type NotificationItem = { id: string; type: string; data: NotificationData; read_at: string | null; created_at: string; };
```

### 4.2.10 WhatsApp Gateway (Phase 4+ / Optional — Not in scope)

Deferred to after Phase 4. Will use Fonnte API when implemented.

### 4.2.11 Acceptance Criteria

- [ ] Guru publishes exam → all target siswa receive database notification
- [ ] Bell icon shows unread count, updates after marking as read
- [ ] "Tandai semua dibaca" clears badge
- [ ] Daily cron sends deadline reminders for tasks due in 24h
- [ ] Notifications are queued (non-blocking)
- [ ] Click notification navigates to correct page

### 4.2.12 Edge Cases

- Empty recipients: guard `if ($users->isEmpty()) return;`
- Duplicate notifications: check `wasChanged()` before dispatching on update
- Shared prop for unauthenticated: null-safe check in middleware
- Cache invalidation: clear `user:{id}:unread_notifications` on mark-read

---

## Task 4.1: Analytics & KD Tagging

### 4.1.1 Migrations

**`competency_standards`**:
```sql
competency_standards
├── id (bigint, PK)
├── code (varchar 20)           -- "KD 3.4"
├── name (varchar)              -- "Menerapkan konsep pemrograman OOP"
├── description (text, nullable)
├── subject_id (FK → subjects)
├── timestamps
├── INDEX(subject_id)
├── UNIQUE(code, subject_id)
```

**`question_competency`** (pivot):
```sql
question_competency
├── question_id (FK → questions, cascade delete)
├── competency_standard_id (FK → competency_standards, cascade delete)
├── PRIMARY KEY(question_id, competency_standard_id)
├── INDEX(competency_standard_id)
```

**`item_analysis_cache`** (computed results cache):
```sql
item_analysis_cache
├── id (bigint, PK)
├── exam_session_id (FK → exam_sessions, cascade delete, UNIQUE)
├── analysis_data (json)        -- full analysis result
├── computed_at (timestamp)
├── timestamps
```

### 4.1.2 Models

**`CompetencyStandard`** (new):
- Fillable: `code`, `name`, `description`, `subject_id`
- Relations: `subject(): BelongsTo`, `questions(): BelongsToMany`

**`Question`** (modify — add relationship):
- Add: `competencyStandards(): BelongsToMany` via `question_competency`

**`ItemAnalysisCache`** (new):
- Fillable: `exam_session_id`, `analysis_data`, `computed_at`
- Casts: `analysis_data` => array, `computed_at` => datetime
- Relation: `examSession(): BelongsTo`

### 4.1.3 Service: `app/Services/Analytics/ItemAnalysisService.php`

**Core algorithm — `analyzeExamSession(ExamSession $examSession): array`**:

1. Load all submitted/graded attempts with studentAnswers + questions
2. Build per-student total scores and per-question response data
3. Compute per question:

**Difficulty Index (P)**:
```
P = correct_count / total_attempts
Classification: mudah (P > 0.70), sedang (0.30 ≤ P ≤ 0.70), sulit (P < 0.30)
```

**Discrimination Index (D) — Point-Biserial Correlation**:
```
rpb = ((Mp - Mt) / St) * sqrt(p * q)

Where:
  Mp = mean total score of students who answered correctly
  Mt = mean total score of ALL students
  St = standard deviation of total scores
  p = proportion correct, q = 1 - p

Classification: baik (D > 0.4), cukup (0.2 ≤ D ≤ 0.4), buruk (D < 0.2)
```

**Guard conditions**:
- If `p == 0` or `p == 1` → D = 0 (all correct/wrong, no discrimination)
- If `St == 0` → D = 0 (all students same score)
- For Esai with `is_correct == null` (ungraded) → `skipped: true`

**Distractor Analysis** (PG only):
For each option A/B/C/D: count selections, calculate percentage, flag correct answer.

**Return structure per question**:
```ts
{
    question_id, order, content_preview (100 chars), type,
    total_attempts, correct_count,
    difficulty_index (P), difficulty_label,
    discrimination_index (D), discrimination_label,
    choice_distribution: [{ label, count, percentage, is_correct }] | null,
    competency_standards: [{ code, name }],
    skipped: boolean
}
```

**Method: `getOrComputeAnalysis()`** — check `item_analysis_cache` (fresh if < 1 hour), else dispatch `ComputeItemAnalysisJob`, return stale data or `{ computing: true }`.

**Method: `getKdBreakdown(ExamSession, ?userId)`** — per-KD average scores. If userId provided, return that student's per-KD performance.

### 4.1.4 Service: `app/Services/Analytics/AnalyticsService.php`

Admin-level analytics:

- `getClassScoreTrend(Classroom, AcademicYear)` — monthly avg scores grouped by subject (line chart data)
- `getSubjectComparison(AcademicYear, Department)` — subject-level averages per department
- `getDepartmentComparison(AcademicYear)` — avg scores per department (bar chart data)

All queries use indexed joins: `exam_attempts` → `exam_sessions` → `exam_session_classroom`.

### 4.1.5 Job: `ComputeItemAnalysisJob`

- `ShouldQueue`, dispatched from `getOrComputeAnalysis()` when cache is stale
- Calls `analyzeExamSession()`, stores result in `item_analysis_cache`
- Uses `chunk(500)` for large datasets (500 students × 50 questions)

### 4.1.6 Controllers

**`Guru\ItemAnalysisController`**:
```
GET  /guru/grading/{examSession}/item-analysis     → show (Inertia page)
POST /guru/grading/{examSession}/item-analysis/refresh → dispatch recompute job
```

**`Guru\CompetencyController`** (KD management within bank soal):
```
GET    /guru/bank-soal/{bankSoal}/kompetensi       → index
POST   /guru/bank-soal/{bankSoal}/kompetensi       → store
PUT    /guru/bank-soal/{bankSoal}/kompetensi/{id}   → update
DELETE /guru/bank-soal/{bankSoal}/kompetensi/{id}   → destroy
POST   /guru/bank-soal/{bankSoal}/soal/{soal}/tag-kompetensi → tagQuestion
```

**`Admin\AnalyticsController`**:
```
GET  /admin/analytics                              → index (filters + charts)
GET  /admin/analytics/classroom/{classroom}        → classroomDetail
GET  /admin/analytics/department/{department}       → departmentDetail
```

### 4.1.7 Vue Pages

**`Guru/Penilaian/ItemAnalysis.vue`**:
- Summary cards: total questions, avg difficulty, avg discrimination
- Distribution chart: histogram of difficulty (mudah/sedang/sulit bar counts) — shadcn BarChart
- Scatter plot: X=Difficulty(P), Y=Discrimination(D), color-coded by quality — shadcn chart
- Item table: TanStack Table with No, Soal Preview, Tipe, P, Label, D, Label, Distribusi Pilihan, KD
- KD Breakdown: collapsible per-KD summary with avg score
- Computing state: skeleton + auto-reload every 30s if `isComputing`
- Access: link from `Guru/Penilaian/Show.vue` page

**`Guru/BankSoal/Kompetensi.vue`**:
- Two-panel: left = question list with KD tags, right = KD CRUD form
- Multi-select to tag questions to KDs

**Modify `Guru/BankSoal/Soal/Create.vue` and `Edit.vue`**:
- Add multi-select for competency standards in question form
- Load subject's competency standards for selection

**`Admin/Analytics/Index.vue`**:
- Filter bar: Academic Year selector, Classroom/Department selector
- Tren Nilai line chart: X=time(monthly), Y=avg score, one line per subject
- Perbandingan Kelas bar chart: X=classroom, Y=avg score, grouped by subject
- Rekap table: TanStack Table with classroom stats (avg, high, low, pass rate)
- Uses Inertia partial reloads for filter changes

### 4.1.8 Sidebar

Admin: add `{ title: 'Analitik', icon: BarChart3, href: '/admin/analytics' }`
Guru: no new sidebar item — item analysis accessible from Penilaian page button

### 4.1.9 TypeScript Types: `types/analytics.ts`

```ts
type DifficultyLabel = 'mudah' | 'sedang' | 'sulit';
type DiscriminationLabel = 'baik' | 'cukup' | 'buruk';
type ChoiceDistribution = { label: string; count: number; percentage: number; is_correct: boolean; };
type ItemAnalysisResult = {
    question_id: number; order: number; content_preview: string; type: string;
    total_attempts: number; correct_count: number;
    difficulty_index: number; difficulty_label: DifficultyLabel;
    discrimination_index: number; discrimination_label: DiscriminationLabel;
    choice_distribution: ChoiceDistribution[] | null;
    competency_standards: { code: string; name: string }[];
    skipped: boolean;
};
type ExamAnalysis = {
    exam_session_id: number; computed_at: string | null; computing: boolean;
    items: ItemAnalysisResult[];
    summary: { total_questions: number; easy_count: number; medium_count: number; hard_count: number; good_discrimination_count: number; fair_discrimination_count: number; poor_discrimination_count: number; };
    kd_breakdown: KdBreakdown[];
};
type KdBreakdown = { code: string; name: string; question_count: number; avg_score: number; max_possible: number; };
type CompetencyStandard = { id: number; code: string; name: string; description: string | null; subject_id: number; };
type ClassScoreTrend = { month: number; month_label: string; avg_score: number; subject_id: number; subject_name: string; };
```

### 4.1.10 Acceptance Criteria

- [ ] Completed exam with ≥10 attempts: item analysis computes P and D for all PG/BenarSalah questions
- [ ] Esai questions flagged as `skipped: true` if not fully graded
- [ ] Scatter plot renders with ≥20 data points
- [ ] KD breakdown shows per-KD averages when questions are tagged
- [ ] Admin analytics shows score trend for selected classroom + academic year
- [ ] Analysis cached; re-request within 1 hour serves cached data
- [ ] Refresh button triggers ComputeItemAnalysisJob and shows computing state
- [ ] Warning banner when < 5 attempts (insufficient for meaningful analysis)

### 4.1.11 Edge Cases

- **0 attempts**: return empty items with all-zero summary
- **1 attempt**: `St = 0` → D = 0 for all. Show warning "minimal 5 peserta untuk analisis yang bermakna"
- **P = 1.0** (all correct): `q = 0` → rpb undefined → D = 0
- **Large exams** (500×50 = 25K rows): computation in queue job, `chunk(500)` for memory
- **No KD tags**: show info notice "Belum ada soal yang ditandai KD"
- **Partial reload on admin**: `router.get('/admin/analytics', filters, { preserveState: true, replace: true })`

---

## Task 4.4: Data Exchange

### 4.4.1 Export Nilai Rapor (Excel)

**Export class**: `app/Exports/NilaiRaporExport.php` (multi-sheet)
- Sheet 1: Rekap — rows=students, columns=subjects (pivot table style)
- Sheet 2: Detail — all exam scores per student

**Job**: `ExportNilaiRaporJob` — ShouldQueue, saves to `storage/app/exports/rapor-{classroom}-{year}-{uuid}.xlsx`
- On completion: sends notification to requesting user with download URL

**Controller**: `Admin\AnalyticsController`
```
POST /admin/analytics/export-rapor     → dispatch ExportNilaiRaporJob
GET  /admin/analytics/download-export/{filename} → serve file
```

### 4.4.2 Export Data Siswa (Generic Excel)

**Export class**: `app/Exports/StudentDataExport.php`
- Generic column mapping: NIS/NISN, Nama, Kelas, Jurusan, Tahun Ajaran
- Maps from users + classroom_student + classrooms + departments
- Configurable columns — admin selects which fields to include
- Can be adapted later for specific Dapodik format

**Controller**: `Admin\DataExchangeController`
```
GET /admin/data-exchange/export-students → StreamedDownload
GET /admin/data-exchange/template        → download import template
POST /admin/data-exchange/import         → process import
```

### 4.4.3 Import Siswa (Generic Excel)

**Import class**: `app/Imports/StudentDataImport.php`
- Uses `ToModel`, `WithHeadingRow`, `WithBatchInserts(200)`, `WithChunkReading(200)`
- Column mapping: NIS/NISN → `username`, Nama → `name`, auto-generate password = `bcrypt(username)`, role = siswa
- Map Kelas column → lookup `Classroom` by name, add to `classroom_student`
- Validation: duplicate username check, invalid class names → per-row error report
- Encoding handling: `mb_convert_encoding()` for non-UTF8 files

### 4.4.4 Print Soal ke PDF

**Blade view**: `resources/views/pdf/exam-questions.blade.php`
- Layout: A4 portrait, inline CSS (dompdf constraint — no flexbox/grid)
- Header: school name, exam name, subject, class, date, duration
- Instructions section
- Questions 1-N with proper formatting per type:
  - PG: A/B/C/D options
  - B/S: Benar / Salah
  - Esai: blank answer space
  - Menjodohkan: two-column table
  - Isian Singkat: blank line
  - Ordering: numbered items
- Answer sheet section at bottom (bubble sheet style for PG)

**Controller addition** in `Guru\ExamSessionController`:
```
GET /guru/ujian/{ujian}/print-pdf → download PDF
```

**Image handling in PDF**: use `file://` scheme with `storage_path()` for question images (dompdf can render local images).

**Button**: add "Cetak Soal" button to `Guru/Ujian/Show.vue`

### 4.4.5 Acceptance Criteria

- [ ] Export rapor generates valid .xlsx with 2 sheets (rekap + detail), via queue
- [ ] Generic student data import with 100 students creates accounts without memory issues (chunk + batch)
- [ ] Invalid import rows produce per-row error report
- [ ] Print PDF generates properly formatted A4 with all question types
- [ ] PDF download < 10 seconds for 50-question exam
- [ ] Export for classroom with no data: Excel with headers + "Belum ada data" row

### 4.4.6 Edge Cases

- **PDF with images**: use `storage_path('app/public/' . $question->media_path)` with `file://` prefix
- **Import encoding**: files may use Windows-1252 — use `mb_convert_encoding()` in `BeforeImport` concern
- **Large import** (1000+ students): `WithChunkReading(200)` + `WithBatchInserts(200)` for HDD server
- **Empty classroom export**: generate Excel with headers and info row

---

## Complete New Files Summary

### Migrations (5)
1. `create_notifications_table` (via artisan)
2. `create_audit_logs_table`
3. `create_competency_standards_table`
4. `create_question_competency_table`
5. `create_item_analysis_cache_table`

### Models (3 new, 2 modified)
- New: `AuditLog`, `CompetencyStandard`, `ItemAnalysisCache`
- Modified: `Question` (+competencyStandards relation, +Auditable trait), `ExamSession` (+Auditable trait), `User` (+Auditable trait), `QuestionBank` (+Auditable)

### Traits (1)
- `app/Traits/Auditable.php`

### Services (3 new)
- `app/Services/Analytics/ItemAnalysisService.php`
- `app/Services/Analytics/AnalyticsService.php`
- `app/Services/AuditService.php`

### Jobs (2 new)
- `app/Jobs/ComputeItemAnalysisJob.php`
- `app/Jobs/ExportNilaiRaporJob.php`

### Notifications (5 new)
- `UjianDijadwalkanNotification`, `DeadlineTugasNotification`, `NilaiDipublikasiNotification`, `MateriBaruNotification`, `PengumumanBaruNotification`

### Commands (2 new)
- `SendDeadlineRemindersCommand`
- `BackupDatabaseCommand`

### Controllers (6 new, 4 modified)
- New: `Admin\AuditLogController`, `Admin\AnalyticsController`, `Admin\DataExchangeController`, `Guru\ItemAnalysisController`, `Guru\CompetencyController`, `NotificationController`
- Modified: `Guru\GradingController`, `Guru\ExamSessionController`, `Guru\MaterialController`, `Guru\AnnouncementController` (add notification dispatch)

### Exports/Imports (3 new)
- `app/Exports/NilaiRaporExport.php`, `app/Exports/StudentDataExport.php`, `app/Imports/StudentDataImport.php`

### Vue Pages (5 new, 3 modified)
- New: `Admin/Analytics/Index.vue`, `Admin/AuditLog/Index.vue`, `Guru/Penilaian/ItemAnalysis.vue`, `Guru/BankSoal/Kompetensi.vue`, `Notifications/Index.vue`
- Modified: `Guru/BankSoal/Soal/Create.vue`, `Guru/BankSoal/Soal/Edit.vue` (add KD multi-select), `Guru/Ujian/Show.vue` (add print PDF button)

### Components (1 new, 2 modified)
- New: `NotificationBell.vue`
- Modified: `AppSidebar.vue` (new nav items), `AppSidebarLayout.vue` (add NotificationBell)

### Blade Views (1 new)
- `resources/views/pdf/exam-questions.blade.php`

### TypeScript Types (3 new)
- `types/analytics.ts`, `types/audit.ts`, `types/notification.ts`

---

## All New Routes

### Admin
```
GET    /admin/audit-log
POST   /admin/backup
GET    /admin/analytics
GET    /admin/analytics/classroom/{classroom}
GET    /admin/analytics/department/{department}
POST   /admin/analytics/export-rapor
GET    /admin/analytics/download-export/{filename}
GET    /admin/data-exchange/export-students
GET    /admin/data-exchange/template
POST   /admin/data-exchange/import
```

### Guru
```
GET    /guru/grading/{examSession}/item-analysis
POST   /guru/grading/{examSession}/item-analysis/refresh
GET    /guru/bank-soal/{bankSoal}/kompetensi
POST   /guru/bank-soal/{bankSoal}/kompetensi
PUT    /guru/bank-soal/{bankSoal}/kompetensi/{competency}
DELETE /guru/bank-soal/{bankSoal}/kompetensi/{competency}
POST   /guru/bank-soal/{bankSoal}/soal/{soal}/tag-kompetensi
GET    /guru/ujian/{ujian}/print-pdf
```

### Shared (all authenticated)
```
GET    /notifications
GET    /notifications/list
POST   /notifications/read-all
POST   /notifications/{id}/read
DELETE /notifications/{id}
```

### Admin Sidebar Additions
```
Analitik (BarChart3 icon) → /admin/analytics
Log Audit (ShieldCheck icon) → /admin/audit-log
Data Exchange (Database icon) → /admin/data-exchange/export-students
```

---

## Verification

1. **Audit**: Create/update/delete an ExamSession → check `audit_logs` table has correct entries
2. **Notifications**: Publish exam results → check siswa receives notification, bell badge updates
3. **Analytics**: Run `ComputeItemAnalysisJob` for exam with 20+ attempts → verify P and D calculations match manual computation
4. **PDF**: Print a 30-question exam with mixed types → verify all types render, images display, layout fits A4
5. **Export**: Export rapor for a classroom → verify multi-sheet Excel with correct pivot data
6. **Tests**: `php artisan test` — add feature tests for ItemAnalysisService (difficulty/discrimination calculations), notification dispatch, audit trait

### Critical Files to Modify
- `app/Services/Exam/GradingService.php` — extend for notification dispatch in publishResults
- `resources/js/components/AppSidebar.vue` — add Analitik, Log Audit, Dapodik nav items
- `app/Http/Controllers/Guru/GradingController.php` — add notification dispatch + link to item analysis
- `app/Models/Question.php` — add competencyStandards() BelongsToMany
- `resources/js/types/grading.ts` — pattern reference for new type files
- `resources/js/layouts/app/AppSidebarLayout.vue` — add NotificationBell component
