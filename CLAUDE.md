# CLAUDE.md — SMK LMS + CBT System

Sistem LMS dan CBT untuk SMK. Target: 500+ siswa ujian bersamaan pada server Intel Xeon 20 core, 16GB RAM, HDD.
Initialized via `laravel new smk-lms --using vue` (official Laravel Vue starter kit).

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
| Testing | Pest PHP, Vitest | Latest |
| Rich Text Editor | Tiptap | Latest |

## Architecture Decisions (ringkas)

- **Inertia.js** (bukan Livewire/SPA): single monolith, CBT state di browser, auto-save periodik = hemat I/O di HDD.
- **Redis wajib**: HDD latency 50-100x SSD. Redis untuk session, cache, queue, dan exam answer buffer.
- **shadcn-vue**: sudah built-in di starter kit, copy-paste model, native Tailwind.
- **TypeScript**: type safety kritis untuk CBT interface.

## Project Structure

```
app/
├── Enums/          # PHP Enums (UserRole, QuestionType, ExamStatus)
├── Http/Controllers/{Admin,Guru,Siswa}/
├── Http/Requests/  # Form Request validation
├── Models/         # Eloquent models
├── Services/       # Business logic
├── Jobs/           # Queue jobs
├── Policies/       # Authorization
├── Events/         # Broadcasting events
resources/js/
├── components/ui/  # shadcn-vue base components
├── Components/     # Custom (Exam/, LMS/, DataTable/)
├── Pages/{Admin,Guru,Siswa}/
├── composables/    # useExamTimer, useAutoSave, dll
├── types/          # TypeScript interfaces
routes/web.php      # All Inertia routes
```

## Common Commands

```bash
php artisan serve                       # Laravel dev server
npm run dev                             # Vite dev server
php artisan queue:work redis            # Queue worker
php artisan reverb:start                # WebSocket server
php artisan migrate:fresh --seed        # Reset DB
php artisan test                        # Backend tests
npx vitest                              # Frontend tests
php artisan make:model Name -mfsc       # Model + migration + seeder + controller
npx shadcn-vue@latest add <component>   # Add shadcn component
```

## Git Workflow

- Commit format: `[Phase X] type: description` — types: `feat`, `fix`, `refactor`, `test`, `docs`, `chore`
- Branches: `main` (production), `develop` (integration), `feature/phase-X-nama-fitur`

## Rules (progressive disclosure)

Detail conventions dan technical design ada di `.claude/rules/`:
- **`.claude/rules/conventions.md`** — coding conventions PHP + Vue + TS + naming + language rules
- **`.claude/rules/performance.md`** — HDD optimization, Redis rules, indexing, auto-save buffer
- **`.claude/rules/exam-engine.md`** — auto-save flow, timer sync, Redis key structure, question loading
- **`.claude/rules/efficient-reading.md`** — file reading efficiency rules

Detailed specs: `PRD.md` (requirements), `plan.md` (implementation plan).
