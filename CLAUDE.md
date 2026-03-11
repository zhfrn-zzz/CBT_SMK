# CLAUDE.md — SMK LMS + CBT System

## Project Overview

Sistem Learning Management System (LMS) dan Computer Based Test (CBT) untuk Sekolah Menengah Kejuruan (SMK). Dibangun untuk menangani 500+ siswa ujian secara bersamaan pada server dengan spesifikasi Intel Xeon 20 core, 16GB RAM, dan HDD.

## Tech Stack

| Layer | Technology | Version |
|-------|-----------|---------|
| Backend | Laravel | 12.x |
| Frontend | Vue 3 + Inertia.js + TypeScript | Vue 3.5+, Inertia 2.x |
| UI Components | shadcn-vue + TanStack Table | Latest |
| Styling | Tailwind CSS | 4.x |
| Database | MySQL | 8.x |
| Cache & Queue | Redis | 7.x |
| WebSocket | Laravel Reverb | 1.x |
| Auth | Laravel Official Vue Starter Kit | Built-in |
| Testing | Pest PHP (backend), Vitest (frontend) | Latest |
| Rich Text Editor | Tiptap | Latest |

**Initialized via**: `laravel new smk-lms --using vue` (official Laravel Vue starter kit)

## Architecture Decisions

### Why Official Laravel Vue Starter Kit
- Provides pre-configured: Vue 3 + Composition API + TypeScript + Inertia.js + Tailwind CSS + shadcn-vue + Auth (login, register, password reset, email verification)
- Eliminates 2-3 days of boilerplate setup
- Maintained by Laravel team — guaranteed compatibility
- Includes AppLayout, Sidebar, Navbar components out of the box

### Why Inertia.js instead of SPA or Livewire
- **Not Livewire**: 500 siswa × setiap klik = server request. Di HDD, ini menjadi bottleneck fatal. Livewire's server-rendered interactivity terlalu boros I/O.
- **Not separate SPA**: Menghindari dua deployment (PHP + Node.js). Guru yang maintain familiar Laravel, bukan Node.js ops.
- **Inertia.js**: Single monolith deployment. Routing tetap di Laravel. Frontend Vue handles client-side state. CBT state disimpan di browser, hanya auto-save periodik ke server = beban server turun drastis.

### Why Redis is Essential (not optional)
- HDD latency ~5-10ms vs SSD ~0.1ms. Tanpa Redis, 500 concurrent user auto-save akan antri di disk.
- Redis digunakan untuk: session driver, cache driver, queue driver, auto-save buffer sebelum persist ke MySQL.

### Why shadcn-vue (not PrimeVue)
- Sudah ter-include dan ter-configure di Laravel Vue starter kit — zero setup tambahan.
- Copy-paste component model = full ownership, bisa dimodifikasi bebas.
- Native Tailwind CSS, sangat ringan — penting untuk 500 concurrent users.
- Untuk DataTable, gunakan TanStack Table (@tanstack/vue-table) — lebih fleksibel dan ringan.

### Why TypeScript
- Sudah ter-configure di starter kit — zero setup tambahan.
- Type safety mengurangi runtime errors terutama di CBT interface yang kritis.
- Autocomplete dan refactoring lebih aman.
- Interface/Type definitions untuk exam data structures mengurangi bug.

## Project Structure

```
smk-lms/
├── app/
│   ├── Enums/                  # PHP Enums (UserRole, QuestionType, ExamStatus, dll)
│   ├── Events/                 # Broadcasting events (ExamStarted, AnswerSaved, dll)
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Admin/          # AdminController, UserManagementController
│   │   │   ├── Guru/           # BankSoalController, ExamController, GradingController
│   │   │   ├── Siswa/          # ExamTakingController, MaterialController
│   │   │   └── Auth/           # (from starter kit)
│   │   ├── Middleware/         # RoleMiddleware, ExamSessionMiddleware
│   │   └── Requests/          # Form Request validation classes
│   ├── Models/                 # Eloquent models
│   ├── Services/               # Business logic (ExamService, GradingService, dll)
│   ├── Jobs/                   # Queue jobs (PersistAnswers, CalculateGrades, dll)
│   └── Policies/              # Authorization policies per model
├── database/
│   ├── migrations/
│   ├── seeders/                # Demo data, default admin user
│   └── factories/             # Model factories for testing
├── resources/
│   └── js/
│       ├── components/         # shadcn-vue components (auto-generated location)
│       │   └── ui/             # Base shadcn components (Button, Dialog, etc.)
│       ├── Components/         # Custom app components
│       │   ├── Exam/           # QuestionCard, Timer, NavigationPanel
│       │   ├── LMS/            # MaterialCard, AssignmentForm
│       │   └── DataTable/      # TanStack Table wrappers
│       ├── Pages/              # Inertia pages (mirrors route structure)
│       │   ├── Admin/
│       │   ├── Guru/
│       │   ├── Siswa/
│       │   └── Auth/           # (from starter kit)
│       ├── composables/        # Vue composables (useExamTimer, useAutoSave, dll)
│       ├── types/              # TypeScript type definitions
│       │   ├── exam.ts         # ExamSession, Question, Answer types
│       │   ├── user.ts         # User, Role types
│       │   └── index.ts        # Re-exports
│       ├── lib/                # Utility functions (from starter kit)
│       └── layouts/            # Layout components (from starter kit)
├── routes/
│   ├── web.php                 # All Inertia routes
│   ├── channels.php            # WebSocket channel authorization
│   └── console.php             # Artisan command scheduling
├── config/
├── tests/
│   ├── Feature/
│   └── Unit/
├── CLAUDE.md                   # This file
├── PRD.md                      # Product Requirements Document
└── plan.md                     # Implementation Plan
```

## Coding Conventions

### PHP / Laravel
- **Strict typing**: Gunakan `declare(strict_types=1)` di semua file PHP.
- **Return types**: Semua method harus punya return type declaration.
- **Enum over constants**: Gunakan PHP Enum untuk values tetap (role, status, tipe soal).
- **Service pattern**: Business logic di `app/Services/`, controller hanya handle request/response.
- **Form Requests**: Semua validasi di Form Request class, bukan di controller.
- **Policy-based auth**: Gunakan Laravel Policy untuk authorization, bukan manual check di controller.
- **Eager loading**: Selalu gunakan `with()` untuk relasi. N+1 query DILARANG — ini kritis untuk HDD.
- **Database indexing**: Setiap foreign key dan kolom yang sering di-query HARUS di-index.
- **Chunk processing**: Untuk operasi bulk (import siswa, batch grading), gunakan `chunk()` atau `LazyCollection`.
- **Queue heavy tasks**: Grading esai batch, export Excel, import CSV — semua via Queue job.

### Vue / TypeScript / Frontend
- **Composition API only**: Tidak menggunakan Options API.
- **`<script setup lang="ts">` syntax**: Selalu gunakan script setup dengan TypeScript.
- **Composables for reuse**: Logic yang dipakai di banyak komponen → extract ke `composables/`.
- **shadcn-vue components**: Gunakan komponen dari `components/ui/` sebagai base. Jangan install UI library lain.
- **TanStack Table**: Untuk semua DataTable (daftar siswa, nilai, soal). Bungkus di `Components/DataTable/`.
- **Props typing**: Gunakan `defineProps<T>()` dengan TypeScript interface.
- **Emits typing**: Gunakan `defineEmits<T>()` dengan TypeScript interface.
- **Type definitions**: Semua data structure yang dikirim dari backend harus punya TypeScript interface di `types/`.

### Naming Conventions
- **Database tables**: snake_case, plural (`exam_sessions`, `question_banks`, `student_answers`)
- **Models**: PascalCase, singular (`ExamSession`, `QuestionBank`, `StudentAnswer`)
- **Controllers**: PascalCase, singular + Controller (`ExamSessionController`)
- **Vue components**: PascalCase (`QuestionCard.vue`, `ExamTimer.vue`)
- **Vue composables**: camelCase with `use` prefix (`useExamTimer.ts`, `useAutoSave.ts`)
- **TypeScript types/interfaces**: PascalCase (`ExamSession`, `QuestionData`, `SaveAnswersPayload`)
- **TypeScript files**: camelCase (`exam.ts`, `user.ts`)
- **Routes**: kebab-case (`/guru/bank-soal`, `/siswa/ujian/{id}`)
- **API endpoints**: kebab-case (`/api/exam-sessions`, `/api/student-answers`)
- **Enum values**: PascalCase (`UserRole::Guru`, `QuestionType::PilihanGanda`)

### Language
- **Code**: English (variable names, function names, class names)
- **UI text**: Bahasa Indonesia (labels, messages, notifications)
- **Comments**: Bahasa Indonesia boleh untuk complex business logic, English untuk general
- **Database column names**: English
- **Enum labels/display**: Bahasa Indonesia
- **TypeScript interfaces**: English

## Common Commands

```bash
# Development — jalankan di terminal terpisah
php artisan serve                    # Start Laravel dev server
npm run dev                          # Start Vite dev server
php artisan queue:work redis         # Start queue worker
php artisan reverb:start             # Start WebSocket server

# Database
php artisan migrate                  # Run migrations
php artisan migrate:fresh --seed     # Reset DB with seeders
php artisan make:migration           # Create migration

# Code Generation
php artisan make:model ModelName -mfsc  # Model + migration + seeder + controller
php artisan make:request RequestName    # Form Request
php artisan make:policy PolicyName      # Policy
php artisan make:job JobName            # Queue Job
php artisan make:event EventName        # Event

# shadcn-vue — tambah komponen baru
npx shadcn-vue@latest add button        # Add Button component
npx shadcn-vue@latest add dialog        # Add Dialog component
npx shadcn-vue@latest add table         # Add Table component

# Testing
php artisan test                     # Run all tests
php artisan test --filter=ExamTest   # Run specific test
npx vitest                           # Run frontend tests

# Cache & Optimization (production only)
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
```

## Performance Rules (CRITICAL — HDD + 16GB RAM)

1. **NEVER** query tanpa index pada tabel yang akan besar (student_answers, exam_logs).
2. **ALWAYS** use Redis untuk session dan cache driver. Jangan pakai `file` atau `database` driver.
3. **ALWAYS** eager load relasi. Gunakan `php artisan telescope` atau query log untuk deteksi N+1.
4. **AUTO-SAVE**: Buffer jawaban di client-side, kirim batch setiap 30 detik. Simpan ke Redis dulu, persist ke MySQL via Queue job setiap 60 detik.
5. **QUERY LIMIT**: Untuk DataTable / listing, selalu paginate. Max 50 items per page.
6. **FILE UPLOADS**: Simpan di `storage/app/materials/`. Jangan simpan di database sebagai blob.
7. **QUEUE**: Semua operasi berat (grading batch, export, import) HARUS via queue. Jangan blocking request.
8. **CONNECTION POOLING**: Set MySQL `max_connections` sesuai jumlah PHP-FPM worker. Jangan biarkan default.

## Environment Variables Yang Perlu Di-set

```env
# App
APP_NAME="SMK LMS"
APP_URL=http://localhost:8000

# Session & Cache — HARUS Redis
SESSION_DRIVER=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis

# Broadcasting — Laravel Reverb
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=smk-lms
REVERB_APP_KEY=<generate>
REVERB_APP_SECRET=<generate>

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=smk_lms
DB_USERNAME=<set>
DB_PASSWORD=<set>

# Redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

## Git Workflow

- **main**: Production-ready code only
- **develop**: Integration branch
- **feature/phase-X-nama-fitur**: Feature branches per task
- Commit message format: `[Phase X] type: description` (contoh: `[Phase 1] feat: implement exam timer component`)
- Types: `feat`, `fix`, `refactor`, `test`, `docs`, `chore`
