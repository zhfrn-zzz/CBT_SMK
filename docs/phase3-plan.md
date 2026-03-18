# Phase 3 — LMS Core: Detailed Implementation Plan

> **Goal**: Fitur pembelajaran di luar ujian — materi, tugas, forum, pengumuman, presensi.
> **Prerequisite**: Phase 1 & 2 sudah complete (CBT, Proctor, Remedial).
> **Estimasi**: Minggu 11-14

---

## Table of Contents

1. [Task 3.1: Materi Pembelajaran](#task-31-materi-pembelajaran)
2. [Task 3.2: Tugas & Assignment](#task-32-tugas--assignment)
3. [Task 3.3: Forum Diskusi & Pengumuman](#task-33-forum-diskusi--pengumuman)
4. [Task 3.4: Presensi / Kehadiran](#task-34-presensi--kehadiran)
5. [Task 3.5: Sidebar Navigation Update](#task-35-sidebar-navigation-update)
6. [Task 3.6: Dashboard Update (Phase 3)](#task-36-dashboard-update-phase-3)
7. [Database Indexing (Phase 3)](#database-indexing-phase-3)

---

## Task 3.1: Materi Pembelajaran

### Hubungan dengan Fitur Existing

- Materi terikat ke **subject** + **classroom** → guru hanya bisa membuat materi untuk mapel+kelas yang sudah di-assign ke mereka (tabel `classroom_subject_teacher`).
- Filter berdasarkan **tahun ajaran aktif** (`academic_years.is_active = true`).
- Siswa hanya melihat materi dari kelas yang mereka terdaftar di dalamnya (tabel `classroom_student`).
- Reuse pattern yang sudah ada: `TeachingAssignment` model untuk authorization, `PaginatedData<T>` type untuk listing.

---

### T3.1.1 — Migration: `materials`, `material_progress`

**File**: `database/migrations/2026_XX_XX_010000_create_materials_table.php`
**File**: `database/migrations/2026_XX_XX_010001_create_material_progress_table.php`

**Schema `materials`**:
```sql
materials
├── id (bigint, PK)
├── subject_id (FK → subjects)
├── classroom_id (FK → classrooms)
├── user_id (FK → users) -- guru pembuat
├── title (varchar 255)
├── description (text, nullable)
├── type (enum: 'file', 'video_link', 'text')
├── file_path (varchar 500, nullable)      -- untuk type = file
├── file_original_name (varchar 255, nullable) -- nama asli file yang diupload
├── file_size (bigint, nullable)            -- ukuran file dalam bytes
├── video_url (varchar 500, nullable)       -- untuk type = video_link (YouTube URL)
├── text_content (text, nullable)           -- untuk type = text (rich text via Tiptap)
├── topic (varchar 255, nullable)           -- bab/topik pengelompokan
├── order (int, default 0)                  -- urutan dalam topik
├── is_published (boolean, default true)    -- draft / published
├── timestamps
├── INDEX(subject_id, classroom_id)
├── INDEX(user_id)
├── INDEX(topic)
```

**Schema `material_progress`**:
```sql
material_progress
├── id (bigint, PK)
├── material_id (FK → materials)
├── user_id (FK → users) -- siswa
├── is_completed (boolean, default false)
├── completed_at (datetime, nullable)
├── timestamps
├── UNIQUE(material_id, user_id)
```

**Acceptance Criteria**:
- [ ] Migration up dan down berjalan tanpa error
- [ ] Semua foreign key constraints benar
- [ ] Composite index pada `(subject_id, classroom_id)` untuk query listing materi

---

### T3.1.2 — Models + Relationships

**File**: `app/Models/Material.php`
- `belongsTo(Subject)`, `belongsTo(Classroom)`, `belongsTo(User)` (guru)
- `hasMany(MaterialProgress)`
- `progress()` → `hasMany(MaterialProgress)` (untuk eager load)
- Scope: `scopeForClassroom($classroomId)`, `scopePublished()`
- Enum cast: `type` → `MaterialType` enum
- Accessor: `formatted_file_size` (human readable: "2.5 MB")

**File**: `app/Models/MaterialProgress.php`
- `belongsTo(Material)`, `belongsTo(User)`

**File**: `app/Enums/MaterialType.php`
- Values: `File`, `VideoLink`, `Text`
- Label: `'File Upload'`, `'Link Video YouTube'`, `'Teks / Artikel'`

**Acceptance Criteria**:
- [ ] Relasi `Material::progress` eager loadable tanpa N+1
- [ ] `Material::forClassroom()` scope filter by classroom_id
- [ ] Enum memiliki method `label()` untuk display Bahasa Indonesia

---

### T3.1.3 — TypeScript Types

**File**: `resources/js/types/lms.ts`

```typescript
// Material Types
export type MaterialType = 'file' | 'video_link' | 'text';

export interface Material {
  id: number;
  subject_id: number;
  classroom_id: number;
  user_id: number;
  title: string;
  description: string | null;
  type: MaterialType;
  file_path: string | null;
  file_original_name: string | null;
  file_size: number | null;
  video_url: string | null;
  text_content: string | null;
  topic: string | null;
  order: number;
  is_published: boolean;
  created_at: string;
  updated_at: string;
  // Relations
  subject?: Subject;
  classroom?: Classroom;
  user?: User;
  progress?: MaterialProgress[];
  // Computed
  formatted_file_size?: string;
  completion_count?: number;
  total_students?: number;
}

export interface MaterialProgress {
  id: number;
  material_id: number;
  user_id: number;
  is_completed: boolean;
  completed_at: string | null;
}

export interface MaterialForm {
  title: string;
  description: string | null;
  subject_id: number | null;
  classroom_id: number | null;
  type: MaterialType;
  file: File | null;
  video_url: string | null;
  text_content: string | null;
  topic: string | null;
  order: number;
  is_published: boolean;
}
```

---

### T3.1.4 — Guru: CRUD Materi

#### Service Layer

**File**: `app/Services/LMS/MaterialService.php`

Methods:
- `getForClassroom(int $classroomId, int $subjectId, ?string $topic): LengthAwarePaginator` — paginated, grouped by topic
- `create(array $data, UploadedFile $file = null): Material`
- `update(Material $material, array $data, UploadedFile $file = null): Material`
- `delete(Material $material): void` — also delete file from disk
- `reorder(array $orderedIds): void` — batch update `order` field
- `getProgressOverview(int $classroomId, int $subjectId): Collection` — per-student completion stats
- `getTopics(int $classroomId, int $subjectId): array` — distinct topic list untuk grouping

#### File Upload Handling

| Aspect | Specification |
|--------|--------------|
| **Storage path** | `storage/app/materials/{subject_id}/{classroom_id}/{filename}` |
| **Allowed types** | PDF, DOCX, PPTX, DOC, PPT, XLS, XLSX, JPG, JPEG, PNG, GIF |
| **Max file size** | 50 MB |
| **Filename** | `{timestamp}_{slugified_original_name}.{ext}` (hindari collision) |
| **Validation** | Laravel Form Request: `'file' => 'required_if:type,file|file|mimes:pdf,docx,pptx,doc,ppt,xls,xlsx,jpg,jpeg,png,gif|max:51200'` |
| **Serve file** | Route khusus dengan `Storage::download()` — jangan expose storage path ke public |
| **Delete** | Hapus file dari disk saat material dihapus atau file diganti |

#### YouTube Embed Parsing

| Aspect | Specification |
|--------|--------------|
| **Accepted formats** | `https://www.youtube.com/watch?v=VIDEO_ID`, `https://youtu.be/VIDEO_ID`, `https://www.youtube.com/embed/VIDEO_ID` |
| **Validation regex** | `/^(https?:\/\/)?(www\.)?(youtube\.com\/(watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/` |
| **Extract video ID** | Dari URL, extract video ID (11 char) |
| **Embed URL** | Simpan URL original di `video_url`, render di frontend sebagai `<iframe src="https://www.youtube.com/embed/{videoId}" ...>` |
| **Frontend** | Composable `useYouTubeEmbed(url: string)` → return `{ videoId, embedUrl, thumbnailUrl }` |

#### Form Request

**File**: `app/Http/Requests/Guru/StoreMaterialRequest.php`
**File**: `app/Http/Requests/Guru/UpdateMaterialRequest.php`

Validation rules:
```php
'title' => 'required|string|max:255',
'description' => 'nullable|string',
'subject_id' => 'required|exists:subjects,id',
'classroom_id' => 'required|exists:classrooms,id',
'type' => 'required|in:file,video_link,text',
'file' => 'required_if:type,file|file|mimes:pdf,docx,pptx,doc,ppt,xls,xlsx,jpg,jpeg,png,gif|max:51200',
'video_url' => 'required_if:type,video_link|nullable|url|regex:/youtube\.com|youtu\.be/',
'text_content' => 'required_if:type,text|nullable|string',
'topic' => 'nullable|string|max:255',
'order' => 'integer|min:0',
'is_published' => 'boolean',
```

Tambahan: validasi bahwa guru memang ter-assign ke `subject_id` + `classroom_id` ini (cek `classroom_subject_teacher`).

#### Policy

**File**: `app/Policies/MaterialPolicy.php`

- `viewAny(User $user)` → guru/siswa (siswa filtered by classroom)
- `view(User $user, Material $material)` → guru pemilik ATAU siswa yang terdaftar di classroom
- `create(User $user)` → guru only
- `update(User $user, Material $material)` → guru pemilik only
- `delete(User $user, Material $material)` → guru pemilik only

#### Controller

**File**: `app/Http/Controllers/Guru/MaterialController.php`

| Method | Route | Description |
|--------|-------|-------------|
| `index()` | `GET /guru/materi` | List materi grouped by mapel+kelas. Filter: subject, classroom, topic. |
| `create()` | `GET /guru/materi/create` | Form buat materi. Dropdown mapel+kelas dari teaching assignments. |
| `store()` | `POST /guru/materi` | Simpan materi + upload file jika ada. |
| `show()` | `GET /guru/materi/{material}` | Detail materi + progress overview. |
| `edit()` | `GET /guru/materi/{material}/edit` | Form edit materi. |
| `update()` | `PUT /guru/materi/{material}` | Update materi + replace file jika ada. |
| `destroy()` | `DELETE /guru/materi/{material}` | Hapus materi + file dari disk. |
| `download()` | `GET /guru/materi/{material}/download` | Download file materi (stream). |
| `progress()` | `GET /guru/materi/{material}/progress` | Detail progress per siswa untuk materi ini. |
| `reorder()` | `POST /guru/materi/reorder` | Reorder materi via drag-and-drop. |

#### UI/UX Flow — Guru

**Halaman: Daftar Materi** (`Pages/Guru/Materi/Index.vue`)
- Filter bar: dropdown Mata Pelajaran, dropdown Kelas (auto-populated dari teaching assignments)
- Materi ditampilkan grouped by `topic` (collapsible sections, semacam accordion)
- Setiap card materi menampilkan: title, type icon (📄/🎬/📝), topic, tanggal upload, progress bar (X/Y siswa selesai)
- Tombol: + Tambah Materi, Edit, Hapus (confirm dialog), Reorder (drag handle)
- Jika tidak ada materi: empty state "Belum ada materi. Tambahkan materi pertama."

**Halaman: Buat Materi** (`Pages/Guru/Materi/Create.vue`)
- Field: Mata Pelajaran (select), Kelas (select, filtered by mapel terpilih), Judul (input), Deskripsi (textarea), Topik/Bab (input, autocomplete dari topik existing), Urutan (number)
- Radio: Tipe Materi → File Upload | Link Video YouTube | Teks/Artikel
  - File Upload: dropzone area, tampilkan nama file + size setelah upload
  - Link Video YouTube: input URL + live preview embed di bawahnya
  - Teks/Artikel: Tiptap editor (sama seperti editor soal)
- Checkbox: "Publish langsung" (default: checked)
- Tombol: Simpan, Batal

**Halaman: Detail Materi** (`Pages/Guru/Materi/Show.vue`)
- Tampilkan konten materi (preview file/embed video/teks)
- Tab: "Konten" | "Progress Siswa"
- Tab Progress: tabel siswa (nama, NIS, status: Sudah dibaca ✓ / Belum dibaca ✗, tanggal selesai)
- Summary stats: "15 dari 30 siswa sudah menyelesaikan (50%)"

**Halaman: Edit Materi** (`Pages/Guru/Materi/Edit.vue`)
- Sama seperti Create, pre-filled. Jika type=file, tampilkan file existing + opsi "Ganti file"

#### Edge Cases

- Guru upload file > 50MB → validation error "Ukuran file maksimal 50 MB"
- Guru paste URL YouTube yang invalid → validation error "URL YouTube tidak valid"
- Guru hapus materi yang sudah ada progress siswa → hard delete material + progress (confirm dialog: "Materi dan semua data progress siswa akan dihapus")
- File MIME type tidak sesuai ekstensi → reject ("Tipe file tidak diizinkan")
- Guru mencoba membuat materi untuk kelas yang bukan asuhannya → 403 Forbidden
- Topik di-rename → materi dengan topik lama tetap, topik baru muncul di grouping
- Video YouTube di-set private/dihapus oleh uploader → embed gagal render, tampilkan fallback "Video tidak tersedia"

**Acceptance Criteria**:
- [ ] Guru bisa upload materi tipe file (PDF, DOCX, PPTX, gambar)
- [ ] Guru bisa embed video YouTube dengan live preview
- [ ] Guru bisa buat materi teks dengan Tiptap rich text editor
- [ ] File tersimpan di `storage/app/materials/` dengan path terstruktur
- [ ] File > 50 MB ditolak dengan pesan error yang jelas
- [ ] URL YouTube yang invalid ditolak dengan pesan error
- [ ] Materi bisa dikelompokkan per topik/bab
- [ ] Materi bisa di-reorder (drag-and-drop)
- [ ] Guru hanya bisa mengelola materi untuk mapel+kelas yang di-assign
- [ ] Guru bisa melihat progress baca siswa per materi

---

### T3.1.5 — Siswa: Browse & View Materi

#### Controller

**File**: `app/Http/Controllers/Siswa/MaterialController.php`

| Method | Route | Description |
|--------|-------|-------------|
| `index()` | `GET /siswa/materi` | List materi per kelas+mapel yang terdaftar |
| `show()` | `GET /siswa/materi/{material}` | View detail materi |
| `download()` | `GET /siswa/materi/{material}/download` | Download file materi |
| `complete()` | `POST /siswa/materi/{material}/complete` | Tandai "sudah dibaca/ditonton" |

#### UI/UX Flow — Siswa

**Halaman: Daftar Materi** (`Pages/Siswa/Materi/Index.vue`)
- Sidebar/tab per mata pelajaran (berdasarkan kelas siswa saat ini di tahun ajaran aktif)
- Materi dikelompokkan per topik (accordion)
- Setiap item: title, type icon, tanggal, status (✓ Selesai / Belum dibaca)
- Progress bar di atas: "Anda sudah menyelesaikan 8 dari 15 materi (53%)"
- Klik item → masuk ke halaman detail

**Halaman: Detail Materi** (`Pages/Siswa/Materi/Show.vue`)
- **Tipe File**: Tampilkan info file (nama, ukuran, tipe), tombol "Download File". Jika PDF, embed PDF viewer in-page.
- **Tipe Video**: Embed iframe YouTube, responsive 16:9 aspect ratio
- **Tipe Teks**: Render rich text content (HTML dari Tiptap)
- Tombol: "Tandai Selesai" (di bagian bawah) → toggle `is_completed`, tampilkan konfirmasi
- Navigasi: Prev/Next materi (dalam topik yang sama)

#### Progress Calculation Logic

```
Per mata pelajaran per kelas:
  total_materials = COUNT(materials WHERE subject_id = X AND classroom_id = Y AND is_published = true)
  completed = COUNT(material_progress WHERE user_id = siswa AND is_completed = true AND material_id IN total_materials)
  percentage = (completed / total_materials) * 100
```

- Jika total_materials = 0 → progress = 0% (bukan division by zero)
- Progress dihitung per request (tidak di-cache) karena data relatif kecil

#### Edge Cases

- Siswa akses materi yang `is_published = false` → 404
- Siswa akses materi dari kelas lain → 403 Forbidden
- Siswa klik "Tandai Selesai" dua kali → idempotent (upsert, bukan duplicate)
- Siswa buka PDF yang corrupt → browser PDF viewer handle, tampilkan fallback "File tidak bisa ditampilkan, silakan download"
- YouTube embed blocked di sekolah → tampilkan link langsung ke YouTube sebagai fallback

**Acceptance Criteria**:
- [ ] Siswa hanya melihat materi dari kelas yang mereka terdaftar
- [ ] Materi dikelompokkan per topik dengan status progress
- [ ] Siswa bisa download file materi
- [ ] Siswa bisa menonton video YouTube embedded
- [ ] Siswa bisa membaca materi teks (rich text)
- [ ] Siswa bisa menandai materi sebagai "selesai"
- [ ] Progress bar akurat menampilkan persentase materi yang sudah diselesaikan
- [ ] Navigasi prev/next materi berfungsi

---

### T3.1.6 — Guru: Progress Overview per Kelas

#### UI/UX Flow

**Halaman: Progress Kelas** (bagian dari `Pages/Guru/Materi/Index.vue` atau tab terpisah)
- Pilih mapel + kelas
- Tabel TanStack Table: Nama Siswa | NIS | Materi Selesai | Persentase | Detail
- Sortable by persentase (ascending/descending)
- Klik "Detail" → modal/page menampilkan checklist materi mana yang sudah/belum dibaca siswa ini
- Summary: "Rata-rata completion: 67%. 5 siswa belum membuka materi apapun."

**Acceptance Criteria**:
- [ ] Guru bisa melihat overview progress per kelas
- [ ] Tabel sortable dan searchable (by nama/NIS)
- [ ] Detail per siswa menampilkan materi mana yang sudah/belum dibaca
- [ ] Summary statistics akurat

---

## Task 3.2: Tugas & Assignment

### Hubungan dengan Fitur Existing

- Tugas terikat ke **subject** + **classroom** (sama seperti materi).
- Authorization sama: guru hanya bisa membuat tugas untuk mapel+kelas yang di-assign.
- Siswa yang bisa mengerjakan: yang terdaftar di `classroom_student` untuk classroom tersebut.
- Pattern grading mirip dengan manual grading esai di CBT (reuse style, beda controller).

---

### T3.2.1 — Migration: `assignments`, `assignment_submissions`

**File**: `database/migrations/2026_XX_XX_020000_create_assignments_table.php`
**File**: `database/migrations/2026_XX_XX_020001_create_assignment_submissions_table.php`

**Schema `assignments`**:
```sql
assignments
├── id (bigint, PK)
├── subject_id (FK → subjects)
├── classroom_id (FK → classrooms)
├── user_id (FK → users) -- guru pembuat
├── title (varchar 255)
├── description (text) -- rich text (Tiptap)
├── file_path (varchar 500, nullable) -- lampiran dari guru
├── file_original_name (varchar 255, nullable)
├── deadline_at (datetime)
├── max_score (decimal 8,2, default 100)
├── allow_late_submission (boolean, default false)
├── late_penalty_percent (int, default 0) -- potongan nilai jika telat (0 = tanpa potongan)
├── submission_type (enum: 'file', 'text', 'file_or_text') -- apa yang siswa submit
├── is_published (boolean, default true)
├── timestamps
├── INDEX(subject_id, classroom_id)
├── INDEX(user_id)
├── INDEX(deadline_at)
```

**Schema `assignment_submissions`**:
```sql
assignment_submissions
├── id (bigint, PK)
├── assignment_id (FK → assignments)
├── user_id (FK → users) -- siswa
├── content (text, nullable) -- jawaban teks
├── file_path (varchar 500, nullable) -- file yang diupload siswa
├── file_original_name (varchar 255, nullable)
├── submitted_at (datetime)
├── is_late (boolean, default false)
├── score (decimal 8,2, nullable) -- nilai dari guru
├── feedback (text, nullable) -- komentar guru
├── graded_at (datetime, nullable)
├── graded_by (FK → users, nullable) -- guru yang menilai
├── timestamps
├── UNIQUE(assignment_id, user_id) -- 1 siswa 1 submission per tugas
```

**Acceptance Criteria**:
- [ ] Migration up dan down tanpa error
- [ ] `UNIQUE(assignment_id, user_id)` mencegah double submission
- [ ] Index pada `deadline_at` untuk query upcoming/overdue

---

### T3.2.2 — Models, Enum, Types

**File**: `app/Models/Assignment.php`
- `belongsTo(Subject)`, `belongsTo(Classroom)`, `belongsTo(User)`
- `hasMany(AssignmentSubmission)`
- Scope: `scopeUpcoming()` (deadline > now), `scopeOverdue()` (deadline < now)
- Accessor: `is_overdue` (boolean), `formatted_deadline`

**File**: `app/Models/AssignmentSubmission.php`
- `belongsTo(Assignment)`, `belongsTo(User)` (siswa), `belongsTo(User, 'graded_by')` (guru)
- Accessor: `status` → computed dari state: 'not_submitted' | 'submitted' | 'late' | 'graded'

**File**: `app/Enums/SubmissionType.php`
- Values: `File`, `Text`, `FileOrText`
- Label: `'Upload File'`, `'Teks'`, `'File atau Teks'`

**File**: `app/Enums/SubmissionStatus.php` (virtual, untuk display)
- Values: `NotSubmitted`, `Submitted`, `Late`, `Graded`
- Label Bahasa Indonesia + warna badge

**TypeScript** (tambah di `resources/js/types/lms.ts`):
```typescript
export type SubmissionType = 'file' | 'text' | 'file_or_text';
export type SubmissionStatus = 'not_submitted' | 'submitted' | 'late' | 'graded';

export interface Assignment {
  id: number;
  subject_id: number;
  classroom_id: number;
  user_id: number;
  title: string;
  description: string;
  file_path: string | null;
  file_original_name: string | null;
  deadline_at: string;
  max_score: number;
  allow_late_submission: boolean;
  late_penalty_percent: number;
  submission_type: SubmissionType;
  is_published: boolean;
  created_at: string;
  updated_at: string;
  // Relations
  subject?: Subject;
  classroom?: Classroom;
  user?: User;
  submissions?: AssignmentSubmission[];
  // Computed
  is_overdue?: boolean;
  submission_count?: number;
  graded_count?: number;
  total_students?: number;
}

export interface AssignmentSubmission {
  id: number;
  assignment_id: number;
  user_id: number;
  content: string | null;
  file_path: string | null;
  file_original_name: string | null;
  submitted_at: string;
  is_late: boolean;
  score: number | null;
  feedback: string | null;
  graded_at: string | null;
  graded_by: number | null;
  status: SubmissionStatus;
  // Relations
  user?: User;
  assignment?: Assignment;
}
```

---

### T3.2.3 — Guru: CRUD Tugas

#### Service Layer

**File**: `app/Services/LMS/AssignmentService.php`

Methods:
- `getForTeacher(User $teacher, ?int $subjectId, ?int $classroomId): LengthAwarePaginator`
- `create(array $data, ?UploadedFile $file): Assignment`
- `update(Assignment $assignment, array $data, ?UploadedFile $file): Assignment`
- `delete(Assignment $assignment): void` — juga hapus semua submissions + file
- `getSubmissions(Assignment $assignment): Collection` — with user, sorted
- `gradeSubmission(AssignmentSubmission $submission, float $score, ?string $feedback, User $grader): void`
- `getSubmissionStats(Assignment $assignment): array` — submitted/unsubmitted/graded counts

#### File Upload Handling (Guru attachment)

| Aspect | Specification |
|--------|--------------|
| **Storage path** | `storage/app/assignments/attachments/{assignment_id}/{filename}` |
| **Allowed types** | PDF, DOCX, PPTX, DOC, PPT, XLS, XLSX, JPG, JPEG, PNG, GIF, ZIP, RAR |
| **Max file size** | 50 MB |
| **Serve** | Via route + `Storage::download()` (authorized) |

#### Form Request

**File**: `app/Http/Requests/Guru/StoreAssignmentRequest.php`

```php
'title' => 'required|string|max:255',
'description' => 'required|string',
'subject_id' => 'required|exists:subjects,id',
'classroom_id' => 'required|exists:classrooms,id',
'file' => 'nullable|file|mimes:pdf,docx,pptx,doc,ppt,xls,xlsx,jpg,jpeg,png,gif,zip,rar|max:51200',
'deadline_at' => 'required|date|after:now',
'max_score' => 'required|numeric|min:1|max:100',
'allow_late_submission' => 'boolean',
'late_penalty_percent' => 'required_if:allow_late_submission,true|integer|min:0|max:100',
'submission_type' => 'required|in:file,text,file_or_text',
'is_published' => 'boolean',
```

Tambahan: validasi guru ter-assign ke subject_id + classroom_id.

#### Policy

**File**: `app/Policies/AssignmentPolicy.php`
- `viewAny` → guru/siswa
- `view` → guru pemilik ATAU siswa di classroom
- `create` → guru only
- `update/delete` → guru pemilik only
- `grade` → guru pemilik only

#### Controller

**File**: `app/Http/Controllers/Guru/AssignmentController.php`

| Method | Route | Description |
|--------|-------|-------------|
| `index()` | `GET /guru/tugas` | List tugas, filter by mapel+kelas |
| `create()` | `GET /guru/tugas/create` | Form buat tugas |
| `store()` | `POST /guru/tugas` | Simpan tugas |
| `show()` | `GET /guru/tugas/{assignment}` | Detail tugas + list submission siswa |
| `edit()` | `GET /guru/tugas/{assignment}/edit` | Form edit tugas |
| `update()` | `PUT /guru/tugas/{assignment}` | Update tugas |
| `destroy()` | `DELETE /guru/tugas/{assignment}` | Hapus tugas + semua submission |
| `download()` | `GET /guru/tugas/{assignment}/download` | Download lampiran guru |
| `submissions()` | `GET /guru/tugas/{assignment}/submissions` | Detail semua submission (grading view) |
| `grade()` | `PUT /guru/tugas/submissions/{submission}/grade` | Simpan nilai + feedback |
| `downloadSubmission()` | `GET /guru/tugas/submissions/{submission}/download` | Download file submission siswa |

#### UI/UX Flow — Guru

**Halaman: Daftar Tugas** (`Pages/Guru/Tugas/Index.vue`)
- Filter: Mata Pelajaran, Kelas
- TanStack Table: Judul | Kelas | Mapel | Deadline | Sudah Submit (X/Y) | Sudah Dinilai (X/Y) | Aksi
- Badge warna deadline: hijau (> 3 hari), kuning (1-3 hari), merah (lewat deadline)
- Aksi: Lihat Submission, Edit, Hapus

**Halaman: Buat Tugas** (`Pages/Guru/Tugas/Create.vue`)
- Field: Mata Pelajaran (select), Kelas (select), Judul (input), Deskripsi (Tiptap editor)
- Lampiran File (opsional dropzone)
- Deadline (date-time picker)
- Nilai Maksimal (number, default 100)
- Tipe Submission: Radio (File Upload | Teks | File atau Teks)
- Toggle: Izinkan Terlambat → if checked, field "Potongan Nilai (%)" muncul
- Tombol: Simpan, Batal

**Halaman: Detail Tugas + Grading** (`Pages/Guru/Tugas/Show.vue`)
- Info tugas: judul, deskripsi, lampiran, deadline, konfigurasi
- Tab: "Submission Siswa"
- Tabel: Nama | NIS | Status (badge: Belum Submit / Sudah Submit / Terlambat / Sudah Dinilai) | Waktu Submit | Nilai | Aksi
- Klik aksi "Nilai" → inline form atau modal: tampilkan jawaban siswa (teks/file download), input Nilai (0 - max_score), Feedback (textarea)
- Tombol "Simpan Nilai" → POST grade
- Progress: "12 dari 30 siswa sudah dinilai"

**Halaman: Edit Tugas** (`Pages/Guru/Tugas/Edit.vue`)
- Sama seperti Create, pre-filled
- Warning jika sudah ada submission: "Tugas ini sudah memiliki X submission. Perubahan deadline akan berlaku untuk semua siswa."

#### Late Submission Logic

```
Saat siswa submit:
1. Cek deadline: submitted_at vs assignment.deadline_at
2. Jika submitted_at > deadline_at:
   a. Jika allow_late_submission = false → tolak (400: "Deadline sudah lewat")
   b. Jika allow_late_submission = true → terima, set is_late = true
3. Saat guru menilai submission yang is_late = true:
   - Nilai final = score - (score × late_penalty_percent / 100)
   - Tampilkan di UI: "Nilai: 85 (potongan 10% karena terlambat → final: 76.5)"
   - Yang disimpan di DB: score = nilai sebelum potongan (supaya guru bisa adjust penalty nanti)
   - Potongan dihitung saat display/export
```

#### Edge Cases

- Siswa submit setelah deadline + late submission tidak diizinkan → 422 "Deadline tugas sudah lewat"
- Siswa submit 2x → UNIQUE constraint → update submission yang sudah ada (re-submit)
- Guru edit deadline menjadi lebih awal dari submission yang sudah masuk → submission yang sudah masuk tetap valid, yang belum submit ikut deadline baru
- Guru hapus tugas yang sudah ada submission → confirm dialog "X submission akan ikut terhapus"
- Siswa mencoba submit teks padahal submission_type = file → validation error
- File submission siswa corrupt / 0 byte → tolak "File tidak valid"
- Guru menilai dengan score > max_score → validation error

**Acceptance Criteria**:
- [ ] Guru bisa membuat tugas dengan deadline, lampiran, dan konfigurasi late submission
- [ ] Guru bisa melihat daftar submission per tugas
- [ ] Guru bisa menilai submission (nilai angka + feedback teks)
- [ ] Late submission di-handle sesuai konfigurasi (izinkan/tolak)
- [ ] Late penalty dihitung otomatis saat display
- [ ] Siswa hanya bisa submit 1 kali per tugas (bisa re-submit/update sebelum dinilai)
- [ ] Progress grading terlihat (X dari Y sudah dinilai)

---

### T3.2.4 — Siswa: View Tugas + Submit

#### Controller

**File**: `app/Http/Controllers/Siswa/AssignmentController.php`

| Method | Route | Description |
|--------|-------|-------------|
| `index()` | `GET /siswa/tugas` | List tugas dari semua kelas+mapel siswa |
| `show()` | `GET /siswa/tugas/{assignment}` | Detail tugas + form submission |
| `submit()` | `POST /siswa/tugas/{assignment}/submit` | Submit jawaban (file atau teks) |
| `download()` | `GET /siswa/tugas/{assignment}/download` | Download lampiran guru |

#### File Upload Handling (Siswa submission)

| Aspect | Specification |
|--------|--------------|
| **Storage path** | `storage/app/assignments/submissions/{assignment_id}/{user_id}/{filename}` |
| **Allowed types** | PDF, DOCX, PPTX, DOC, PPT, XLS, XLSX, JPG, JPEG, PNG, ZIP, RAR |
| **Max file size** | 25 MB |
| **Re-submit** | Old file dihapus, diganti file baru (sebelum dinilai) |

#### Form Request

**File**: `app/Http/Requests/Siswa/SubmitAssignmentRequest.php`

```php
'content' => 'required_if:submission_type,text|nullable|string',
'file' => 'required_if:submission_type,file|nullable|file|mimes:pdf,docx,pptx,doc,ppt,xls,xlsx,jpg,jpeg,png,zip,rar|max:25600',
```

Validasi tambahan:
- Tugas `is_published = true`
- Siswa terdaftar di classroom tugas
- Jika sudah dinilai (`graded_at` not null) → tidak bisa re-submit ("Tugas sudah dinilai, tidak bisa diubah")

#### UI/UX Flow — Siswa

**Halaman: Daftar Tugas** (`Pages/Siswa/Tugas/Index.vue`)
- Tab/filter per mata pelajaran
- List card: Judul Tugas | Mapel | Deadline | Status badge | Nilai (jika sudah dinilai)
- Status badge: 🔴 Belum Submit | 🟡 Sudah Submit | 🟠 Terlambat | 🟢 Sudah Dinilai
- Sortable by deadline (default: upcoming first)
- Overdue tugas yang belum di-submit tampil dengan highlight merah

**Halaman: Detail Tugas + Submit** (`Pages/Siswa/Tugas/Show.vue`)
- Info tugas: judul, deskripsi (rich text), lampiran (download button), deadline countdown
- **Jika belum submit**:
  - Form submission: textarea (jika text/file_or_text) dan/atau file upload (jika file/file_or_text)
  - Tombol "Kumpulkan Tugas" → confirm dialog
  - Countdown: "Sisa waktu: 2 hari 5 jam" atau "Deadline sudah lewat" (merah)
  - Jika deadline lewat + late tidak diizinkan: form disabled, pesan "Deadline sudah lewat"
  - Jika deadline lewat + late diizinkan: form aktif, warning "Tugas akan ditandai terlambat (potongan X%)"
- **Jika sudah submit, belum dinilai**:
  - Tampilkan submission: teks jawaban / file yang diupload
  - Tombol "Edit Jawaban" (re-submit) → replace old submission
  - Timestamp: "Dikumpulkan pada: 15 Mar 2026, 14:30"
  - Badge "Terlambat" jika is_late
- **Jika sudah dinilai**:
  - Tampilkan submission + nilai + feedback guru
  - Tidak bisa edit
  - Jika late: "Nilai: 85 (potongan 10% → 76.5)"

**Acceptance Criteria**:
- [ ] Siswa bisa melihat daftar tugas dari semua kelas yang terdaftar
- [ ] Siswa bisa submit tugas berupa teks dan/atau file upload
- [ ] Siswa bisa re-submit (edit jawaban) selama belum dinilai
- [ ] Late submission ditolak jika tidak diizinkan
- [ ] Late submission diterima dengan peringatan jika diizinkan
- [ ] Siswa bisa melihat nilai dan feedback setelah dinilai
- [ ] Deadline countdown ditampilkan dengan benar

---

## Task 3.3: Forum Diskusi & Pengumuman

### Hubungan dengan Fitur Existing

- Forum dan pengumuman terikat ke **subject** + **classroom** (forum) atau **classroom** saja (pengumuman).
- Akses: semua user yang terhubung dengan classroom tersebut (guru pengampu + siswa terdaftar).
- Pengumuman bisa juga broadcast (classroom_id = null → tampil di semua dashboard).

---

### T3.3.1 — Migration: `discussion_threads`, `discussion_replies`, `announcements`

**File**: `database/migrations/2026_XX_XX_030000_create_discussion_threads_table.php`
**File**: `database/migrations/2026_XX_XX_030001_create_discussion_replies_table.php`
**File**: `database/migrations/2026_XX_XX_030002_create_announcements_table.php`

**Schema `discussion_threads`**:
```sql
discussion_threads
├── id (bigint, PK)
├── subject_id (FK → subjects)
├── classroom_id (FK → classrooms)
├── user_id (FK → users) -- pembuat thread (guru atau siswa)
├── title (varchar 255)
├── content (text) -- rich text body
├── is_pinned (boolean, default false)
├── is_locked (boolean, default false) -- guru bisa lock thread, no more replies
├── last_reply_at (datetime, nullable) -- untuk sorting by activity
├── reply_count (int, default 0) -- denormalized counter untuk performa
├── timestamps
├── INDEX(subject_id, classroom_id)
├── INDEX(user_id)
├── INDEX(is_pinned, last_reply_at) -- untuk sorting: pinned first, then by activity
```

**Schema `discussion_replies`**:
```sql
discussion_replies
├── id (bigint, PK)
├── discussion_thread_id (FK → discussion_threads)
├── user_id (FK → users) -- pembuat reply
├── content (text)
├── timestamps
├── INDEX(discussion_thread_id, created_at)
```

**Schema `announcements`**:
```sql
announcements
├── id (bigint, PK)
├── user_id (FK → users) -- guru atau admin pembuat
├── classroom_id (FK → classrooms, nullable) -- null = broadcast ke semua
├── subject_id (FK → subjects, nullable) -- null jika broadcast atau pengumuman kelas umum
├── title (varchar 255)
├── content (text) -- rich text
├── is_pinned (boolean, default false)
├── published_at (datetime, default now) -- schedule pengumuman
├── timestamps
├── INDEX(classroom_id)
├── INDEX(published_at)
```

**Acceptance Criteria**:
- [ ] Migrations up dan down tanpa error
- [ ] `is_locked` mencegah reply baru lewat validation (bukan DB constraint)
- [ ] `reply_count` ter-denormalize untuk menghindari COUNT query saat listing

---

### T3.3.2 — Models + Types

**Models**:

**File**: `app/Models/DiscussionThread.php`
- `belongsTo(Subject)`, `belongsTo(Classroom)`, `belongsTo(User)`
- `hasMany(DiscussionReply)`
- `latestReply()` → `hasOne(DiscussionReply)->latestOfMany()`
- Scope: `scopePinnedFirst()` → `orderByDesc('is_pinned')->orderByDesc('last_reply_at')`

**File**: `app/Models/DiscussionReply.php`
- `belongsTo(DiscussionThread)`, `belongsTo(User)`
- Boot event: saat created → update parent thread `last_reply_at` dan increment `reply_count`
- Boot event: saat deleted → decrement parent `reply_count`

**File**: `app/Models/Announcement.php`
- `belongsTo(User)`, `belongsTo(Classroom)` (nullable), `belongsTo(Subject)` (nullable)
- Scope: `scopePublished()` → `where('published_at', '<=', now())`
- Scope: `scopeForStudent(User $student)` → classroom_id IN student's classrooms OR classroom_id IS NULL
- Scope: `scopePinnedFirst()`

**TypeScript** (tambah di `resources/js/types/lms.ts`):
```typescript
export interface DiscussionThread {
  id: number;
  subject_id: number;
  classroom_id: number;
  user_id: number;
  title: string;
  content: string;
  is_pinned: boolean;
  is_locked: boolean;
  last_reply_at: string | null;
  reply_count: number;
  created_at: string;
  updated_at: string;
  user?: User;
  subject?: Subject;
  classroom?: Classroom;
  latest_reply?: DiscussionReply;
  replies?: DiscussionReply[];
}

export interface DiscussionReply {
  id: number;
  discussion_thread_id: number;
  user_id: number;
  content: string;
  created_at: string;
  updated_at: string;
  user?: User;
}

export interface Announcement {
  id: number;
  user_id: number;
  classroom_id: number | null;
  subject_id: number | null;
  title: string;
  content: string;
  is_pinned: boolean;
  published_at: string;
  created_at: string;
  updated_at: string;
  user?: User;
  classroom?: Classroom;
  subject?: Subject;
}
```

---

### T3.3.3 — Forum Diskusi: CRUD + Reply

#### Service Layer

**File**: `app/Services/LMS/DiscussionService.php`

Methods:
- `getThreads(int $subjectId, int $classroomId, ?string $search): LengthAwarePaginator` — pinned first, then by last_reply_at desc. Paginate 20/page.
- `createThread(array $data): DiscussionThread`
- `deleteThread(DiscussionThread $thread): void` — cascade delete replies
- `togglePin(DiscussionThread $thread): void` — guru only
- `toggleLock(DiscussionThread $thread): void` — guru only
- `createReply(DiscussionThread $thread, array $data): DiscussionReply` — validate not locked
- `deleteReply(DiscussionReply $reply): void`

#### Policy

**File**: `app/Policies/DiscussionThreadPolicy.php`
- `viewAny` → guru pengampu + siswa di classroom
- `create` → guru pengampu + siswa di classroom
- `update/delete` → pembuat thread ATAU guru pengampu kelas (guru bisa moderate)
- `pin/lock` → guru pengampu only
- `reply` → guru/siswa di classroom + thread not locked

#### Controller (Guru)

**File**: `app/Http/Controllers/Guru/DiscussionController.php`

| Method | Route | Description |
|--------|-------|-------------|
| `index()` | `GET /guru/forum` | List forum per mapel+kelas |
| `show()` | `GET /guru/forum/{thread}` | Thread detail + replies |
| `store()` | `POST /guru/forum` | Buat thread baru |
| `destroy()` | `DELETE /guru/forum/{thread}` | Hapus thread |
| `reply()` | `POST /guru/forum/{thread}/reply` | Reply di thread |
| `deleteReply()` | `DELETE /guru/forum/reply/{reply}` | Hapus reply |
| `togglePin()` | `POST /guru/forum/{thread}/toggle-pin` | Pin/unpin thread |
| `toggleLock()` | `POST /guru/forum/{thread}/toggle-lock` | Lock/unlock thread |

#### Controller (Siswa)

**File**: `app/Http/Controllers/Siswa/DiscussionController.php`

| Method | Route | Description |
|--------|-------|-------------|
| `index()` | `GET /siswa/forum` | List forum per mapel |
| `show()` | `GET /siswa/forum/{thread}` | Thread detail + replies |
| `store()` | `POST /siswa/forum` | Buat thread baru |
| `destroy()` | `DELETE /siswa/forum/{thread}` | Hapus thread sendiri |
| `reply()` | `POST /siswa/forum/{thread}/reply` | Reply di thread |
| `deleteReply()` | `DELETE /siswa/forum/reply/{reply}` | Hapus reply sendiri |

#### UI/UX Flow

**Halaman: List Forum** (`Pages/Guru/Forum/Index.vue`, `Pages/Siswa/Forum/Index.vue`)
- Filter: Mata Pelajaran (select), Kelas (select — guru only, siswa auto-detected)
- Search bar: cari by judul thread
- List threads: card layout
  - 📌 Pin indicator (jika pinned)
  - 🔒 Lock indicator (jika locked)
  - Judul | Oleh: [nama user] [badge Guru/Siswa] | X balasan | Terakhir dibalas: [waktu relatif]
- Tombol: + Buat Thread
- Pagination: 20 thread per page

**Halaman: Detail Thread** (`Pages/Guru/Forum/Show.vue`, `Pages/Siswa/Forum/Show.vue`)
- Header: Judul thread, pembuat (avatar + nama + role badge), tanggal
- Isi thread (rich text)
- Divider
- List replies: chronological
  - Setiap reply: avatar + nama + role badge + waktu relatif + konten
  - Guru punya opsi hapus reply siapapun; siswa hanya bisa hapus reply sendiri
- Reply form di bagian bawah: textarea + tombol "Balas"
- Jika thread locked: reply form disabled, pesan "Thread ini sudah dikunci"
- Guru: dropdown menu → Pin/Unpin, Lock/Unlock, Hapus Thread

**Halaman: Buat Thread** (modal atau inline form di Index)
- Field: Mata Pelajaran (select), Kelas (select), Judul (input), Isi (textarea, rich text opsional — bisa pakai Tiptap ringan atau textarea biasa)
- Tombol: Posting, Batal

#### Forum Notification Trigger

> **Note**: Full notification system ada di Phase 4. Untuk Phase 3, implementasikan "notification-ready" architecture:
> - Saat reply dibuat, dispatch event `DiscussionReplyCreated` (Laravel Event)
> - Event ini bisa di-listen oleh notification listener di Phase 4
> - Untuk saat ini: event hanya di-dispatch, belum ada listener yang mengirim notifikasi
> - Di Phase 4: tambahkan listener yang mengirim notifikasi ke pembuat thread + semua yang pernah reply

```php
// app/Events/DiscussionReplyCreated.php
class DiscussionReplyCreated
{
    public function __construct(
        public DiscussionReply $reply,
        public DiscussionThread $thread,
    ) {}
}
```

#### Edge Cases

- Siswa mencoba pin/lock thread → 403
- Reply di thread yang locked → validation error "Thread sudah dikunci"
- Siswa hapus thread yang bukan miliknya → 403
- Thread tanpa reply → tampilkan "Belum ada balasan. Jadilah yang pertama membalas."
- Thread dengan banyak reply (100+) → paginate replies, 20 per page, oldest first
- User yang membuat thread dihapus dari sistem → tampilkan "[Pengguna dihapus]" sebagai pembuat
- XSS prevention: sanitize semua content sebelum render (gunakan `v-html` dengan sanitizer atau render as plain text)

**Acceptance Criteria**:
- [ ] Guru dan siswa bisa membuat thread diskusi
- [ ] Guru dan siswa bisa membalas thread
- [ ] Guru bisa pin dan lock thread
- [ ] Thread yang di-lock tidak bisa dibalas
- [ ] Thread yang di-pin tampil di atas
- [ ] Guru bisa menghapus thread dan reply siapapun (moderasi)
- [ ] Siswa hanya bisa menghapus thread/reply miliknya sendiri
- [ ] Event `DiscussionReplyCreated` ter-dispatch saat ada reply baru
- [ ] Forum hanya bisa diakses oleh user yang terhubung ke classroom (guru pengampu + siswa terdaftar)

---

### T3.3.4 — Pengumuman

#### Service Layer

**File**: `app/Services/LMS/AnnouncementService.php`

Methods:
- `getForClassroom(?int $classroomId, ?int $subjectId): LengthAwarePaginator` — pinned first, newest first. Paginate 10/page.
- `getForStudent(User $student): LengthAwarePaginator` — pengumuman dari semua classroom siswa + broadcast
- `create(array $data): Announcement`
- `update(Announcement $announcement, array $data): Announcement`
- `delete(Announcement $announcement): void`
- `togglePin(Announcement $announcement): void`

#### Controller (Guru)

**File**: `app/Http/Controllers/Guru/AnnouncementController.php`

| Method | Route | Description |
|--------|-------|-------------|
| `index()` | `GET /guru/pengumuman` | List pengumuman yang dibuat guru |
| `create()` | `GET /guru/pengumuman/create` | Form buat pengumuman |
| `store()` | `POST /guru/pengumuman` | Simpan pengumuman |
| `edit()` | `GET /guru/pengumuman/{announcement}/edit` | Form edit |
| `update()` | `PUT /guru/pengumuman/{announcement}` | Update |
| `destroy()` | `DELETE /guru/pengumuman/{announcement}` | Hapus |
| `togglePin()` | `POST /guru/pengumuman/{announcement}/toggle-pin` | Pin/unpin |

#### Controller (Siswa — read only)

Pengumuman untuk siswa tampil di dashboard dan halaman terpisah.

**File**: `app/Http/Controllers/Siswa/AnnouncementController.php`

| Method | Route | Description |
|--------|-------|-------------|
| `index()` | `GET /siswa/pengumuman` | List semua pengumuman yang relevan |
| `show()` | `GET /siswa/pengumuman/{announcement}` | Detail pengumuman |

#### UI/UX Flow

**Guru — Daftar Pengumuman** (`Pages/Guru/Pengumuman/Index.vue`)
- TanStack Table: Judul | Target (Kelas / Semua) | Tanggal | Pinned | Aksi
- Filter: Kelas (atau "Semua kelas")
- Aksi: Edit, Hapus, Pin/Unpin

**Guru — Buat Pengumuman** (`Pages/Guru/Pengumuman/Create.vue`)
- Field: Judul (input), Isi (Tiptap editor)
- Target: Radio → "Kelas tertentu" (select kelas dari teaching assignments) | "Semua kelas saya"
  - Jika "Kelas tertentu": select Kelas, opsional select Mapel
- Jadwal Publish: date-time picker (default: sekarang)
- Toggle: Pin pengumuman
- Tombol: Publish, Batal

**Siswa — Daftar Pengumuman** (`Pages/Siswa/Pengumuman/Index.vue`)
- List card: Judul | Oleh: [guru] | Tanggal | 📌 jika pinned
- Klik → detail pengumuman
- Pinned pengumuman di atas

**Siswa — Detail Pengumuman** (`Pages/Siswa/Pengumuman/Show.vue`)
- Judul, pembuat, tanggal
- Isi (rich text)

#### Edge Cases

- Guru broadcast ke "semua kelas" → simpan beberapa record (satu per classroom) ATAU simpan satu record dengan classroom_id = null? → **Pilih classroom_id = null** untuk broadcast, lebih simpel
- Pengumuman di-schedule di masa depan → siswa belum bisa lihat sampai `published_at <= now()`
- Guru edit pengumuman yang sudah di-read siswa → edit berlaku, tidak ada tracking "sudah dibaca"
- Admin membuat pengumuman? → Admin juga bisa buat pengumuman broadcast (classroom_id = null). Tambahkan route admin untuk pengumuman juga di Phase 3.

**Acceptance Criteria**:
- [ ] Guru bisa membuat pengumuman untuk kelas tertentu atau broadcast
- [ ] Pengumuman bisa di-schedule (publish di masa depan)
- [ ] Pengumuman bisa di-pin
- [ ] Siswa hanya melihat pengumuman dari kelas mereka + broadcast
- [ ] Pengumuman ter-schedule belum tampil untuk siswa sebelum waktunya
- [ ] Admin bisa membuat pengumuman broadcast

---

## Task 3.4: Presensi / Kehadiran

### Hubungan dengan Fitur Existing

- Presensi terikat ke **classroom** + **subject** + **guru** (sama seperti materi/tugas).
- Guru hanya bisa buka presensi untuk kelas+mapel yang di-assign.
- Siswa menandai hadir berdasarkan kode akses yang diberikan guru.
- Rekap presensi bisa di-export ke Excel (gunakan `maatwebsite/excel` yang sudah terinstall dari Phase 1).

---

### T3.4.1 — Migration: `attendances`, `attendance_records`

**File**: `database/migrations/2026_XX_XX_040000_create_attendances_table.php`
**File**: `database/migrations/2026_XX_XX_040001_create_attendance_records_table.php`

**Schema `attendances`** (sesi presensi per pertemuan):
```sql
attendances
├── id (bigint, PK)
├── classroom_id (FK → classrooms)
├── subject_id (FK → subjects)
├── user_id (FK → users) -- guru
├── meeting_date (date)
├── meeting_number (int) -- pertemuan ke-N
├── access_code (varchar 6, nullable) -- kode akses 6 digit
├── code_expires_at (datetime, nullable) -- kapan kode expired
├── is_open (boolean, default false) -- apakah sesi presensi masih terbuka
├── note (text, nullable) -- catatan pertemuan
├── timestamps
├── INDEX(classroom_id, subject_id)
├── INDEX(user_id)
├── UNIQUE(classroom_id, subject_id, meeting_date) -- 1 sesi per mapel per kelas per hari
```

**Schema `attendance_records`** (record per siswa):
```sql
attendance_records
├── id (bigint, PK)
├── attendance_id (FK → attendances)
├── user_id (FK → users) -- siswa
├── status (enum: 'hadir', 'izin', 'sakit', 'alfa')
├── checked_in_at (datetime, nullable) -- waktu siswa input kode
├── note (varchar 255, nullable) -- keterangan (opsional)
├── timestamps
├── UNIQUE(attendance_id, user_id) -- 1 record per siswa per sesi
```

**Acceptance Criteria**:
- [ ] Migration up dan down tanpa error
- [ ] `UNIQUE(classroom_id, subject_id, meeting_date)` mencegah duplikasi sesi
- [ ] `UNIQUE(attendance_id, user_id)` mencegah duplikasi record

---

### T3.4.2 — Models, Enum, Types

**File**: `app/Models/Attendance.php`
- `belongsTo(Classroom)`, `belongsTo(Subject)`, `belongsTo(User)` (guru)
- `hasMany(AttendanceRecord)`
- Accessor: `is_code_expired` → `code_expires_at && now() > code_expires_at`
- Scope: `scopeForClassroom($classroomId, $subjectId)`

**File**: `app/Models/AttendanceRecord.php`
- `belongsTo(Attendance)`, `belongsTo(User)` (siswa)
- Enum cast: `status` → `AttendanceStatus`

**File**: `app/Enums/AttendanceStatus.php`
- Values: `Hadir`, `Izin`, `Sakit`, `Alfa`
- Label: `'Hadir'`, `'Izin'`, `'Sakit'`, `'Alfa'` (sudah Bahasa Indonesia)
- Color: `'green'`, `'blue'`, `'yellow'`, `'red'` (untuk badge)

**TypeScript** (tambah di `resources/js/types/lms.ts`):
```typescript
export type AttendanceStatus = 'hadir' | 'izin' | 'sakit' | 'alfa';

export interface Attendance {
  id: number;
  classroom_id: number;
  subject_id: number;
  user_id: number;
  meeting_date: string;
  meeting_number: number;
  access_code: string | null;
  code_expires_at: string | null;
  is_open: boolean;
  note: string | null;
  created_at: string;
  updated_at: string;
  // Relations
  classroom?: Classroom;
  subject?: Subject;
  user?: User;
  records?: AttendanceRecord[];
  // Computed
  is_code_expired?: boolean;
  present_count?: number;
  absent_count?: number;
  total_students?: number;
}

export interface AttendanceRecord {
  id: number;
  attendance_id: number;
  user_id: number;
  status: AttendanceStatus;
  checked_in_at: string | null;
  note: string | null;
  user?: User;
}

export interface AttendanceRecap {
  user: User;
  total_meetings: number;
  hadir: number;
  izin: number;
  sakit: number;
  alfa: number;
  percentage: number; // hadir / total * 100
}
```

---

### T3.4.3 — Service Layer

**File**: `app/Services/LMS/AttendanceService.php`

Methods:
- `getSessions(int $classroomId, int $subjectId): LengthAwarePaginator` — list sesi presensi, paginate 20
- `openSession(array $data): Attendance` — buat sesi, generate kode akses, set expiry
- `closeSession(Attendance $attendance): void` — set `is_open = false`
- `regenerateCode(Attendance $attendance): string` — generate kode baru, reset expiry
- `generateAccessCode(): string` — 6 digit numerik random
- `checkIn(Attendance $attendance, User $student, string $code): AttendanceRecord` — validate kode, create record hadir
- `setStatus(Attendance $attendance, User $student, AttendanceStatus $status, ?string $note): AttendanceRecord` — guru manual override
- `bulkSetStatus(Attendance $attendance, array $records): void` — batch update status untuk multiple siswa
- `getRecap(int $classroomId, int $subjectId, ?string $startDate, ?string $endDate): Collection<AttendanceRecap>` — rekap per siswa
- `exportRecap(int $classroomId, int $subjectId): string` — generate Excel, return path

#### Access Code Expiry Logic

```
Saat guru buka sesi presensi:
1. Generate kode akses: 6 digit numerik (e.g., "482915")
2. Set code_expires_at = now() + configurable duration (default: 30 menit)
3. Siswa input kode → validate:
   a. Kode cocok? Jika tidak → error "Kode salah"
   b. Kode expired (now() > code_expires_at)? → error "Kode sudah kedaluwarsa"
   c. Sesi masih open (is_open = true)? Jika tidak → error "Sesi presensi sudah ditutup"
   d. Siswa sudah punya record? → error "Anda sudah melakukan presensi"
4. Jika valid: create record status = 'hadir', checked_in_at = now()
5. Guru bisa regenerate kode (new code, new expiry) kapan saja
6. Guru bisa manual override: set status siswa mana saja ke hadir/izin/sakit/alfa
7. Guru tutup sesi → semua siswa yang belum punya record otomatis di-set 'alfa'
```

#### Policy

**File**: `app/Policies/AttendancePolicy.php`
- `viewAny` → guru (filtered by teaching assignment) + siswa (filtered by classroom)
- `create/open` → guru pengampu only
- `update/close` → guru pemilik sesi only
- `checkIn` → siswa di classroom only
- `export` → guru pengampu only

---

### T3.4.4 — Controller (Guru)

**File**: `app/Http/Controllers/Guru/AttendanceController.php`

| Method | Route | Description |
|--------|-------|-------------|
| `index()` | `GET /guru/presensi` | List sesi presensi per mapel+kelas |
| `create()` | `GET /guru/presensi/create` | Form buka sesi baru |
| `store()` | `POST /guru/presensi` | Buka sesi presensi, generate kode |
| `show()` | `GET /guru/presensi/{attendance}` | Detail sesi + list record siswa |
| `close()` | `POST /guru/presensi/{attendance}/close` | Tutup sesi, set alfa untuk yang belum |
| `regenerateCode()` | `POST /guru/presensi/{attendance}/regenerate-code` | Generate kode baru |
| `updateStatus()` | `PUT /guru/presensi/{attendance}/status` | Manual override status siswa |
| `recap()` | `GET /guru/presensi/recap` | Rekap presensi per kelas+mapel |
| `exportRecap()` | `GET /guru/presensi/recap/export` | Export Excel rekap presensi |

#### UI/UX Flow — Guru

**Halaman: List Presensi** (`Pages/Guru/Presensi/Index.vue`)
- Filter: Mata Pelajaran, Kelas
- TanStack Table: Pertemuan Ke- | Tanggal | Hadir (X/Y) | Status Sesi (Aktif ✅ / Ditutup ⬜) | Aksi
- Tombol: + Buka Sesi Presensi
- Aksi: Lihat Detail, Tutup Sesi

**Halaman: Buka Sesi** (`Pages/Guru/Presensi/Create.vue`)
- Field: Mata Pelajaran (select), Kelas (select), Tanggal (date picker, default: hari ini), Pertemuan Ke- (auto-increment dari pertemuan terakhir), Catatan (textarea, opsional)
- Durasi Kode: select (15 menit, 30 menit, 60 menit, Sampai ditutup manual)
- Tombol: Buka Sesi → generate kode → redirect ke halaman detail

**Halaman: Detail Sesi** (`Pages/Guru/Presensi/Show.vue`)
- Header: Mapel, Kelas, Tanggal, Pertemuan ke-N
- **Kode Akses**: ditampilkan besar (font-size besar, bisa di-proyeksi), dengan countdown expiry
  - Tombol: "Generate Kode Baru" (refresh icon)
  - Countdown: "Kode berlaku 25:30 lagi" atau "Kode kedaluwarsa" (merah)
- **Tabel Siswa**: Nama | NIS | Status (select: Hadir/Izin/Sakit/Alfa) | Waktu Check-in | Catatan
  - Status default: kosong (belum diisi)
  - Saat siswa input kode → status berubah ke "Hadir" secara real-time (Inertia polling atau manual refresh)
  - Guru bisa manual ubah status via dropdown di setiap baris
- Tombol: "Tutup Sesi" → confirm dialog "Siswa yang belum presensi akan ditandai Alfa. Lanjutkan?"
- Summary: "Hadir: 25, Izin: 2, Sakit: 1, Alfa: 4"

**Halaman: Rekap Presensi** (`Pages/Guru/Presensi/Recap.vue`)
- Filter: Mata Pelajaran, Kelas, Rentang Tanggal (date range picker)
- TanStack Table: Nama | NIS | Hadir | Izin | Sakit | Alfa | % Kehadiran
- Sortable by semua kolom
- Tombol: "Export Excel"
- Summary: "Rata-rata kehadiran: 89%. Siswa dengan kehadiran < 75%: 3 orang"

---

### T3.4.5 — Controller (Siswa)

**File**: `app/Http/Controllers/Siswa/AttendanceController.php`

| Method | Route | Description |
|--------|-------|-------------|
| `index()` | `GET /siswa/presensi` | Rekap presensi siswa sendiri |
| `checkIn()` | `POST /siswa/presensi/check-in` | Input kode presensi |

#### UI/UX Flow — Siswa

**Halaman: Presensi Saya** (`Pages/Siswa/Presensi/Index.vue`)
- **Form Check-in** (di atas): Input kode 6 digit (styled besar, auto-focus), tombol "Hadir"
  - Success: toast "Presensi berhasil dicatat ✓"
  - Error: toast merah "Kode salah" / "Kode kedaluwarsa" / "Sesi sudah ditutup"
- **Rekap Kehadiran** (di bawah):
  - Filter: Mata Pelajaran (select)
  - Tabel: Tanggal | Pertemuan Ke- | Mapel | Status (badge warna) | Catatan
  - Summary card: Hadir: 20 | Izin: 2 | Sakit: 1 | Alfa: 1 | Persentase: 83%

#### Edge Cases

- Guru buka 2 sesi presensi di hari yang sama untuk kelas+mapel yang sama → UNIQUE constraint mencegah, error "Sesi presensi untuk kelas ini hari ini sudah ada"
- Siswa input kode yang expired → error "Kode sudah kedaluwarsa, minta guru untuk generate kode baru"
- Guru tutup sesi tapi belum semua siswa check-in → siswa yang belum auto-set "Alfa"
- Siswa input kode dari kelas lain → validate bahwa siswa terdaftar di classroom_id sesi → error "Anda tidak terdaftar di kelas ini"
- Siswa input kode saat sesi is_open = false → error "Sesi presensi sudah ditutup"
- Kode expired tapi sesi masih open → guru bisa regenerate kode
- Meeting_number salah urut → auto-calculate: MAX(meeting_number WHERE classroom_id AND subject_id) + 1
- Export Excel gagal → queue job + notify guru saat selesai (reuse pattern dari export nilai ujian)

**Acceptance Criteria**:
- [ ] Guru bisa membuka sesi presensi dan mendapatkan kode akses 6 digit
- [ ] Kode akses memiliki expiry time yang configurable
- [ ] Guru bisa regenerate kode kapan saja
- [ ] Siswa bisa input kode dan otomatis ditandai "Hadir"
- [ ] Kode yang expired/salah/sesi ditutup ditolak dengan pesan jelas
- [ ] Guru bisa manual override status siswa (Hadir/Izin/Sakit/Alfa)
- [ ] Saat sesi ditutup, siswa tanpa record otomatis di-set "Alfa"
- [ ] Rekap presensi per siswa tersedia (total per status + persentase)
- [ ] Export rekap presensi ke Excel berfungsi
- [ ] 1 sesi per mapel per kelas per hari (constraint)

---

## Task 3.5: Sidebar Navigation Update

### Menu Baru yang Ditambahkan

Update `resources/js/components/AppSidebar.vue` — tambah menu items sesuai role:

#### Guru (prefix `/guru`)
```
Existing:
├── Dashboard           → /guru/dashboard
├── Bank Soal           → /guru/bank-soal
├── Ujian               → /guru/ujian
├── Penilaian           → /guru/grading

New (Phase 3) — tambahkan group "Pembelajaran":
├── 📚 Materi           → /guru/materi
├── 📝 Tugas            → /guru/tugas
├── 💬 Forum Diskusi    → /guru/forum
├── 📢 Pengumuman       → /guru/pengumuman
├── 📋 Presensi         → /guru/presensi
```

**Struktur sidebar Guru final**:
```
Dashboard
──────────── (separator: Ujian)
Bank Soal
Ujian
Penilaian
──────────── (separator: Pembelajaran)
Materi
Tugas
Forum Diskusi
Pengumuman
Presensi
```

#### Siswa (prefix `/siswa`)
```
Existing:
├── Dashboard           → /siswa/dashboard
├── Ujian               → /siswa/ujian
├── Nilai               → /siswa/nilai

New (Phase 3) — tambahkan group "Pembelajaran":
├── 📚 Materi           → /siswa/materi
├── 📝 Tugas            → /siswa/tugas
├── 💬 Forum Diskusi    → /siswa/forum
├── 📢 Pengumuman       → /siswa/pengumuman
├── 📋 Presensi         → /siswa/presensi
```

**Struktur sidebar Siswa final**:
```
Dashboard
──────────── (separator: Ujian)
Ujian
Nilai
──────────── (separator: Pembelajaran)
Materi
Tugas
Forum Diskusi
Pengumuman
Presensi
```

#### Admin
- Tidak ada perubahan sidebar admin di Phase 3.
- Admin bisa membuat pengumuman broadcast → tambahkan route `/admin/pengumuman` (CRUD pengumuman), tapi **tidak masuk sidebar** (akses via dashboard atau direct URL). Atau opsional: tambahkan menu "Pengumuman" di sidebar admin.

#### Implementation Notes
- Gunakan pattern `NavItem` yang sudah ada di `resources/js/types/navigation.ts`
- Gunakan icon dari Lucide (sudah terinstall via shadcn-vue): `BookOpen`, `FileText`, `MessageCircle`, `Megaphone`, `ClipboardList`
- Tambahkan `separator` antar group menu (pattern sudah ada di AppSidebar)

**Acceptance Criteria**:
- [ ] Sidebar guru menampilkan 5 menu baru di bawah separator "Pembelajaran"
- [ ] Sidebar siswa menampilkan 5 menu baru di bawah separator "Pembelajaran"
- [ ] Active state benar (highlight menu saat di halaman tersebut)
- [ ] Menu responsive di mobile (collapsible)

---

## Task 3.6: Dashboard Update (Phase 3)

### Guru Dashboard

Tambahkan section/card baru di `Pages/Guru/Dashboard.vue`:

| Card | Data | Query |
|------|------|-------|
| Tugas Perlu Dinilai | Count submission yang belum dinilai | `assignment_submissions WHERE graded_at IS NULL` (dari tugas guru) |
| Presensi Hari Ini | Sesi presensi aktif hari ini (jika ada) + link | `attendances WHERE meeting_date = today AND user_id = guru` |
| Materi Terbaru | 3 materi terakhir yang diupload guru | `materials WHERE user_id = guru ORDER BY created_at DESC LIMIT 3` |
| Pengumuman Terbaru | 3 pengumuman terakhir | `announcements WHERE user_id = guru ORDER BY created_at DESC LIMIT 3` |

### Siswa Dashboard

Tambahkan section/card baru di `Pages/Siswa/Dashboard.vue`:

| Card | Data | Query |
|------|------|-------|
| Tugas Mendatang | Tugas yang deadline-nya belum lewat + belum di-submit | `assignments WHERE deadline_at > now AND no submission` |
| Materi Terbaru | 3 materi terbaru dari kelas siswa | `materials WHERE published AND classroom_id IN student_classrooms` |
| Pengumuman | 3 pengumuman terbaru (pinned first) | `announcements WHERE classroom_id IN ... OR classroom_id IS NULL` |
| Presensi Hari Ini | Apakah sudah presensi hari ini? Jika ada sesi aktif, tampilkan link | Check `attendances WHERE meeting_date = today` → ada record? |

### TypeScript Types Update

Update `resources/js/types/grading.ts` (atau `lms.ts`):
```typescript
// Extend existing dashboard stats
export interface GuruDashboardStats {
  // ... existing fields ...
  pending_submissions: number;
  today_attendance_sessions: Attendance[];
  recent_materials: Material[];
  recent_announcements: Announcement[];
}

export interface SiswaDashboardStats {
  // ... existing fields ...
  upcoming_assignments: Assignment[];
  recent_materials: Material[];
  recent_announcements: Announcement[];
  today_attendance: { has_session: boolean; is_checked_in: boolean; attendance_id?: number } | null;
}
```

**Acceptance Criteria**:
- [ ] Dashboard guru menampilkan card tugas perlu dinilai, presensi hari ini, materi terbaru
- [ ] Dashboard siswa menampilkan card tugas mendatang, materi terbaru, pengumuman, presensi hari ini
- [ ] Data dashboard akurat dan query tidak N+1

---

## Database Indexing (Phase 3)

```sql
-- Materials
ALTER TABLE materials ADD INDEX idx_subject_classroom (subject_id, classroom_id);
ALTER TABLE materials ADD INDEX idx_user (user_id);
ALTER TABLE materials ADD INDEX idx_topic (topic);
ALTER TABLE material_progress ADD INDEX idx_material (material_id);
ALTER TABLE material_progress ADD INDEX idx_user (user_id);

-- Assignments
ALTER TABLE assignments ADD INDEX idx_subject_classroom (subject_id, classroom_id);
ALTER TABLE assignments ADD INDEX idx_user (user_id);
ALTER TABLE assignments ADD INDEX idx_deadline (deadline_at);
ALTER TABLE assignment_submissions ADD INDEX idx_assignment (assignment_id);
ALTER TABLE assignment_submissions ADD INDEX idx_user (user_id);
ALTER TABLE assignment_submissions ADD INDEX idx_graded (graded_at);

-- Discussions
ALTER TABLE discussion_threads ADD INDEX idx_subject_classroom (subject_id, classroom_id);
ALTER TABLE discussion_threads ADD INDEX idx_pinned_activity (is_pinned, last_reply_at);
ALTER TABLE discussion_replies ADD INDEX idx_thread_created (discussion_thread_id, created_at);

-- Announcements
ALTER TABLE announcements ADD INDEX idx_classroom (classroom_id);
ALTER TABLE announcements ADD INDEX idx_published (published_at);

-- Attendance
ALTER TABLE attendances ADD INDEX idx_classroom_subject (classroom_id, subject_id);
ALTER TABLE attendances ADD INDEX idx_meeting_date (meeting_date);
ALTER TABLE attendance_records ADD INDEX idx_attendance (attendance_id);
ALTER TABLE attendance_records ADD INDEX idx_user (user_id);
```

> **Catatan**: Semua index ini harus dibuat di migration file (bukan manual ALTER). Gunakan `$table->index(...)` di migration.

---

## Route Summary (Phase 3)

### Guru Routes (tambah di `routes/web.php`)

```php
// === Phase 3: LMS ===
// Materi
Route::resource('materi', MaterialController::class);
Route::get('materi/{material}/download', [MaterialController::class, 'download'])->name('materi.download');
Route::get('materi/{material}/progress', [MaterialController::class, 'progress'])->name('materi.progress');
Route::post('materi/reorder', [MaterialController::class, 'reorder'])->name('materi.reorder');

// Tugas
Route::resource('tugas', AssignmentController::class);
Route::get('tugas/{assignment}/download', [AssignmentController::class, 'download'])->name('tugas.download');
Route::get('tugas/{assignment}/submissions', [AssignmentController::class, 'submissions'])->name('tugas.submissions');
Route::put('tugas/submissions/{submission}/grade', [AssignmentController::class, 'grade'])->name('tugas.grade');
Route::get('tugas/submissions/{submission}/download', [AssignmentController::class, 'downloadSubmission'])->name('tugas.download-submission');

// Forum
Route::resource('forum', DiscussionController::class)->only(['index', 'show', 'store', 'destroy']);
Route::post('forum/{thread}/reply', [DiscussionController::class, 'reply'])->name('forum.reply');
Route::delete('forum/reply/{reply}', [DiscussionController::class, 'deleteReply'])->name('forum.delete-reply');
Route::post('forum/{thread}/toggle-pin', [DiscussionController::class, 'togglePin'])->name('forum.toggle-pin');
Route::post('forum/{thread}/toggle-lock', [DiscussionController::class, 'toggleLock'])->name('forum.toggle-lock');

// Pengumuman
Route::resource('pengumuman', AnnouncementController::class);
Route::post('pengumuman/{announcement}/toggle-pin', [AnnouncementController::class, 'togglePin'])->name('pengumuman.toggle-pin');

// Presensi
Route::resource('presensi', AttendanceController::class)->only(['index', 'create', 'store', 'show']);
Route::post('presensi/{attendance}/close', [AttendanceController::class, 'close'])->name('presensi.close');
Route::post('presensi/{attendance}/regenerate-code', [AttendanceController::class, 'regenerateCode'])->name('presensi.regenerate-code');
Route::put('presensi/{attendance}/status', [AttendanceController::class, 'updateStatus'])->name('presensi.update-status');
Route::get('presensi/recap', [AttendanceController::class, 'recap'])->name('presensi.recap');
Route::get('presensi/recap/export', [AttendanceController::class, 'exportRecap'])->name('presensi.export-recap');
```

### Siswa Routes (tambah di `routes/web.php`)

```php
// === Phase 3: LMS ===
// Materi
Route::get('materi', [MaterialController::class, 'index'])->name('materi.index');
Route::get('materi/{material}', [MaterialController::class, 'show'])->name('materi.show');
Route::get('materi/{material}/download', [MaterialController::class, 'download'])->name('materi.download');
Route::post('materi/{material}/complete', [MaterialController::class, 'complete'])->name('materi.complete');

// Tugas
Route::get('tugas', [AssignmentController::class, 'index'])->name('tugas.index');
Route::get('tugas/{assignment}', [AssignmentController::class, 'show'])->name('tugas.show');
Route::post('tugas/{assignment}/submit', [AssignmentController::class, 'submit'])->name('tugas.submit');
Route::get('tugas/{assignment}/download', [AssignmentController::class, 'download'])->name('tugas.download');

// Forum
Route::get('forum', [DiscussionController::class, 'index'])->name('forum.index');
Route::get('forum/{thread}', [DiscussionController::class, 'show'])->name('forum.show');
Route::post('forum', [DiscussionController::class, 'store'])->name('forum.store');
Route::delete('forum/{thread}', [DiscussionController::class, 'destroy'])->name('forum.destroy');
Route::post('forum/{thread}/reply', [DiscussionController::class, 'reply'])->name('forum.reply');
Route::delete('forum/reply/{reply}', [DiscussionController::class, 'deleteReply'])->name('forum.delete-reply');

// Pengumuman
Route::get('pengumuman', [AnnouncementController::class, 'index'])->name('pengumuman.index');
Route::get('pengumuman/{announcement}', [AnnouncementController::class, 'show'])->name('pengumuman.show');

// Presensi
Route::get('presensi', [AttendanceController::class, 'index'])->name('presensi.index');
Route::post('presensi/check-in', [AttendanceController::class, 'checkIn'])->name('presensi.check-in');
```

---

## File Inventory (Phase 3)

### Backend Files to Create

```
app/Enums/
├── MaterialType.php
├── SubmissionType.php
├── AttendanceStatus.php

app/Models/
├── Material.php
├── MaterialProgress.php
├── Assignment.php
├── AssignmentSubmission.php
├── DiscussionThread.php
├── DiscussionReply.php
├── Announcement.php
├── Attendance.php
├── AttendanceRecord.php

app/Services/LMS/
├── MaterialService.php
├── AssignmentService.php
├── DiscussionService.php
├── AnnouncementService.php
├── AttendanceService.php

app/Http/Controllers/Guru/
├── MaterialController.php
├── AssignmentController.php
├── DiscussionController.php
├── AnnouncementController.php
├── AttendanceController.php

app/Http/Controllers/Siswa/
├── MaterialController.php
├── AssignmentController.php
├── DiscussionController.php
├── AnnouncementController.php
├── AttendanceController.php

app/Http/Requests/Guru/
├── StoreMaterialRequest.php
├── UpdateMaterialRequest.php
├── StoreAssignmentRequest.php
├── UpdateAssignmentRequest.php
├── StoreDiscussionThreadRequest.php
├── StoreAnnouncementRequest.php
├── UpdateAnnouncementRequest.php
├── StoreAttendanceRequest.php

app/Http/Requests/Siswa/
├── SubmitAssignmentRequest.php
├── StoreDiscussionThreadRequest.php
├── CheckInAttendanceRequest.php

app/Policies/
├── MaterialPolicy.php
├── AssignmentPolicy.php
├── DiscussionThreadPolicy.php
├── AnnouncementPolicy.php
├── AttendancePolicy.php

app/Events/
├── DiscussionReplyCreated.php

app/Exports/
├── AttendanceRecapExport.php

database/migrations/
├── 2026_XX_XX_010000_create_materials_table.php
├── 2026_XX_XX_010001_create_material_progress_table.php
├── 2026_XX_XX_020000_create_assignments_table.php
├── 2026_XX_XX_020001_create_assignment_submissions_table.php
├── 2026_XX_XX_030000_create_discussion_threads_table.php
├── 2026_XX_XX_030001_create_discussion_replies_table.php
├── 2026_XX_XX_030002_create_announcements_table.php
├── 2026_XX_XX_040000_create_attendances_table.php
├── 2026_XX_XX_040001_create_attendance_records_table.php
```

### Frontend Files to Create

```
resources/js/types/
├── lms.ts (NEW — all LMS types)

resources/js/composables/
├── useYouTubeEmbed.ts

resources/js/Pages/Guru/
├── Materi/Index.vue
├── Materi/Create.vue
├── Materi/Show.vue
├── Materi/Edit.vue
├── Tugas/Index.vue
├── Tugas/Create.vue
├── Tugas/Show.vue (includes grading)
├── Tugas/Edit.vue
├── Forum/Index.vue
├── Forum/Show.vue
├── Pengumuman/Index.vue
├── Pengumuman/Create.vue
├── Pengumuman/Edit.vue
├── Presensi/Index.vue
├── Presensi/Create.vue
├── Presensi/Show.vue
├── Presensi/Recap.vue

resources/js/Pages/Siswa/
├── Materi/Index.vue
├── Materi/Show.vue
├── Tugas/Index.vue
├── Tugas/Show.vue (includes submit form)
├── Forum/Index.vue
├── Forum/Show.vue
├── Pengumuman/Index.vue
├── Pengumuman/Show.vue
├── Presensi/Index.vue (includes check-in form)
```

### Files to Modify

```
routes/web.php                     — tambah semua route Phase 3
resources/js/components/AppSidebar.vue — tambah menu Pembelajaran
resources/js/Pages/Guru/Dashboard.vue  — tambah cards LMS
resources/js/Pages/Siswa/Dashboard.vue — tambah cards LMS
app/Http/Controllers/Guru/DashboardController.php  — tambah stats
app/Http/Controllers/Siswa/DashboardController.php — tambah stats
resources/js/types/grading.ts      — extend dashboard stats types
```

---

## Recommended Implementation Order

```
Sprint 1 (Minggu 11-12):
1. T3.1.1 — Migrations: materials, material_progress
2. T3.1.2 — Models + Enums + Types
3. T3.1.3 — TypeScript types (lms.ts)
4. T3.1.4 — Guru CRUD Materi (Service, Controller, Pages, Policy)
5. T3.1.5 — Siswa Browse Materi (Controller, Pages)
6. T3.1.6 — Guru Progress Overview

Sprint 2 (Minggu 12-13):
7. T3.2.1 — Migrations: assignments, assignment_submissions
8. T3.2.2 — Models + Enums + Types
9. T3.2.3 — Guru CRUD Tugas (Service, Controller, Pages, Policy)
10. T3.2.4 — Siswa View + Submit Tugas

Sprint 3 (Minggu 13-14):
11. T3.3.1 — Migrations: discussions, announcements
12. T3.3.2 — Models + Types
13. T3.3.3 — Forum CRUD + Reply (Guru + Siswa)
14. T3.3.4 — Pengumuman (Guru + Siswa)
15. T3.4.1 — Migrations: attendances, attendance_records
16. T3.4.2 — Models + Enums + Types
17. T3.4.3-5 — Presensi (Service, Guru Controller, Siswa Controller)

Sprint 4 (Buffer / Polish):
18. T3.5 — Sidebar Navigation Update
19. T3.6 — Dashboard Update
20. Seeder: demo data LMS
21. Testing: feature tests untuk semua fitur Phase 3
```

> **Note**: Sidebar update (T3.5) bisa dilakukan di awal Sprint 1 untuk UX consistency, atau di akhir saat semua fitur sudah jadi. Rekomendasi: tambahkan menu item tapi buat halaman placeholder "Coming Soon" di awal, lalu isi progressif.
