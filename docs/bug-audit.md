# Full Bug/Logic Audit — SMK LMS + CBT System

**Tanggal**: 2026-03-24
**Auditor**: Automated deep audit (7 parallel agents)
**Scope**: Seluruh codebase — auth, exam engine, grading, data integrity, frontend-backend, LMS features, security, concurrency

**Total Issues**: 78 findings
| Severity | Count |
|----------|-------|
| CRITICAL | 11 |
| HIGH     | 24 |
| MEDIUM   | 28 |
| LOW      | 15 |

---

## Table of Contents

1. [Auth & Access Control](#1-auth--access-control)
2. [Exam Flow Logic](#2-exam-flow-logic)
3. [Grading & Score Calculation](#3-grading--score-calculation)
4. [Data Integrity, Migrations & Config](#4-data-integrity-migrations--config)
5. [Frontend-Backend Mismatch](#5-frontend-backend-mismatch)
6. [LMS Features & Queue Jobs](#6-lms-features--queue-jobs)
7. [Security, WebSocket & Concurrency](#7-security-websocket--concurrency)

---

## 1. Auth & Access Control

### 1.1 [CRITICAL] ForumController togglePin & toggleLock — Missing Authorization
- **File**: `app/Http/Controllers/ForumController.php:145-161`
- **Current**: No `$this->authorize()` call. Route middleware `role:admin,guru` exists but no controller-level policy check.
- **Expected**: Defense-in-depth — controller should call `$this->authorize('pin', $thread)` and `$this->authorize('lock', $thread)`.
- **Fix**: Add policy authorization in both methods.

### 1.2 [CRITICAL] GradingController manualGrading — Missing Attempt-to-ExamSession Validation
- **File**: `app/Http/Controllers/Guru/GradingController.php:143-217`
- **Current**: Authorizes `$examSession` but never verifies `$attempt->exam_session_id === $examSession->id`. Guru can access attempts from other exam sessions by manipulating URL.
- **Expected**: Validate that the attempt belongs to the provided exam session.
- **Fix**: Add `if ($attempt->exam_session_id !== $examSession->id) abort(404);`

### 1.3 [CRITICAL] GradingController saveGrade — Missing Answer Cross-Exam Validation
- **File**: `app/Http/Controllers/Guru/GradingController.php:222-238`
- **Current**: Authorizes `$examSession` but never verifies `$answer` belongs to an attempt in that exam session. Guru can grade answers from other exams.
- **Expected**: Validate answer belongs to exam session.
- **Fix**: Add `if ($answer->examAttempt->exam_session_id !== $examSession->id) abort(404);`

### 1.4 [CRITICAL] GradingController activityLog — Missing Attempt Cross-Exam Validation
- **File**: `app/Http/Controllers/Guru/GradingController.php:279-294`
- **Current**: Same cross-exam access issue — guru can view activity logs from attempts in different exam sessions.
- **Expected**: Validate attempt belongs to exam session.
- **Fix**: Add `if ($attempt->exam_session_id !== $examSession->id) abort(404);`

### 1.5 [HIGH] ExamSessionPolicy Does Not Check User Role
- **File**: `app/Policies/ExamSessionPolicy.php:12-25`
- **Current**: Only checks `$user->id === $examSession->user_id`. No explicit guru role check.
- **Expected**: Add `$user->isGuru() &&` before ownership check for defense-in-depth.
- **Fix**: Add role check to `view()`, `update()`, `delete()` methods.

### 1.6 [HIGH] QuestionBankPolicy Does Not Check User Role
- **File**: `app/Policies/QuestionBankPolicy.php:12-25`
- **Current**: Same issue — only checks ownership, not role.
- **Expected**: Add `$user->isGuru()` check.
- **Fix**: Same pattern as ExamSessionPolicy.

### 1.7 [MEDIUM] WebSocket Channel — Exam Proctor Channel Missing Role Verification
- **File**: `routes/channels.php:11-18`
- **Current**: `return $user->id === $examSession->user_id;` — no guru role check.
- **Expected**: Add `$user->isGuru() &&` before ownership check.
- **Fix**: Add role verification.

### 1.8 [MEDIUM] MaterialPolicy — Classroom Ownership Not Verified for Update/Delete
- **File**: `app/Policies/MaterialPolicy.php:36-44`
- **Current**: Guru can edit/delete materials they created for any classroom, even ones they don't teach.
- **Expected**: Verify guru teaches the classroom (if that's the business rule).
- **Fix**: Add classroom-teaching verification.

### 1.9 [MEDIUM] AssignmentPolicy — Same Classroom Issue
- **File**: `app/Policies/AssignmentPolicy.php:36-49`
- **Current**: Same issue as MaterialPolicy.
- **Fix**: Same pattern.

### 1.10 [MEDIUM] ForumController Uses Inline Auth Instead of Policies
- **File**: `app/Http/Controllers/ForumController.php:118-143`
- **Current**: Manual `if (!$user->isAdmin() && ...)` checks instead of `$this->authorize()`.
- **Expected**: Consistent policy usage.
- **Fix**: Replace inline checks with `$this->authorize('delete', $thread)`.

### 1.11 [MEDIUM] AnnouncementPolicy — No Classroom Ownership Check on Create
- **File**: `app/Policies/AnnouncementPolicy.php:12-14`
- **Current**: Only checks role, not whether guru teaches the target classroom.
- **Expected**: Verify guru teaches the classroom.
- **Fix**: Add classroom context verification.

---

## 2. Exam Flow Logic

### 2.1 [CRITICAL] Double-Submit Race Condition
- **File**: `app/Http/Controllers/Siswa/ExamController.php:277-300`, `app/Services/Exam/ExamAttemptService.php:246-311`
- **Current**: Status check (`status === InProgress`) is non-atomic. Two concurrent submit requests can both pass the check before either updates status.
- **Expected**: Atomic check-and-update using pessimistic locking.
- **Fix**: Use `$attempt->lockForUpdate()->where('status', InProgress)->sole()` inside transaction.

### 2.2 [CRITICAL] SingleSessionExam Middleware — TOCTOU Race Condition
- **File**: `app/Http/Middleware/SingleSessionExam.php:25-34`
- **Current**: `Cache::get()` then `Cache::put()` is not atomic. Two concurrent requests with no stored session ID both succeed, second overwrites first.
- **Expected**: Use atomic set-if-not-exists.
- **Fix**: Use `Cache::add()` instead of separate get/put.

### 2.3 [HIGH] Answers Can Be Saved After Time Expires (No Grace Period)
- **File**: `app/Http/Controllers/Siswa/ExamController.php:218-272`
- **Current**: No margin for network latency or clock skew. Answer arriving 100ms after server-side expiration is rejected.
- **Expected**: Add 2-3 second grace period for submissions arriving near expiration.
- **Fix**: Add `SUBMISSION_GRACE_PERIOD = 3` seconds to `isExpired()`.

### 2.4 [HIGH] Timer Sync Allows Negative Remaining Seconds
- **File**: `resources/js/composables/useExamTimer.ts:52-56`
- **Current**: `syncWithServer()` accepts negative values from server. Timer shows negative time and jitter delay still applies before auto-submit.
- **Expected**: Clamp to minimum 0. If already expired, fire immediately without jitter.
- **Fix**: `remainingSeconds.value = Math.max(0, serverRemainingSeconds);`

### 2.5 [HIGH] Zero Questions Edge Case Not Handled
- **File**: `app/Services/Exam/ExamRandomizerService.php:18-62`
- **Current**: If question bank has 0 questions, empty collection is returned. Student sees empty exam, can submit immediately with 0 answers.
- **Expected**: Prevent exam start if no questions available.
- **Fix**: Add validation in `buildExamPayload()`: `if (empty($questions)) throw exception;`

### 2.6 [HIGH] Pool Count > Available Questions Throws Exception
- **File**: `app/Services/Exam/ExamRandomizerService.php:56-59`
- **Current**: `Collection::random(20)` on collection of 10 items throws `ArgumentCountError`.
- **Expected**: Clamp pool count to available questions.
- **Fix**: `$poolCount = min($examSession->pool_count, $allQuestions->count());`

### 2.7 [HIGH] Tab Switch Counter Resets on Resume
- **File**: `resources/js/composables/useExamState.ts:20-47`
- **Current**: `tabSwitchCount` initialized to 0 on every page load. Server payload doesn't include previous count.
- **Expected**: Load tab switch count from server on resume.
- **Fix**: Add `tab_switch_count` to `ExamPayload` type and populate from server.

### 2.8 [MEDIUM] Session Lock TTL Hardcoded to 24h — Doesn't Match Exam Duration
- **File**: `app/Http/Controllers/Siswa/ExamController.php:171`
- **Current**: `Cache::put(..., 86400)`. If exam is >24h (take-home), lock expires and another device can access.
- **Expected**: TTL = exam duration + buffer.
- **Fix**: `$ttl = $ujian->duration_minutes * 60 + 3600;`

### 2.9 [MEDIUM] Randomization Not Deterministic Per Student
- **File**: `app/Services/Exam/ExamRandomizerService.php:56-59`
- **Current**: `Collection::random()` without seed. If teacher changes `pool_count` after some students started, new students get different set size.
- **Expected**: Seed based on student ID + exam ID for reproducibility.
- **Fix**: `mt_srand(crc32("{$examSession->id}:{$student->id}"));`

### 2.10 [MEDIUM] Auto-Save Doesn't Validate Answers Against Question Types
- **File**: `app/Http/Controllers/Siswa/ExamController.php:218-272`
- **Current**: Accepts any answer format — no validation that single-choice gets single value, ordering gets valid IDs, etc.
- **Expected**: Validate answer format per question type.
- **Fix**: Add type-specific validation for each answer.

### 2.11 [MEDIUM] Page Refresh During Exam — Answer Loss Risk
- **File**: `resources/js/composables/useExamState.ts:20-47`
- **Current**: Server answers override localStorage (`{...storedAnswers, ...payload.saved_answers}`). If auto-save hasn't reached server yet, recent answers are lost.
- **Expected**: Compare timestamps and keep the newer answer.
- **Fix**: Add `answered_at` timestamp comparison logic.

### 2.12 [LOW] Timer Jitter (0-30s) Allows Late Activity
- **File**: `resources/js/composables/useExamTimer.ts:30-35`
- **Current**: After timer hits 0, random 0-30s delay before auto-submit. Student can still interact during jitter.
- **Expected**: Reduce jitter to 0-5s or disable interaction when timer hits 0.
- **Fix**: Reduce jitter and disable UI on expiration.

### 2.13 [LOW] Device Lock Inflexible — Network Change Locks Out Student
- **File**: `app/Http/Controllers/Siswa/ExamController.php:387-407`
- **Current**: IP change during exam (e.g. WiFi to mobile) permanently locks student out.
- **Expected**: Allow limited device changes or log and allow.
- **Fix**: Add grace period or limited device change allowance.

---

## 3. Grading & Score Calculation

### 3.1 [HIGH] autoGrade Doesn't Update Attempt Status to Graded
- **File**: `app/Services/Exam/ExamAttemptService.php:316-354`
- **Current**: Sets `is_fully_graded = true` but doesn't update `status` to `ExamAttemptStatus::Graded`. Creates inconsistent state.
- **Expected**: Set `status = Graded` when `is_fully_graded = true`.
- **Fix**: Add `'status' => ExamAttemptStatus::Graded` to the update.

### 3.2 [MEDIUM] IsianSingkat Grading Fails Silently When No Keywords Defined
- **File**: `app/Services/Exam/ExamAttemptService.php:420-440`
- **Current**: If question has no keywords, `$question->keywords->contains()` returns false — answer marked incorrect.
- **Expected**: Skip auto-grading (mark as pending manual grading) or log warning.
- **Fix**: Add `if ($question->keywords->isEmpty()) { mark as needing manual grading; return; }`

### 3.3 [MEDIUM] Multiple Answer Uses All-or-Nothing — No Partial Credit
- **File**: `app/Services/Exam/ExamAttemptService.php:382-414`
- **Current**: Missing one correct option = 0 score. By design (confirmed by tests), but not documented.
- **Expected**: Document this behavior clearly for teachers.
- **Fix**: Add documentation/tooltip in exam creation UI.

### 3.4 [LOW] CSV Export Uses Locale-Sensitive number_format
- **File**: `app/Services/Exam/GradingService.php:167`
- **Current**: `number_format((float) $attempt->score, 2)` — may use comma as decimal separator in non-English locales.
- **Expected**: Explicit decimal point.
- **Fix**: `number_format((float) $attempt->score, 2, '.', '')`

### 3.5 [LOW] Analytics Missing NULL Score Defensive Check
- **File**: `app/Services/Analytics/AnalyticsService.php:115-131`
- **Current**: `round((float) $row->avg_score, 2)` — if NULL passes through, produces 0.0 silently.
- **Expected**: Explicit null check.
- **Fix**: `$row->avg_score !== null ? round((float) $row->avg_score, 2) : 0`

### 3.6 [LOW] Ordering Question order Field May Be NULL
- **File**: `app/Services/Exam/ExamAttemptService.php:489-519`
- **Current**: `sortBy('order')` assumes order is always set. If NULL, sort is undefined.
- **Expected**: Ensure NOT NULL constraint on order field.
- **Fix**: Verify migration has NOT NULL on `question_options.order`.

---

## 4. Data Integrity, Migrations & Config

### 4.1 [CRITICAL] .env.example — SESSION_DRIVER Defaults to database (Not Redis)
- **File**: `.env.example:30`
- **Current**: `SESSION_DRIVER=database`. HDD latency 50-100ms per session read.
- **Expected**: `SESSION_DRIVER=redis` per performance rules.
- **Fix**: Change default to `redis`.

### 4.2 [CRITICAL] .env.example — QUEUE_CONNECTION Defaults to database
- **File**: `.env.example:38`
- **Current**: `QUEUE_CONNECTION=database`. All queue jobs hit disk.
- **Expected**: `QUEUE_CONNECTION=redis`.
- **Fix**: Change default to `redis`.

### 4.3 [CRITICAL] .env.example — CACHE_STORE Defaults to database
- **File**: `.env.example:40`
- **Current**: `CACHE_STORE=database`. Cache reads hit HDD.
- **Expected**: `CACHE_STORE=redis`.
- **Fix**: Change default to `redis`.

### 4.4 [HIGH] original_exam_session_id Uses nullOnDelete — Orphans Remedial Records
- **File**: `database/migrations/2026_03_15_020003_add_remedial_fields_to_exam_sessions_table.php:14-15`
- **Current**: `nullOnDelete()` — deleting original exam sets remedial's reference to NULL, orphaning it.
- **Expected**: Either cascade delete or prevent deletion of exams with active remedials.
- **Fix**: Use `restrictOnDelete()` or cascade with business logic check.

### 4.5 [HIGH] Email Made Nullable After Being Unique — Authentication Risk
- **File**: `database/migrations/2026_03_12_110344_add_role_username_is_active_to_users_table.php:20`
- **Current**: `$table->string('email')->nullable()->change()`. Multiple NULL emails possible. Fortify may break if it expects email.
- **Expected**: Verify authentication doesn't depend on email, or keep email required.
- **Fix**: Verify Fortify login flow handles NULL email.

### 4.6 [HIGH] Missing Index on exam_activity_logs.created_at
- **File**: `database/migrations/2026_03_14_010006_create_exam_activity_logs_table.php`
- **Current**: Only indexed by `exam_attempt_id`. Time-range queries scan entire table on HDD.
- **Expected**: Index on `(exam_attempt_id, created_at)`.
- **Fix**: Add composite index.

### 4.7 [HIGH] Performance Index Migration Has Incorrect Date (2023 vs 2026)
- **File**: `database/migrations/2023_03_23_072331_add_performance_indexes.php`
- **Current**: Filename starts with `2023_` — will run before all other 2026 migrations, potentially failing if tables don't exist yet.
- **Expected**: Date should be `2026_03_23`.
- **Fix**: Rename migration file.

### 4.8 [MEDIUM] Missing Index on exam_session_questions.order
- **File**: `database/migrations/2026_03_14_010002_create_exam_session_questions_table.php`
- **Current**: No index on `order` column.
- **Expected**: Index on `(exam_session_id, order)` for ordered question retrieval.
- **Fix**: Add composite index.

### 4.9 [MEDIUM] StudentAnswer UNIQUE Constraint Added in Late Migration
- **File**: `database/migrations/2026_03_14_010005_create_student_answers_table.php` + `2023_03_23_072331_add_performance_indexes.php`
- **Current**: Unique constraint added in performance migration, not base migration. Race condition window exists.
- **Expected**: Unique constraint in base migration.
- **Fix**: Move unique constraint to base migration.

### 4.10 [MEDIUM] ForumReply/DiscussionReply Increment Race Condition
- **File**: `app/Models/ForumReply.php:21-36`, `app/Models/DiscussionReply.php:21-36`
- **Current**: Two simultaneous replies can both call `increment()` — while `increment()` itself is atomic, the `update(['last_reply_at' => ...])` is not part of the same atomic operation.
- **Expected**: Wrap in transaction.
- **Fix**: Use `DB::transaction()`.

### 4.11 [MEDIUM] DatabaseSeeder Not Idempotent
- **File**: `database/seeders/DatabaseSeeder.php`
- **Current**: Uses `User::create()` with hardcoded usernames. Running twice throws unique constraint violation.
- **Expected**: Use `firstOrCreate()`.
- **Fix**: Replace `create()` with `firstOrCreate()`.

### 4.12 [MEDIUM] StudentAnswerFactory Creates Mismatched Question-Attempt Data
- **File**: `database/factories/StudentAnswerFactory.php:20-21`
- **Current**: Creates random ExamAttempt and random Question that may not belong to the same exam session.
- **Expected**: Ensure question belongs to attempt's exam session.
- **Fix**: Add relationship validation in factory.

### 4.13 [LOW] Log Rotation Not Configured for Production
- **File**: `.env.example:18-19`
- **Current**: `LOG_STACK=single`, `LOG_LEVEL=debug`. Single file grows indefinitely on HDD.
- **Expected**: `LOG_STACK=daily`, `LOG_LEVEL=info`, `LOG_DAILY_DAYS=7`.
- **Fix**: Update .env.example defaults.

### 4.14 [LOW] Material/Assignment File Cleanup Missing on Delete
- **File**: `app/Models/Material.php`, `app/Models/Assignment.php`
- **Current**: No `deleting` event to clean up storage files. Orphaned files accumulate.
- **Expected**: Delete file from storage when model is deleted.
- **Fix**: Add `static::deleting()` handler.

### 4.15 [LOW] Audit Log Retention Policy Missing
- **File**: `database/migrations/2026_03_19_010000_create_audit_logs_table.php`
- **Current**: No retention policy. Grows indefinitely on HDD.
- **Expected**: Scheduled pruning of old records (90+ days).
- **Fix**: Add scheduled command for audit log cleanup.

### 4.16 [LOW] QuestionBank Cascade Delete Chain Too Broad
- **File**: `database/migrations/2026_03_13_020000_create_question_banks_table.php:16-17`
- **Current**: Subject delete → bank delete → questions delete → exam data orphaned.
- **Expected**: Use `restrictOnDelete()` on subject to prevent accidental data loss.
- **Fix**: Change to restrict or soft delete subjects.

---

## 5. Frontend-Backend Mismatch

### 5.1 [HIGH] Double-Click Submit — No Frontend Deduplication
- **File**: `resources/js/Pages/Siswa/Ujian/ExamInterface.vue:176-189`
- **Current**: `isSubmitting = true` guards UI, but rapid double-click can still send two POST requests before state updates.
- **Expected**: Request-level deduplication or idempotency token.
- **Fix**: Add idempotency key header or disable submit button via DOM before async call.

### 5.2 [MEDIUM] Browser Back Button After Exam Submit Returns to Stale Exam
- **File**: `resources/js/Pages/Siswa/Ujian/ExamInterface.vue:78-94`
- **Current**: After submission, redirect via `router.post()`. Browser back button returns to stale ExamInterface page.
- **Expected**: Use `router.replace()` or re-check attempt status on mount.
- **Fix**: On component mount, if attempt is already submitted, redirect to exam list.

### 5.3 [MEDIUM] Missing is_published Validation in ExamSessionRequest
- **File**: `app/Http/Requests/Guru/ExamSessionRequest.php:21-41`
- **Current**: No validation for `is_published` field. Malicious payload could set arbitrary value.
- **Expected**: Add `'is_published' => ['sometimes', 'boolean']`.
- **Fix**: Add validation rule.

### 5.4 [MEDIUM] Exam Payload saved_answers Cast to Object Unnecessarily
- **File**: `app/Services/Exam/ExamAttemptService.php:200`
- **Current**: `'saved_answers' => (object) $savedAnswers` — forces PHP object. TypeScript expects `Record<string, string>`.
- **Expected**: Send as array directly.
- **Fix**: Remove `(object)` cast.

### 5.5 [LOW] Flash Messages Don't Auto-Dismiss
- **File**: `resources/js/components/FlashMessage.vue`
- **Current**: Flash messages persist until next navigation. No auto-dismiss timer.
- **Expected**: Success messages auto-dismiss after 5-7 seconds.
- **Fix**: Add `setTimeout` for auto-dismiss.

### 5.6 [LOW] Material Progress Not Eager-Loaded (Potential N+1)
- **File**: `app/Http/Controllers/Siswa/MaterialController.php:44-55`
- **Current**: Materials and progress loaded in separate queries, then manually joined.
- **Expected**: Use eager loading with constraints.
- **Fix**: Use `with(['progress' => fn($q) => $q->where('user_id', $student->id)])`.

---

## 6. LMS Features & Queue Jobs

### 6.1 [HIGH] Forum: Guru Can Reply to Locked Threads
- **File**: `app/Http/Controllers/Guru/DiscussionController.php:85-97`
- **Current**: Teacher can reply to locked threads — no `is_locked` check. Only Siswa controller checks this.
- **Expected**: Both Guru and Siswa should respect locked thread status.
- **Fix**: Add locked check before allowing reply.

### 6.2 [HIGH] Assignment: Race Condition on Re-submission After Grading
- **File**: `app/Http/Controllers/Siswa/AssignmentController.php:69-92`
- **Current**: Checks `graded_at` but no locking. Between check and submit, teacher could grade it. `updateOrCreate()` then overwrites graded submission.
- **Expected**: Atomic check or pessimistic lock.
- **Fix**: Add database-level constraint or pessimistic lock.

### 6.3 [HIGH] File Upload: 0-byte Files Not Rejected
- **File**: `app/Http/Requests/Guru/StoreMaterialRequest.php:30`, `app/Http/Requests/Guru/StoreAssignmentRequest.php:29`
- **Current**: No `min:1` or empty file check in validation rules.
- **Expected**: Reject 0-byte files.
- **Fix**: Add `min:1` to file validation.

### 6.4 [HIGH] File Upload: Double Extension (.php.jpg) Not Blocked
- **File**: `app/Rules/ValidMimeType.php:37-58`
- **Current**: Only checks last extension. `.php.jpg` passes validation.
- **Expected**: Detect and block double extensions.
- **Fix**: Check for dots in filename before final extension.

### 6.5 [HIGH] PersistAnswersJob — No Validation on Corrupted Redis Data
- **File**: `app/Jobs/PersistAnswersJob.php:36-83`
- **Current**: `json_decode()` could return non-array from corrupted data. No error logging.
- **Expected**: Validate decoded data and log corruption.
- **Fix**: Add `if (!is_array($answers)) { Log::warning(...); return; }`

### 6.6 [HIGH] GradingController saveGrade — Can Overwrite Existing Grade
- **File**: `app/Http/Controllers/Guru/GradingController.php:222-238`
- **Current**: No check if answer already has a grade. Silently overwrites.
- **Expected**: Warn or confirm before overwriting.
- **Fix**: Add grade-exists check or confirmation flow.

### 6.7 [MEDIUM] Attendance: Duplicate Check-in Not Database-Constrained
- **File**: `app/Services/LMS/AttendanceService.php:100-106`
- **Current**: Uniqueness checked only in code, not by database constraint. Concurrent requests can create duplicates.
- **Expected**: Database unique constraint on `(attendance_id, user_id)`.
- **Fix**: Add migration for unique index.

### 6.8 [MEDIUM] Attendance: closeSession Marks Alfa Without Checking Code Expiry
- **File**: `app/Services/LMS/AttendanceService.php:47-63`
- **Current**: If `code_expires_at` is NULL (unlimited), students still get marked as Alfa on close.
- **Expected**: If code never expires, don't auto-mark as Alfa.
- **Fix**: Check `code_expires_at` before marking.

### 6.9 [MEDIUM] GradeExamJob — No Idempotency Check
- **File**: `app/Jobs/GradeExamJob.php:15-31`
- **Current**: No check if attempt is already graded. Retry re-grades everything.
- **Expected**: Early return if already graded.
- **Fix**: Add `if ($this->attempt->status === Graded) return;`

### 6.10 [MEDIUM] Assignment Late Penalty Not Auto-Applied
- **File**: `app/Services/LMS/AssignmentService.php:95-107`
- **Current**: `gradeSubmission()` accepts raw score without applying `late_penalty_percent`.
- **Expected**: Auto-apply penalty if `is_late = true`.
- **Fix**: Calculate penalized score in service method.

### 6.11 [MEDIUM] Announcement: published_at vs is_published Inconsistency
- **File**: `app/Models/Announcement.php:52-55`
- **Current**: Two fields control visibility (`is_published` flag and `published_at` timestamp). Logic is inconsistent.
- **Expected**: Use one consistent field.
- **Fix**: Standardize on one approach.

### 6.12 [MEDIUM] Notification Unread Count Cache Race Condition
- **File**: `app/Http/Controllers/NotificationController.php:55-63`
- **Current**: `markAllAsRead()` updates DB and forgets cache non-atomically.
- **Expected**: Atomic operation.
- **Fix**: Use transaction or atomic cache+DB update.

### 6.13 [MEDIUM] File Download — Missing File Returns 404 Without Audit
- **File**: `app/Http/Controllers/Guru/MaterialController.php:184-193`
- **Current**: Missing storage file returns 404 with no logging.
- **Expected**: Log the missing file for investigation.
- **Fix**: Add `Log::warning()` before abort.

### 6.14 [LOW] CleanupOrphanedFilesJob — Non-Recursive Directory Scan
- **File**: `app/Jobs/CleanupOrphanedFilesJob.php:47-61`
- **Current**: `Storage::files($dir)` only scans root level, not subdirectories.
- **Expected**: Recursive scan.
- **Fix**: Use `Storage::allFiles($dir)`.

### 6.15 [LOW] Siswa Assignment Index — N+1 Query on Submissions
- **File**: `app/Http/Controllers/Siswa/AssignmentController.php:36-43`
- **Current**: For each assignment, calls `submissions()->where()` in map callback.
- **Expected**: Eager load with constraints.
- **Fix**: Use `with()` eager loading.

### 6.16 [LOW] Attendance Recap Calculation — N+1 Pattern
- **File**: `app/Services/LMS/AttendanceService.php:140-180`
- **Current**: Fetches all students and records, groups in PHP.
- **Expected**: Group in database query.
- **Fix**: Use raw SQL grouping or database-level aggregation.

### 6.17 [LOW] Material Progress markComplete — No Access Control
- **File**: `app/Services/LMS/MaterialService.php:93-107`
- **Current**: Sets `completed_at` without verifying material belongs to student's classroom.
- **Expected**: Add classroom membership check.
- **Fix**: Verify in controller or add Eloquent scope.

---

## 7. Security, WebSocket & Concurrency

### 7.1 [CRITICAL] Device Lock Bypass via Cache Expiry
- **File**: `app/Http/Middleware/SingleSessionExam.php:12-39`, `app/Http/Controllers/Siswa/ExamController.php:387-407`
- **Current**: Session ID stored in Redis cache. Cache can expire or be cleared, allowing access from different device.
- **Expected**: Use encrypted, tamper-proof storage (signed cookie or JWT).
- **Fix**: Store device fingerprint in encrypted session cookie.

### 7.2 [CRITICAL] Force-Submit Race Condition — No Pessimistic Locking
- **File**: `app/Services/Exam/ProctorService.php:142-168`
- **Current**: Proctor terminate and student submit can race. Both check status non-atomically.
- **Expected**: Use pessimistic locking on attempt status.
- **Fix**: `$attempt = ExamAttempt::lockForUpdate()->where(...)->firstOrFail();`

### 7.3 [HIGH] Timezone Misconfiguration — UTC Instead of WIB
- **File**: `config/app.php`, `.env`
- **Current**: Application timezone is UTC. School operates in WIB (UTC+7). Exam times displayed 7 hours off.
- **Expected**: `APP_TIMEZONE=Asia/Jakarta`.
- **Fix**: Set timezone in `.env` and `config/app.php`.

### 7.4 [HIGH] Redis Failure — No Fallback for Auto-Save
- **File**: `app/Services/Exam/ExamAttemptService.php:212-241`
- **Current**: If Redis is down, auto-save fails completely. After 3 retries, student sees warning. Answers are lost on submit.
- **Expected**: Fallback to direct database write if Redis fails.
- **Fix**: Add try-catch around Redis write with DB fallback.

### 7.5 [HIGH] CSP Header Too Permissive
- **File**: `app/Http/Middleware/SecurityHeaders.php:23-28`
- **Current**: CSP includes `'unsafe-inline'` and `'unsafe-eval'`. Weakens XSS protection.
- **Expected**: Remove unsafe directives, use nonce-based inline scripts.
- **Fix**: Implement nonce-based CSP.

### 7.6 [HIGH] Device Fingerprint Column Unused
- **File**: `app/Models/ExamAttempt.php`, `app/Http/Controllers/Siswa/ExamController.php:387-407`
- **Current**: `device_fingerprint` column exists but is never populated or checked. Device lock relies only on IP + user agent substring.
- **Expected**: Populate and verify device fingerprint.
- **Fix**: Generate fingerprint from multiple signals (UA, headers, etc.).

### 7.7 [HIGH] Concurrent Question Import — Order Number Collision
- **File**: `app/Imports/QuestionImport.php:25-50`
- **Current**: Both imports read `max('order')`, both get same value, create duplicate order numbers.
- **Expected**: Use atomic increment or lock.
- **Fix**: Use `lockForUpdate()` on bank record or DB-level auto-increment.

### 7.8 [HIGH] No Optimistic Locking on Question Edit During Active Exam
- **File**: `app/Http/Controllers/Guru/QuestionController.php:69-104`
- **Current**: Two teachers editing same question simultaneously — last save wins, silently overwriting.
- **Expected**: Optimistic locking using `updated_at` check.
- **Fix**: `$soal->where('updated_at', $originalUpdatedAt)->update([...]);`

### 7.9 [HIGH] Student Import — No Within-File Duplicate Check
- **File**: `app/Imports/StudentImport.php:19-93`
- **Current**: `unique:users,username` checks DB only. Two rows with same NIS in same file — second fails silently.
- **Expected**: Pre-validate uniqueness within file.
- **Fix**: Add pre-validation loop for duplicate NIS within file.

### 7.10 [MEDIUM] Session Timeout May Expire During Exam
- **File**: `.env` — `SESSION_LIFETIME=120`
- **Current**: Session expires after 2 minutes without HTTP requests. If student reads long question without interaction, session dies.
- **Expected**: Auto-save extends session. But if auto-save is 30s interval, this should be fine. Verify `SESSION_LIFETIME` is adequate.
- **Fix**: Increase session lifetime or ensure auto-save keeps it alive.

### 7.11 [MEDIUM] No Rate Limiting on Log Activity Endpoint
- **File**: `app/Http/Controllers/Siswa/ExamController.php:305-382`
- **Current**: No rate limiting. Malicious student can spam tab switch events, filling database.
- **Expected**: Add rate limiting middleware.
- **Fix**: Add `throttle:120,1` middleware.

### 7.12 [MEDIUM] Auto-Save Retry Can Overlap With Scheduled Save
- **File**: `resources/js/composables/useAutoSave.ts:31-73`
- **Current**: Failed save retries in 10s, but scheduled save fires every 30s. Can overlap.
- **Expected**: Consolidate retry and scheduled logic.
- **Fix**: Clear retry timeout when scheduled save fires.

### 7.13 [MEDIUM] Import Error Messages — Potential XSS
- **File**: `app/Http/Controllers/Admin/UserImportController.php:34-43`
- **Current**: Excel cell values displayed in error messages without sanitization.
- **Expected**: Sanitize error messages.
- **Fix**: `$errors = array_map(fn ($e) => htmlspecialchars($e), $errors);`

### 7.14 [MEDIUM] No Idempotency Token on Submit Endpoint
- **File**: `resources/js/Pages/Siswa/Ujian/ExamInterface.vue:176-189`
- **Current**: No idempotency key header. Network retries can cause duplicate submissions.
- **Expected**: Add idempotency key.
- **Fix**: Generate UUID per submit request.

### 7.15 [LOW] Reverb Credentials in .env (Standard Practice but Document)
- **File**: `.env:70-72`
- **Current**: Reverb keys in plaintext .env. Normal for dev but document rotation for production.
- **Expected**: Ensure .gitignore covers .env. Rotate for production.
- **Fix**: Verify .gitignore and document credential rotation.

### 7.16 [LOW] Event Payload Broadcasts User Names
- **File**: `app/Events/StudentStartedExam.php:28-36`, `app/Events/StudentSubmittedExam.php:28-39`
- **Current**: Student names sent in broadcast payload. Unnecessary if client has student list.
- **Expected**: Send only user_id, resolve name client-side.
- **Fix**: Remove `user_name` from broadcast payload.

### 7.17 [LOW] ForceSubmitExpired — Checks Duration Only, Not Session ends_at
- **File**: `app/Console/Commands/ForceSubmitExpiredExams.php:22-31`
- **Current**: Uses `isExpired()` which checks duration. Students with proctor-extended time may be force-submitted incorrectly.
- **Expected**: Also check `exam_session.ends_at`.
- **Fix**: Add session end time check.

### 7.18 [LOW] Question Points Not Cast in Model
- **File**: `app/Models/Question.php`
- **Current**: `points` column not cast to float. May return string in some contexts.
- **Expected**: Add cast.
- **Fix**: Add `'points' => 'float'` to model casts.

---

## Priority Fix Order

### Immediate (Before Any Production Use)
1. **GradingController cross-exam access** (1.2, 1.3, 1.4) — data leak between exam sessions
2. **Double-submit race condition** (2.1, 7.2) — use pessimistic locking
3. **SingleSessionExam TOCTOU** (2.2) — use `Cache::add()`
4. **Device lock bypass** (7.1) — move to encrypted storage
5. **.env.example Redis defaults** (4.1, 4.2, 4.3) — critical for HDD performance
6. **Timezone** (7.3) — all times 7 hours off

### High Priority (Before Beta)
7. Tab switch counter reset on resume (2.7)
8. Zero questions / pool_count > available (2.5, 2.6)
9. autoGrade status not updated (3.1)
10. Redis failure fallback (7.4)
11. CSP headers (7.5)
12. File upload double extension (6.4)
13. Performance migration date (4.7)
14. Forum locked thread reply (6.1)

### Medium Priority (Before Launch)
15. All remaining MEDIUM issues — attendance constraints, notification race conditions, answer validation, etc.

### Low Priority (Post-Launch)
16. All LOW issues — log rotation, file cleanup, number formatting, etc.
