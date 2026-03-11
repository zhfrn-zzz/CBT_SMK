# plan.md — Implementation Plan

# SMK LMS + CBT System

## 1. Database Schema

### 1.1 Core Tables

```sql
-- Tahun Ajaran
academic_years
├── id (bigint, PK)
├── name (varchar) -- "2025/2026"
├── semester (enum: 'ganjil', 'genap')
├── is_active (boolean, default false)
├── starts_at (date)
├── ends_at (date)
├── timestamps

-- Jurusan
departments
├── id (bigint, PK)
├── name (varchar) -- "Teknik Komputer dan Jaringan"
├── code (varchar, unique) -- "TKJ"
├── timestamps

-- Kelas
classrooms
├── id (bigint, PK)
├── name (varchar) -- "XI TKJ 1"
├── academic_year_id (FK → academic_years)
├── department_id (FK → departments)
├── grade_level (enum: '10', '11', '12')
├── timestamps

-- Mata Pelajaran
subjects
├── id (bigint, PK)
├── name (varchar) -- "Administrasi Sistem Jaringan"
├── code (varchar, unique) -- "ASJ"
├── department_id (FK → departments, nullable) -- null = mapel umum
├── timestamps

-- Users (extend Laravel default dari starter kit)
users
├── id (bigint, PK)
├── name (varchar)
├── username (varchar, unique) -- NIS untuk siswa, NIP untuk guru
├── email (varchar, unique, nullable)
├── email_verified_at (timestamp, nullable)
├── password (varchar)
├── role (enum: 'admin', 'guru', 'siswa')
├── is_active (boolean, default true)
├── remember_token (varchar, nullable)
├── timestamps

-- Siswa → Kelas (pivot)
classroom_student
├── id (bigint, PK)
├── classroom_id (FK → classrooms)
├── user_id (FK → users)
├── timestamps
├── UNIQUE(classroom_id, user_id)

-- Guru → Mata Pelajaran + Kelas (pivot)
classroom_subject_teacher
├── id (bigint, PK)
├── classroom_id (FK → classrooms)
├── subject_id (FK → subjects)
├── user_id (FK → users) -- guru
├── timestamps
├── UNIQUE(classroom_id, subject_id, user_id)
```

### 1.2 Bank Soal Tables

```sql
-- Bank Soal (container)
question_banks
├── id (bigint, PK)
├── name (varchar) -- "UTS ASJ Kelas XI Semester 1"
├── subject_id (FK → subjects)
├── user_id (FK → users) -- guru pembuat
├── description (text, nullable)
├── timestamps

-- Soal
questions
├── id (bigint, PK)
├── question_bank_id (FK → question_banks)
├── type (enum: 'pilihan_ganda', 'benar_salah', 'esai', 'isian_singkat', 'menjodohkan', 'ordering', 'multiple_answer')
├── content (text) -- rich text soal
├── media_path (varchar, nullable) -- path gambar/audio
├── points (decimal, default 1) -- bobot nilai
├── explanation (text, nullable) -- pembahasan
├── order (int, default 0) -- urutan dalam bank soal
├── metadata (json, nullable) -- data tambahan per tipe soal
├── timestamps

-- Pilihan Jawaban (untuk PG, B/S, Multiple Answer)
question_options
├── id (bigint, PK)
├── question_id (FK → questions)
├── label (varchar) -- "A", "B", "C", "D"
├── content (text) -- teks pilihan
├── media_path (varchar, nullable)
├── is_correct (boolean, default false)
├── order (int, default 0)
├── timestamps

-- Matching Pairs (untuk soal menjodohkan — Phase 2)
question_matching_pairs
├── id (bigint, PK)
├── question_id (FK → questions)
├── premise (text) -- kolom kiri
├── response (text) -- kolom kanan (jawaban benar)
├── order (int, default 0)
├── timestamps

-- Keywords (untuk isian singkat, auto-grade — Phase 2)
question_keywords
├── id (bigint, PK)
├── question_id (FK → questions)
├── keyword (varchar) -- keyword alternatif yang diterima
├── is_primary (boolean, default false)
├── timestamps
```

### 1.3 CBT / Exam Tables

```sql
-- Sesi Ujian
exam_sessions
├── id (bigint, PK)
├── name (varchar) -- "UTS ASJ XI TKJ Sesi 1"
├── subject_id (FK → subjects)
├── user_id (FK → users) -- guru pembuat
├── academic_year_id (FK → academic_years)
├── question_bank_id (FK → question_banks)
├── token (varchar, unique) -- kode akses 6 char
├── duration_minutes (int) -- durasi ujian
├── starts_at (datetime) -- window mulai
├── ends_at (datetime) -- window selesai
├── is_randomize_questions (boolean, default false)
├── is_randomize_options (boolean, default false)
├── is_published (boolean, default false) -- apakah hasil sudah dipublish
├── pool_count (int, nullable) -- jika set, ambil N soal random dari bank
├── kkm (decimal, nullable) -- Kriteria Ketuntasan Minimal
├── max_tab_switches (int, nullable) -- null = unlimited
├── status (enum: 'draft', 'scheduled', 'active', 'completed', 'archived')
├── timestamps

-- Sesi Ujian → Kelas (pivot)
exam_session_classroom
├── id (bigint, PK)
├── exam_session_id (FK → exam_sessions)
├── classroom_id (FK → classrooms)
├── timestamps

-- Soal yang dipilih untuk ujian (jika tidak pakai question pool)
exam_session_questions
├── id (bigint, PK)
├── exam_session_id (FK → exam_sessions)
├── question_id (FK → questions)
├── order (int) -- urutan soal dalam ujian
├── timestamps

-- Attempt siswa mengerjakan ujian
exam_attempts
├── id (bigint, PK)
├── exam_session_id (FK → exam_sessions)
├── user_id (FK → users) -- siswa
├── started_at (datetime)
├── submitted_at (datetime, nullable)
├── remaining_seconds (int, nullable) -- untuk resume
├── is_force_submitted (boolean, default false)
├── ip_address (varchar, nullable)
├── device_fingerprint (varchar, nullable)
├── score (decimal, nullable) -- total nilai setelah grading
├── is_fully_graded (boolean, default false)
├── status (enum: 'in_progress', 'submitted', 'graded')
├── timestamps
├── INDEX(exam_session_id, user_id)

-- Soal per siswa (untuk randomisasi & question pool)
exam_attempt_questions
├── id (bigint, PK)
├── exam_attempt_id (FK → exam_attempts)
├── question_id (FK → questions)
├── order (int) -- urutan soal untuk siswa ini
├── option_order (json, nullable) -- urutan opsi jika di-randomize ["C","A","D","B"]
├── timestamps

-- Jawaban siswa
student_answers
├── id (bigint, PK)
├── exam_attempt_id (FK → exam_attempts)
├── question_id (FK → questions)
├── answer (text, nullable) -- "A" untuk PG, teks untuk esai
├── is_flagged (boolean, default false)
├── is_correct (boolean, nullable) -- null = belum dinilai
├── score (decimal, nullable) -- nilai per soal
├── feedback (text, nullable) -- komentar guru
├── answered_at (datetime, nullable)
├── timestamps
├── INDEX(exam_attempt_id, question_id)

-- Activity Log saat ujian (anti-cheat)
exam_activity_logs
├── id (bigint, PK)
├── exam_attempt_id (FK → exam_attempts)
├── event_type (enum: 'tab_switch', 'fullscreen_exit', 'focus_lost', 'copy_attempt', 'right_click')
├── description (varchar, nullable)
├── created_at (datetime)
├── INDEX(exam_attempt_id)
```

### 1.4 LMS Tables (Phase 3)

```sql
-- Materi Pembelajaran
materials
├── id (bigint, PK)
├── subject_id (FK → subjects)
├── classroom_id (FK → classrooms)
├── user_id (FK → users) -- guru
├── title (varchar)
├── description (text, nullable)
├── type (enum: 'file', 'video_link', 'text')
├── file_path (varchar, nullable)
├── video_url (varchar, nullable)
├── text_content (text, nullable)
├── topic (varchar, nullable)
├── order (int, default 0)
├── timestamps

-- Tracking baca materi
material_progress
├── id (bigint, PK)
├── material_id (FK → materials)
├── user_id (FK → users)
├── is_completed (boolean, default false)
├── completed_at (datetime, nullable)
├── timestamps
├── UNIQUE(material_id, user_id)

-- Tugas
assignments
├── id (bigint, PK)
├── subject_id (FK → subjects)
├── classroom_id (FK → classrooms)
├── user_id (FK → users)
├── title (varchar)
├── description (text)
├── file_path (varchar, nullable)
├── deadline_at (datetime)
├── max_score (decimal, default 100)
├── allow_late_submission (boolean, default false)
├── timestamps

-- Submission tugas
assignment_submissions
├── id (bigint, PK)
├── assignment_id (FK → assignments)
├── user_id (FK → users)
├── content (text, nullable)
├── file_path (varchar, nullable)
├── submitted_at (datetime)
├── is_late (boolean, default false)
├── score (decimal, nullable)
├── feedback (text, nullable)
├── graded_at (datetime, nullable)
├── timestamps
├── UNIQUE(assignment_id, user_id)

-- Forum Diskusi
discussion_threads
├── id (bigint, PK)
├── subject_id (FK → subjects)
├── classroom_id (FK → classrooms)
├── user_id (FK → users)
├── title (varchar)
├── content (text)
├── is_pinned (boolean, default false)
├── timestamps

discussion_replies
├── id (bigint, PK)
├── discussion_thread_id (FK → discussion_threads)
├── user_id (FK → users)
├── content (text)
├── timestamps

-- Pengumuman
announcements
├── id (bigint, PK)
├── user_id (FK → users)
├── classroom_id (FK → classrooms, nullable) -- null = broadcast
├── title (varchar)
├── content (text)
├── is_pinned (boolean, default false)
├── timestamps

-- Presensi
attendances
├── id (bigint, PK)
├── classroom_id (FK → classrooms)
├── subject_id (FK → subjects)
├── user_id (FK → users) -- guru
├── meeting_date (date)
├── meeting_number (int)
├── access_code (varchar, nullable)
├── timestamps

attendance_records
├── id (bigint, PK)
├── attendance_id (FK → attendances)
├── user_id (FK → users) -- siswa
├── status (enum: 'hadir', 'izin', 'sakit', 'alfa')
├── note (varchar, nullable)
├── timestamps
├── UNIQUE(attendance_id, user_id)
```

### 1.5 System Tables (Phase 4)

```sql
-- Notifications (menggunakan Laravel notification system)
notifications
├── id (uuid, PK)
├── type (varchar)
├── notifiable_type (varchar)
├── notifiable_id (bigint)
├── data (json)
├── read_at (datetime, nullable)
├── timestamps
├── INDEX(notifiable_type, notifiable_id)

-- Audit Trail
audit_logs
├── id (bigint, PK)
├── user_id (FK → users)
├── action (varchar) -- "created", "updated", "deleted"
├── auditable_type (varchar)
├── auditable_id (bigint)
├── old_values (json, nullable)
├── new_values (json, nullable)
├── ip_address (varchar, nullable)
├── created_at (datetime)
├── INDEX(auditable_type, auditable_id)
├── INDEX(user_id)
├── INDEX(created_at)

-- KD Tagging (Phase 4)
competency_standards
├── id (bigint, PK)
├── subject_id (FK → subjects)
├── code (varchar) -- "3.4"
├── description (text)
├── timestamps

question_competency
├── question_id (FK → questions)
├── competency_standard_id (FK → competency_standards)
├── PRIMARY KEY(question_id, competency_standard_id)
```

---

## 2. API & Route Structure

### 2.1 Route Organization

```
routes/web.php
│
├── Auth routes (dari Laravel Vue Starter Kit — sudah ada)
│   ├── GET  /login
│   ├── POST /login
│   ├── POST /logout
│   ├── GET  /register (disable atau limit ke admin-only)
│   └── ...
│
├── Admin routes (prefix: /admin, middleware: auth, role:admin)
│   ├── Dashboard
│   │   └── GET  /admin/dashboard
│   ├── Users
│   │   ├── GET    /admin/users
│   │   ├── GET    /admin/users/create
│   │   ├── POST   /admin/users
│   │   ├── GET    /admin/users/{user}/edit
│   │   ├── PUT    /admin/users/{user}
│   │   ├── DELETE /admin/users/{user}
│   │   └── POST   /admin/users/import
│   ├── Academic Structure
│   │   ├── Resource: /admin/academic-years
│   │   ├── Resource: /admin/departments
│   │   ├── Resource: /admin/classrooms
│   │   └── Resource: /admin/subjects
│   ├── Classroom Assignment
│   │   ├── POST /admin/classrooms/{classroom}/assign-students
│   │   └── POST /admin/classrooms/{classroom}/assign-teachers
│   └── System (Phase 4)
│       ├── GET  /admin/audit-logs
│       └── POST /admin/backup
│
├── Guru routes (prefix: /guru, middleware: auth, role:guru)
│   ├── Dashboard
│   │   └── GET /guru/dashboard
│   ├── Bank Soal
│   │   ├── Resource: /guru/bank-soal
│   │   ├── Resource: /guru/bank-soal/{bankSoal}/soal  (nested)
│   │   ├── POST /guru/bank-soal/{bankSoal}/soal/import
│   │   └── GET  /guru/bank-soal/{bankSoal}/soal/template-download
│   ├── Exam Sessions
│   │   ├── Resource: /guru/ujian
│   │   ├── GET  /guru/ujian/{ujian}/proctor
│   │   ├── POST /guru/ujian/{ujian}/override
│   │   └── GET  /guru/ujian/{ujian}/hasil
│   ├── Grading
│   │   ├── GET  /guru/grading/{examSession}
│   │   ├── PUT  /guru/grading/{studentAnswer}
│   │   └── POST /guru/ujian/{ujian}/publish-results
│   ├── LMS (Phase 3)
│   │   ├── Resource: /guru/materi
│   │   ├── Resource: /guru/tugas
│   │   ├── GET  /guru/tugas/{tugas}/submissions
│   │   ├── PUT  /guru/tugas/submissions/{submission}/grade
│   │   ├── Resource: /guru/presensi
│   │   └── Resource: /guru/pengumuman
│   └── Export
│       ├── GET /guru/export/nilai/{examSession}
│       └── GET /guru/export/presensi/{classroom}
│
├── Siswa routes (prefix: /siswa, middleware: auth, role:siswa)
│   ├── Dashboard
│   │   └── GET /siswa/dashboard
│   ├── Exam
│   │   ├── GET  /siswa/ujian
│   │   ├── POST /siswa/ujian/{ujian}/verify-token
│   │   ├── POST /siswa/ujian/{ujian}/start
│   │   ├── POST /siswa/ujian/{ujian}/save-answers
│   │   ├── POST /siswa/ujian/{ujian}/submit
│   │   └── GET  /siswa/ujian/{ujian}/hasil
│   ├── LMS (Phase 3)
│   │   ├── GET  /siswa/materi
│   │   ├── POST /siswa/materi/{material}/complete
│   │   ├── GET  /siswa/tugas
│   │   ├── POST /siswa/tugas/{tugas}/submit
│   │   ├── GET  /siswa/forum
│   │   └── GET  /siswa/presensi
│   └── Nilai
│       └── GET /siswa/nilai
│
└── API routes (prefix: /api, stateless)
    ├── POST /api/exam/heartbeat
    └── POST /api/exam/log-activity
```

### 2.2 WebSocket Channels (Laravel Reverb)

```
Private Channels:
├── exam.{examSessionId}                       -- proctor: real-time student status
├── exam.{examSessionId}.student.{userId}      -- per-student: override notifications
└── user.{userId}                              -- personal notifications

Events:
├── StudentStartedExam        → exam.{examSessionId}
├── StudentSubmittedExam      → exam.{examSessionId}
├── AnswerProgressUpdated     → exam.{examSessionId}
├── TabSwitchDetected         → exam.{examSessionId}
├── ExamTimeExtended          → exam.{examSessionId}.student.{userId}
├── ExamTerminated            → exam.{examSessionId}.student.{userId}
├── QuestionInvalidated       → exam.{examSessionId}
└── NotificationSent          → user.{userId}
```

---

## 3. Service Layer Architecture

```
app/Services/
├── Exam/
│   ├── ExamSessionService.php        -- CRUD sesi ujian, konfigurasi
│   ├── ExamAttemptService.php        -- start, save, submit, resume logic
│   ├── ExamRandomizerService.php     -- randomize soal & opsi per siswa
│   ├── ExamTimerService.php          -- server-side time validation
│   └── ProctorService.php           -- monitoring, override actions
├── Grading/
│   ├── AutoGradingService.php        -- grade PG, B/S, isian singkat
│   ├── ManualGradingService.php      -- esai grading helpers
│   └── ScoreCalculatorService.php    -- hitung total nilai, status KKM
├── Import/
│   ├── StudentImportService.php      -- parse & import siswa dari Excel
│   └── QuestionImportService.php     -- parse & import soal dari Excel
├── LMS/
│   ├── MaterialService.php
│   ├── AssignmentService.php
│   └── AttendanceService.php
└── Analytics/
    ├── ItemAnalysisService.php       -- analisis butir soal
    └── ReportService.php             -- generate laporan
```

---

## 4. Implementation Task Breakdown

### Phase 1: Core CBT (Minggu 1-6)

#### Sprint 1 — Foundation (Minggu 1-2)
```
Task 1.1: Project Setup (Starter Kit)
├── [T1.1.1] laravel new smk-lms --using vue
│   └── Ini langsung memberikan: Laravel 12, Vue 3, Inertia 2, TypeScript,
│       Tailwind 4, shadcn-vue, Auth (login/register/password reset/verify),
│       AppLayout, Sidebar, Navbar — SEMUA sudah configured
├── [T1.1.2] Install & configure Redis (session, cache, queue drivers)
│   └── composer require predis/predis (jika belum ada)
│   └── Update .env: SESSION_DRIVER=redis, CACHE_STORE=redis, QUEUE_CONNECTION=redis
├── [T1.1.3] Install & configure Laravel Reverb
│   └── php artisan install:broadcasting
├── [T1.1.4] Create PHP Enums: UserRole, QuestionType, ExamStatus, ExamAttemptStatus
├── [T1.1.5] Extend User model: add role, username, is_active fields
│   └── Migration: add columns to users table
│   └── Update User model with enum cast + relationships
├── [T1.1.6] Create RoleMiddleware + register in bootstrap/app.php
├── [T1.1.7] Modify auth redirect: role-based redirect after login
│   └── Admin → /admin/dashboard
│   └── Guru → /guru/dashboard
│   └── Siswa → /siswa/dashboard
├── [T1.1.8] Disable public registration (atau redirect ke admin-only)
├── [T1.1.9] Install additional npm packages:
│   └── npm install @tanstack/vue-table
│   └── npm install @tiptap/vue-3 @tiptap/starter-kit @tiptap/extension-image
│   └── npx shadcn-vue@latest add table dialog alert-dialog badge select input textarea tabs card dropdown-menu
└── [T1.1.10] Create sidebar navigation per role (extend starter kit AppSidebar)

Task 1.2: Academic Structure & User Management
├── [T1.2.1] Migration: academic_years, departments
├── [T1.2.2] Migration: classrooms, subjects, pivot tables
├── [T1.2.3] Models + relationships untuk semua tabel di atas
├── [T1.2.4] TypeScript types: resources/js/types/academic.ts
├── [T1.2.5] Admin: CRUD Users page (TanStack Table + shadcn forms)
├── [T1.2.6] Admin: Bulk import siswa (Excel upload + queue job)
│   └── composer require maatwebsite/excel
├── [T1.2.7] Admin: CRUD Academic Years, Departments, Classrooms, Subjects
├── [T1.2.8] Admin: Assign siswa ke kelas, guru ke mapel+kelas
└── [T1.2.9] Seeder: demo data (1 admin, 5 guru, 50 siswa, kelas, mapel)
```

#### Sprint 2 — Bank Soal (Minggu 2-3)
```
Task 1.3: Bank Soal
├── [T1.3.1] Migration: question_banks, questions, question_options
├── [T1.3.2] Models + relationships
├── [T1.3.3] TypeScript types: resources/js/types/exam.ts
├── [T1.3.4] Guru: CRUD Bank Soal (list, create, edit, delete)
├── [T1.3.5] Guru: CRUD Soal dalam bank (PG, B/S, Esai)
│   ├── Form builder per tipe soal
│   ├── Tiptap rich text editor integration
│   ├── Image upload untuk soal
│   └── Preview soal
├── [T1.3.6] Guru: Import soal dari Excel
│   ├── Template download
│   ├── Upload + validation + preview
│   └── Queue job import
└── [T1.3.7] Policy: guru hanya akses bank soal yang mereka buat
```

#### Sprint 3 — CBT Engine (Minggu 3-5) ⚡ CRITICAL PATH
```
Task 1.4: Exam Session Setup
├── [T1.4.1] Migration: exam_sessions, exam_session_classroom, exam_session_questions
├── [T1.4.2] Models + relationships
├── [T1.4.3] Guru: Create exam session form (semua konfigurasi)
├── [T1.4.4] ExamRandomizerService: logic random soal + opsi
├── [T1.4.5] Token generation (6 char unique per session)
└── [T1.4.6] Guru: List exam sessions + status management

Task 1.5: Student Exam Interface ⚡ MOST CRITICAL
├── [T1.5.1] Migration: exam_attempts, exam_attempt_questions, student_answers, exam_activity_logs
├── [T1.5.2] TypeScript types: ExamAttempt, StudentAnswer, ExamState, SavePayload
├── [T1.5.3] Siswa: Exam list page (upcoming, in-progress, completed)
├── [T1.5.4] Siswa: Token verification page
├── [T1.5.5] ExamAttemptService: start exam logic
│   ├── Validate: waktu, token, belum pernah submit
│   ├── Generate randomized question set per siswa
│   ├── Create exam_attempt + exam_attempt_questions
│   └── Return all questions + options to frontend in 1 request
├── [T1.5.6] Vue: ExamInterface.vue (main exam page)
│   ├── Composable: useExamState.ts — manage all answers in reactive state
│   ├── Composable: useExamTimer.ts — countdown, sync with server
│   ├── Composable: useAutoSave.ts — batch save every 30s
│   ├── Component: QuestionCard.vue — render soal per tipe
│   ├── Component: NavigationPanel.vue — grid nomor soal + status warna
│   ├── Component: ExamTimer.vue — countdown display
│   ├── Component: ExamHeader.vue — info ujian, tombol flag, tombol submit
│   └── Fullscreen mode enter/exit handling
├── [T1.5.7] Auto-save implementation
│   ├── Client: localStorage write on every answer change
│   ├── Client: POST /save-answers every 30 seconds via useAutoSave
│   ├── Server: save to Redis key (exam:{sessionId}:student:{userId})
│   └── Queue job: PersistAnswersJob — Redis → MySQL every 60 seconds
├── [T1.5.8] Submit exam logic
│   ├── Client: shadcn AlertDialog (stats: answered/total/flagged)
│   ├── Server: persist all answers to MySQL, clear Redis
│   ├── Server: trigger auto-grading for PG/B/S
│   └── Server: record submitted_at, update status
├── [T1.5.9] Auto-submit on time expire
│   ├── Client: timer hits 0 → auto-trigger submit
│   └── Server: scheduled command check expired attempts → force submit
├── [T1.5.10] Resume session
│   ├── On login: check for active exam_attempt
│   ├── Redirect to exam if exists
│   ├── Load answers from Redis (or MySQL fallback)
│   ├── Reconcile with localStorage
│   └── Resume timer from server calculated remaining time
└── [T1.5.11] Anti-cheat basic
    ├── visibilitychange listener → log tab switch
    ├── fullscreenchange listener → log exit
    ├── POST /api/exam/log-activity (fire and forget)
    └── Store in exam_activity_logs
```

#### Sprint 4 — Grading & Results (Minggu 5-6)
```
Task 1.6: Grading
├── [T1.6.1] AutoGradingService: grade PG + B/S on submit
├── [T1.6.2] ScoreCalculatorService: weighted score calculation
├── [T1.6.3] Guru: Manual grading interface for esai
│   ├── Per-student view
│   ├── Per-question view
│   ├── Input nilai + feedback
│   └── Progress indicator (X/Y graded)
├── [T1.6.4] Guru: Exam results page
│   ├── TanStack Table: semua siswa + nilai (sortable, searchable)
│   ├── Status lulus/remedial based on KKM
│   ├── Summary stats: rata-rata, tertinggi, terendah
│   └── Publish/unpublish toggle
├── [T1.6.5] Siswa: View results page
│   ├── Nilai total + status
│   ├── Detail per soal + pembahasan
│   └── Only visible after guru publish
└── [T1.6.6] Export hasil ke Excel (queue job)

Task 1.7: Dashboard Phase 1
├── [T1.7.1] Admin dashboard: stats cards (users, exams today)
├── [T1.7.2] Guru dashboard: classes, upcoming exams, pending grading
└── [T1.7.3] Siswa dashboard: upcoming exams, recent scores
```

---

### Phase 2: CBT Advanced + Proctor (Minggu 7-10)

```
Task 2.1: Proctor Dashboard
├── [T2.1.1] Guru: Proctor page (real-time via Reverb)
├── [T2.1.2] Broadcasting events: StudentStarted, ProgressUpdated, TabSwitch
├── [T2.1.3] Real-time student status list
├── [T2.1.4] Visual indicators: progress bar, warning icons
└── [T2.1.5] Manual override UI + backend (extend, reset, terminate, invalidate)

Task 2.2: Additional Question Types
├── [T2.2.1] Migration: question_matching_pairs, question_keywords
├── [T2.2.2] Isian Singkat: form builder + keyword-based auto-grading
├── [T2.2.3] Menjodohkan: form builder + drag-and-drop UI
├── [T2.2.4] Multiple Answer: checkbox UI + scoring logic
├── [T2.2.5] Ordering: drag-and-drop UI + order validation
└── [T2.2.6] Update QuestionCard.vue to handle all types

Task 2.3: Security Enhancement
├── [T2.3.1] Device/IP locking on exam start
├── [T2.3.2] Tab switch limit per exam config
├── [T2.3.3] Warning escalation (warn → warn → auto-submit)
└── [T2.3.4] Guru: exam activity log viewer per siswa

Task 2.4: Remedial System
├── [T2.4.1] Auto-detect remedial (score < KKM)
├── [T2.4.2] Guru: create remedial exam
├── [T2.4.3] Remedial score tracking + replacement logic
└── [T2.4.4] Siswa: remedial indicator on dashboard
```

---

### Phase 3: LMS Core (Minggu 11-14)

```
Task 3.1: Materials
├── [T3.1.1] Migrations: materials, material_progress
├── [T3.1.2] Guru: CRUD materi (upload file, embed video, rich text)
├── [T3.1.3] Siswa: browse & view materi
├── [T3.1.4] Siswa: mark as completed
└── [T3.1.5] Guru: progress overview per kelas

Task 3.2: Assignments
├── [T3.2.1] Migrations: assignments, assignment_submissions
├── [T3.2.2] Guru: CRUD tugas
├── [T3.2.3] Siswa: view tugas + submit
├── [T3.2.4] Guru: grade submissions
└── [T3.2.5] Late submission handling

Task 3.3: Discussion & Announcements
├── [T3.3.1] Migrations: discussion_threads, discussion_replies, announcements
├── [T3.3.2] Forum: thread list, create, reply
├── [T3.3.3] Pin thread feature
├── [T3.3.4] Announcements: create, list, pin
└── [T3.3.5] Dashboard integration

Task 3.4: Attendance
├── [T3.4.1] Migrations: attendances, attendance_records
├── [T3.4.2] Guru: open session, generate code
├── [T3.4.3] Siswa: input code → mark hadir
├── [T3.4.4] Guru: manual override status
└── [T3.4.5] Recap & export
```

---

### Phase 4: Analytics & Polish (Minggu 15-17)

```
Task 4.1: Analytics
├── [T4.1.1] ItemAnalysisService
├── [T4.1.2] Guru: analisis butir soal page
├── [T4.1.3] Advanced dashboard
└── [T4.1.4] KD tagging + per-KD analysis

Task 4.2: Notifications
├── [T4.2.1] Notification bell component (extend starter kit header)
├── [T4.2.2] Trigger notifications on events
└── [T4.2.3] Optional: WhatsApp gateway integration

Task 4.3: Audit & System
├── [T4.3.1] Migration: audit_logs
├── [T4.3.2] Audit trait (auto-log on model events)
├── [T4.3.3] Admin: audit log viewer
├── [T4.3.4] Automated backup script (cron)
└── [T4.3.5] Print soal to PDF

Task 4.4: Data Exchange
├── [T4.4.1] Export nilai format rapor
├── [T4.4.2] Export kompatibel Dapodik
└── [T4.4.3] Import data siswa dari Dapodik
```

---

## 5. Key Technical Implementation Notes

### 5.1 Auto-Save Flow (Detailed)

```
CLIENT SIDE (Vue + TypeScript):
1. Siswa klik jawaban → update reactive state (useExamState composable)
2. Immediately write to localStorage: exam_${sessionId}_answers
3. useAutoSave composable: setiap 30 detik, POST /save-answers
   Body: { answers: Record<string, string>, timestamp: number }
4. On success: update last_saved_at indicator
5. On failure: retry in 10 seconds, max 3 retries, then show warning

SERVER SIDE (Laravel):
1. Controller receives answers JSON
2. Validate: exam still active, user owns attempt, within time window
3. Write to Redis: SET exam:{sessionId}:student:{userId}:answers JSON
4. Write to Redis: SET exam:{sessionId}:student:{userId}:last_save TIMESTAMP
5. Return: { saved: true, server_time: number, remaining_seconds: number }

QUEUE JOB (PersistAnswersJob):
1. Runs every 60 seconds via scheduler
2. SCAN Redis keys matching exam:*:student:*:answers
3. For each: upsert into student_answers table (batch)
4. DO NOT delete Redis key (keep for fast reads)
5. Delete Redis key only after exam is submitted
```

### 5.2 Timer Synchronization

```
1. Saat start exam: server returns { started_at, duration_seconds, server_time }
2. Client calculates: remaining = duration_seconds - (server_time - started_at)
3. Client runs local countdown (setInterval every 1 second)
4. Every auto-save response includes: { remaining_seconds } from server
5. Client reconciles: if server remaining differs > 3 seconds, use server value
6. At 0: client auto-submits
7. Server-side: scheduled command checks exam_attempts where
   started_at + duration < now() AND status = 'in_progress' → force submit
```

### 5.3 Question Loading Strategy

```
Saat siswa mulai ujian, SATU request memuat semua data:
POST /siswa/ujian/{id}/start → returns Inertia page with:

interface ExamStartPayload {
  exam: { name: string; duration: number; total_questions: number };
  questions: Array<{
    id: number;
    order: number;
    content: string;
    type: QuestionType;
    media_url: string | null;
    options: Array<{
      id: number;
      label: string;
      content: string;
    }> | null;
  }>;
  saved_answers: Record<string, string>;
  started_at: number;
  server_time: number;
  remaining_seconds: number;
}

Semua soal dimuat sekaligus ke client state.
Navigasi antar soal = ZERO server requests.
```

### 5.4 Redis Key Structure

```
Session & Cache:
├── laravel_session:{sessionId}
├── laravel_cache:{key}

Exam-specific:
├── exam:{examSessionId}:student:{userId}:answers     -- JSON jawaban
├── exam:{examSessionId}:student:{userId}:last_save    -- timestamp
├── exam:{examSessionId}:student:{userId}:flags        -- JSON flagged
├── exam:{examSessionId}:online_count                  -- atomic counter
└── exam:{examSessionId}:question_set:{userId}         -- cached randomized set

TTL: semua exam keys expire 24 jam setelah exam_session.ends_at
```

### 5.5 Database Indexing Strategy

```sql
-- CRITICAL indexes untuk performa CBT di HDD

ALTER TABLE student_answers ADD INDEX idx_attempt_question (exam_attempt_id, question_id);
ALTER TABLE exam_attempts ADD INDEX idx_session_user (exam_session_id, user_id);
ALTER TABLE exam_attempts ADD INDEX idx_status (status);
ALTER TABLE exam_activity_logs ADD INDEX idx_attempt (exam_attempt_id);
ALTER TABLE classroom_student ADD INDEX idx_classroom (classroom_id);
ALTER TABLE classroom_student ADD INDEX idx_user (user_id);
ALTER TABLE questions ADD INDEX idx_bank (question_bank_id);
ALTER TABLE exam_session_classroom ADD INDEX idx_session (exam_session_id);
ALTER TABLE exam_session_classroom ADD INDEX idx_classroom (classroom_id);
ALTER TABLE notifications ADD INDEX idx_user_read (notifiable_type, notifiable_id, read_at);
ALTER TABLE audit_logs ADD INDEX idx_auditable (auditable_type, auditable_id);
```

---

## 6. Development Environment Setup

```bash
# Prerequisites
- PHP 8.2+ with extensions: redis, gd, mbstring, xml, zip, curl
- Composer 2.x
- Node.js 20+ & npm
- MySQL 8.x
- Redis 7.x
- Git

# Step 1: Create project from official starter kit
laravel new smk-lms --using vue
cd smk-lms

# Step 2: Install additional PHP packages
composer require predis/predis            # Redis client
composer require maatwebsite/excel        # Excel import/export
composer require laravel/reverb           # WebSocket (jika belum)

# Step 3: Install additional npm packages
npm install @tanstack/vue-table           # DataTable
npm install @tiptap/vue-3 @tiptap/starter-kit @tiptap/extension-image  # Rich text

# Step 4: Add shadcn-vue components yang dibutuhkan
npx shadcn-vue@latest add table dialog alert-dialog badge select
npx shadcn-vue@latest add input textarea tabs card dropdown-menu
npx shadcn-vue@latest add toast progress separator sheet

# Step 5: Configure .env
# Copy .env.example → .env, then set:
# DB_DATABASE=smk_lms, DB_USERNAME=..., DB_PASSWORD=...
# SESSION_DRIVER=redis
# CACHE_STORE=redis
# QUEUE_CONNECTION=redis
# BROADCAST_CONNECTION=reverb

# Step 6: Setup database & run
php artisan migrate --seed
php artisan serve &
npm run dev &
php artisan queue:work redis &
php artisan reverb:start &
```

---

## 7. Testing Strategy

### Critical paths yang HARUS di-test:

```
Feature Tests (Pest PHP):
├── Auth: login per role, middleware blocking, role-based redirect
├── Exam Start: token validation, time window, duplicate prevention
├── Auto-Save: save to Redis, persist to MySQL, concurrent saves
├── Submit: auto-grade accuracy, score calculation, status update
├── Resume: answer restoration, timer continuation
├── Force Submit: time expiry, scheduled command
├── Override: extend time, terminate, invalidate question
├── Import: Excel parsing, validation, duplicate handling
└── Grading: auto-grade correctness, manual grade flow

TypeScript Type Safety:
├── All Inertia page props typed
├── All API payloads typed
├── All composable return types explicit
└── No `any` types allowed
```

---

## 8. Deployment Checklist

```
Server Requirements:
├── PHP 8.2+ with extensions: redis, gd, mbstring, xml, zip
├── MySQL 8.x (tuned: innodb_buffer_pool_size = 8G for 16GB RAM)
├── Redis 7.x
├── Nginx or Apache
├── Supervisor (for queue workers + Reverb)
└── SSL certificate (HTTPS)

Laravel Optimization:
├── php artisan config:cache
├── php artisan route:cache
├── php artisan view:cache
├── php artisan event:cache
├── composer install --optimize-autoloader --no-dev
└── npm run build

Supervisor Config:
├── [program:smk-lms-worker] → php artisan queue:work redis --tries=3
└── [program:smk-lms-reverb] → php artisan reverb:start

MySQL Tuning (for HDD + 16GB RAM):
├── innodb_buffer_pool_size = 8G
├── innodb_log_file_size = 256M
├── innodb_flush_log_at_trx_commit = 2
├── innodb_flush_method = O_DIRECT
├── max_connections = 200
└── query_cache_type = 0 (deprecated in MySQL 8, use Redis)

Cron:
├── * * * * * php artisan schedule:run
├── 0 2 * * * mysqldump smk_lms > /backup/smk_lms_$(date +\%Y\%m\%d).sql
└── Schedule: check expired exams every minute → force submit
```
