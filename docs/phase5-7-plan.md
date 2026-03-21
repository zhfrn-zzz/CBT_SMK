# Phase 5, 6, 7 — Detailed Implementation Plan

> **Status**: Planning (belum diimplementasi)
> **Prerequisites**: Phase 1-4 sudah selesai (CBT core, proctor, LMS, analytics, audit)
> **Codebase baseline**: 42 migrations, 28 models, 36 controllers, 83 Vue pages

---

## Table of Contents

1. [Phase 5 — Fitur Tambahan](#phase-5--fitur-tambahan)
   - [Task 5.1 — Beranda Publik](#task-51--beranda-publik)
   - [Task 5.2 — Beranda Setelah Login](#task-52--beranda-setelah-login-home)
   - [Task 5.3 — Forum Sekolah-wide](#task-53--forum-sekolah-wide)
   - [Task 5.4 — Profil User](#task-54--profil-user)
   - [Task 5.5 — Kalender Akademik](#task-55--kalender-akademik)
   - [Task 5.6 — Manajemen File/Storage](#task-56--manajemen-filestorage)
2. [Phase 6 — Security Hardening](#phase-6--security-hardening)
   - [Task 6.1 — Rate Limiting](#task-61--rate-limiting)
   - [Task 6.2 — Input Sanitization & XSS](#task-62--input-sanitization--xss)
   - [Task 6.3 — Session Security](#task-63--session-security)
   - [Task 6.4 — Exam Security Hardening](#task-64--exam-security-hardening)
   - [Task 6.5 — Data Protection](#task-65--data-protection)
   - [Task 6.6 — Audit & Logging Review](#task-66--audit--logging-review)
3. [Phase 7 — UI/UX Polish](#phase-7--uiux-polish)
4. [Dependency Graph](#dependency-graph)

---

## Phase 5 — Fitur Tambahan

### Task 5.1 — Beranda Publik

**Deskripsi**: Mengganti halaman `Welcome.vue` (default Laravel starter kit) dengan beranda publik yang menampilkan informasi SMK Bina Mandiri untuk pengunjung yang belum login.

**Kompleksitas**: Medium

**Kondisi saat ini**:
- `Welcome.vue` masih template default Laravel (menampilkan "Let's get started" + link ke docs Laravel)
- Route `GET /` sudah ada, menampilkan `Welcome.vue`
- Announcements sudah ada tapi belum ada flag `is_public`
- Exam sessions sudah ada tapi belum ada konsep "jadwal publik"

#### Database Changes

```
Migration: add_is_public_to_announcements_table
- announcements:
  + is_public: boolean, default false, indexed
  (Guru/admin bisa tandai pengumuman sebagai publik, tampil di beranda tanpa login)
```

Tidak perlu tabel baru — data sekolah (nama, logo, alamat) cukup di config atau `.env` karena single-school app.

#### Routes Baru

| Method | URI | Controller | Auth |
|--------|-----|------------|------|
| `GET` | `/` | `PublicHomeController@index` | Guest |
| `GET` | `/api/public/announcements` | `PublicHomeController@announcements` | Guest (optional, jika lazy load) |

> Note: Route `GET /` sudah ada. Controller perlu diganti dari closure/redirect ke `PublicHomeController`.

#### Components Baru

```
resources/js/pages/
  Welcome.vue (REWRITE — bukan file baru)

resources/js/components/Public/
  SchoolHero.vue        — Hero section: logo, nama sekolah, tagline
  PublicAnnouncement.vue — Card pengumuman publik
  PublicExamSchedule.vue — Tabel jadwal ujian umum (tanpa detail soal)
  LoginCTA.vue          — Call-to-action tombol Masuk
```

**Layout**: Tidak pakai `AppLayout` (yang butuh auth). Gunakan layout baru `GuestLayout.vue` atau inline di `Welcome.vue` (sudah ada pattern ini di current Welcome.vue).

#### Acceptance Criteria

1. ✅ Halaman `/` menampilkan: logo sekolah, nama "SMK Bina Mandiri", alamat
2. ✅ Menampilkan pengumuman yang ditandai `is_public = true`, max 5 terbaru
3. ✅ Menampilkan jadwal ujian mendatang (nama ujian, tanggal, mata pelajaran) — tanpa detail soal
4. ✅ Tombol "Masuk" prominent, redirect ke `/login`
5. ✅ Jika sudah login, tombol berubah menjadi "Dashboard" (sudah ada pattern ini)
6. ✅ Responsive: mobile, tablet, desktop
7. ✅ Dark mode support (ikuti pattern existing)
8. ✅ SEO-friendly: proper `<Head>` title, meta description

#### Edge Cases

- Tidak ada pengumuman publik → section disembunyikan atau tampilkan placeholder "Belum ada pengumuman"
- Tidak ada ujian mendatang → section disembunyikan
- School info (nama, alamat, logo) dari config — jika belum diisi, tampilkan default

#### Config Baru

```env
# .env
SCHOOL_NAME="SMK Bina Mandiri"
SCHOOL_ADDRESS="Jl. Contoh No. 123, Kota"
SCHOOL_LOGO_PATH="images/logo.png"   # di public/
SCHOOL_TAGLINE="Mencetak Generasi Mandiri dan Berkarakter"
```

```php
# config/school.php
return [
    'name' => env('SCHOOL_NAME', 'SMK Bina Mandiri'),
    'address' => env('SCHOOL_ADDRESS', ''),
    'logo_path' => env('SCHOOL_LOGO_PATH', 'images/logo.png'),
    'tagline' => env('SCHOOL_TAGLINE', ''),
];
```

#### Dependencies

- Tidak ada dependency ke task lain

---

### Task 5.2 — Beranda Setelah Login (Home)

**Deskripsi**: Halaman pertama setelah login — "Apa yang terjadi hari ini". Berbeda dari Dashboard yang sudah ada (lebih ke statistik/analytics).

**Kompleksitas**: Complex

**Kondisi saat ini**:
- Dashboard sudah ada per role: `Admin/Dashboard.vue`, `Guru/Dashboard.vue`, `Siswa/Dashboard.vue`
- Dashboard menampilkan: statistik angka (jumlah user, ujian aktif, dll), upcoming exams, pending grading
- Belum ada konsep "Home" yang fokus pada timeline/activity hari ini

#### Keputusan Arsitektur: Home vs Dashboard — GABUNG

**Rekomendasi: Gabung Home + Dashboard menjadi satu halaman yang informatif.**

**Reasoning**:
1. **Menghindari kebingungan user**: Dua halaman terpisah (Home vs Dashboard) membuat user bingung "mana yang harus saya buka?" — terutama untuk siswa SMK yang tidak tech-savvy.
2. **Mengurangi navigasi**: Satu halaman yang menampilkan KEDUA informasi (apa yang terjadi hari ini + statistik ringkas) lebih efisien. User tidak perlu bolak-balik.
3. **Pattern umum**: Aplikasi seperti Google Classroom, Moodle, dan Canvas menggunakan satu halaman utama yang menggabungkan activity feed + ringkasan.
4. **Implementation cost**: Membangun + maintain 2 halaman mirip = double effort. Lebih baik satu halaman yang komprehensif.

**Implementasi**: Redesign Dashboard yang sudah ada. Tambahkan section "Apa yang terjadi hari ini" di ATAS statistik yang sudah ada. Dashboard menjadi one-stop-shop.

#### Database Changes

Tidak ada — semua data sudah tersedia dari model existing. Hanya perlu query baru di controller.

#### Routes

Tidak ada route baru — gunakan route dashboard yang sudah ada:
- `GET /admin/dashboard`
- `GET /guru/dashboard`
- `GET /siswa/dashboard`

Controller yang sudah ada di-enhance untuk mengirim data tambahan.

#### Components Baru

```
resources/js/components/Dashboard/
  TodaySection.vue           — Container "Hari Ini" section
  UpcomingExamCard.vue        — Card ujian mendatang (ada di Siswa, refactor jadi shared)
  RecentAnnouncementCard.vue  — Card pengumuman terbaru
  DeadlineReminderCard.vue    — Card deadline tugas terdekat (siswa)
  PendingGradingCard.vue      — Card tugas/esai perlu dinilai (guru)
  ActiveExamMonitor.vue       — Mini card ujian sedang berlangsung (guru)
  TodayAttendanceCard.vue     — Card presensi hari ini (guru)
  QuickStats.vue              — Widget angka ringkas (admin)
  RecentActivityFeed.vue      — Activity log terbaru (admin)
  NewMaterialCard.vue         — Card materi baru (siswa)
```

#### Data yang ditampilkan per role

**Siswa Dashboard (enhanced)**:
1. 🔔 Pengumuman terbaru (max 3, link "Lihat Semua")
2. 📝 Ujian hari ini / mendatang minggu ini (max 5)
3. 📅 Deadline tugas terdekat (H-3) (max 3)
4. 📚 Materi baru minggu ini (max 3)
5. 📊 Statistik ringkas (sudah ada): nilai rata-rata, ujian selesai, dll

**Guru Dashboard (enhanced)**:
1. 🔔 Pengumuman terbaru (max 3)
2. 🟢 Ujian yang SEDANG berlangsung (real-time count peserta)
3. 📝 Tugas/esai yang perlu dinilai (count per mapel)
4. 📋 Presensi hari ini: kelas mana yang belum absen
5. 📊 Statistik ringkas (sudah ada): kelas diampu, esai pending, dll

**Admin Dashboard (enhanced)**:
1. 🔔 Pengumuman terbaru (max 3)
2. 📊 Statistik ringkas (sudah ada): jumlah user, ujian hari ini, dll
3. 🕑 Aktivitas terbaru: 10 audit log terakhir (user X login, guru Y buat ujian, dll)
4. ⚠️ Alert: jika ada ujian aktif, tampilkan info

#### Acceptance Criteria

1. ✅ Dashboard menampilkan section "Hari Ini" di atas statistik existing
2. ✅ Siswa: melihat pengumuman, ujian mendatang, deadline tugas, materi baru
3. ✅ Guru: melihat pengumuman, ujian aktif, pending grading, presensi hari ini
4. ✅ Admin: melihat pengumuman, statistik, aktivitas terbaru
5. ✅ Setiap card punya link ke halaman detail terkait
6. ✅ Data di-cache di Redis (5 menit TTL) untuk performa
7. ✅ Empty state yang informatif jika tidak ada data
8. ✅ Responsive layout: 1 kolom mobile, 2-3 kolom desktop

#### Edge Cases

- User baru (belum ada kelas/mapel) → tampilkan welcome message + petunjuk
- Tidak ada ujian/tugas → hide section, jangan tampilkan section kosong
- Data cached tapi ada ujian baru dimulai → cache invalidation saat exam status berubah

#### Dependencies

- Task 5.1 (untuk shared announcement card component)

---

### Task 5.3 — Forum Sekolah-wide

**Deskripsi**: Forum umum lintas kelas/mapel yang bisa diakses semua user. Berbeda dari forum per-kelas yang sudah ada di Phase 3.

**Kompleksitas**: Medium

**Kondisi saat ini**:
- Forum per-classroom sudah ada: `discussion_threads` tabel dengan `subject_id` (NOT NULL) dan `classroom_id` (NOT NULL)
- Model `DiscussionThread` dan `DiscussionReply` sudah ada
- Controller `Guru/DiscussionController` dan `Siswa/DiscussionController` sudah ada
- Pattern pin/lock thread sudah ada

#### Database Changes

```
Migration: create_forum_categories_table
- forum_categories:
  + id: bigint unsigned PK
  + name: varchar(255)
  + slug: varchar(255), unique
  + description: text, nullable
  + color: varchar(7), nullable (hex color untuk badge)
  + order: int, default 0
  + is_active: boolean, default true
  + timestamps

Migration: create_forum_threads_table
- forum_threads:
  + id: bigint unsigned PK
  + forum_category_id: FK → forum_categories, nullable (null = Umum)
  + user_id: FK → users
  + title: varchar(255)
  + content: text
  + is_pinned: boolean, default false
  + is_locked: boolean, default false
  + last_reply_at: datetime, nullable
  + reply_count: int, default 0
  + view_count: int, default 0
  + timestamps
  + INDEX: (forum_category_id, is_pinned, last_reply_at)
  + INDEX: (user_id)

Migration: create_forum_replies_table
- forum_replies:
  + id: bigint unsigned PK
  + forum_thread_id: FK → forum_threads, CASCADE
  + user_id: FK → users
  + content: text
  + timestamps
  + INDEX: (forum_thread_id, created_at)
```

> **Kenapa tabel baru, bukan reuse `discussion_threads`?**
> `discussion_threads` memiliki `subject_id` dan `classroom_id` sebagai NOT NULL foreign keys — mereka secara struktural terikat ke konteks kelas/mapel. Forum sekolah-wide tidak punya konteks ini. Membuat tabel terpisah lebih bersih daripada membuat kolom existing nullable (yang akan break integrity existing data dan queries).

#### Routes Baru

| Method | URI | Controller | Middleware |
|--------|-----|------------|------------|
| `GET` | `/forum` | `ForumController@index` | `auth,verified` |
| `GET` | `/forum/create` | `ForumController@create` | `auth,verified` |
| `POST` | `/forum` | `ForumController@store` | `auth,verified` |
| `GET` | `/forum/{thread}` | `ForumController@show` | `auth,verified` |
| `DELETE` | `/forum/{thread}` | `ForumController@destroy` | `auth,verified` |
| `POST` | `/forum/{thread}/reply` | `ForumController@reply` | `auth,verified` |
| `DELETE` | `/forum/reply/{reply}` | `ForumController@destroyReply` | `auth,verified` |
| `POST` | `/forum/{thread}/toggle-pin` | `ForumController@togglePin` | `auth,verified,role:admin,guru` |
| `POST` | `/forum/{thread}/toggle-lock` | `ForumController@toggleLock` | `auth,verified,role:admin,guru` |
| `GET` | `/admin/forum-categories` | `Admin\ForumCategoryController@index` | `auth,verified,role:admin` |
| `POST` | `/admin/forum-categories` | `Admin\ForumCategoryController@store` | `auth,verified,role:admin` |
| `PUT` | `/admin/forum-categories/{category}` | `Admin\ForumCategoryController@update` | `auth,verified,role:admin` |
| `DELETE` | `/admin/forum-categories/{category}` | `Admin\ForumCategoryController@destroy` | `auth,verified,role:admin` |

> Note: Forum route di top-level `/forum` (bukan di bawah `/admin`, `/guru`, atau `/siswa`) karena accessible oleh semua role.

#### Models Baru

```
app/Models/
  ForumCategory.php    — name, slug, description, color, order, is_active
  ForumThread.php      — Relasi: category, user, replies
  ForumReply.php       — Relasi: thread, user
```

#### Controllers Baru

```
app/Http/Controllers/
  ForumController.php                    — Shared forum (semua role)
  Admin/ForumCategoryController.php      — CRUD kategori (admin only)
```

#### Components Baru

```
resources/js/pages/Forum/
  Index.vue          — List threads dengan filter kategori + search
  Create.vue         — Form buat thread baru
  Show.vue           — Thread detail + replies

resources/js/pages/Admin/ForumCategories/
  Index.vue          — CRUD kategori forum

resources/js/components/Forum/
  ThreadCard.vue     — Card preview thread (title, author, reply count, last activity)
  CategoryBadge.vue  — Badge warna per kategori
  ReplyItem.vue      — Single reply (avatar, nama, role badge, waktu, content)
  ForumSearch.vue    — Search input dengan debounce
```

#### Acceptance Criteria

1. ✅ Semua user (admin, guru, siswa) bisa akses forum di `/forum`
2. ✅ Admin bisa CRUD kategori forum (Umum, Akademik, Teknologi, dll)
3. ✅ User bisa buat thread, pilih kategori, tulis content (Tiptap editor)
4. ✅ User bisa reply ke thread
5. ✅ Admin/guru bisa pin & lock thread
6. ✅ Admin/guru bisa hapus thread/reply siapapun; siswa hanya bisa hapus milik sendiri
7. ✅ Search forum by title/content (LIKE query, cukup untuk skala sekolah)
8. ✅ Filter by kategori
9. ✅ Pagination (20 threads per page)
10. ✅ Thread menampilkan: badge role author (Admin/Guru/Siswa), badge kategori berwarna
11. ✅ Locked thread: tidak bisa reply, tampilkan icon gembok
12. ✅ Pinned thread: selalu di atas

#### Edge Cases

- User dihapus → cascade replies, atau soft-handle dengan "Deleted User"
- Kategori dihapus → threads di kategori itu: set `forum_category_id = null` (Umum)
- Empty forum → tampilkan CTA "Mulai diskusi pertama"
- Thread sangat panjang (100+ replies) → paginate replies (20 per page)
- XSS di content → sanitize HTML (terkait Task 6.2)

#### Dependencies

- Tidak ada hard dependency
- Soft dependency ke Task 6.2 (sanitization) — tapi bisa implement dulu, sanitize nanti

---

### Task 5.4 — Profil User

**Deskripsi**: Halaman profil lengkap per user. Extend settings/profile yang sudah ada dengan informasi role-specific.

**Kompleksitas**: Medium

**Kondisi saat ini**:
- `settings/Profile.vue` sudah ada — hanya menampilkan nama + email
- `settings/Password.vue` sudah ada — ganti password
- `settings/TwoFactor.vue` sudah ada — setup 2FA
- User table: `id, name, username, email, role, is_active, password, 2FA fields`
- **BELUM ADA**: foto profil, halaman profil publik, rekap nilai/kehadiran per siswa

#### Database Changes

```
Migration: add_profile_fields_to_users_table
- users:
  + photo_path: varchar(255), nullable   (path ke storage)
  + phone: varchar(20), nullable
  + bio: text, nullable
```

> Note: Tidak tambah kolom `kelas`, `jurusan`, `mapel` karena sudah ada di tabel relasi (classroom_student, classroom_subject_teacher). Query join saja.

#### Routes Baru

| Method | URI | Controller | Middleware |
|--------|-----|------------|------------|
| `GET` | `/profile/{user}` | `ProfileViewController@show` | `auth,verified` |
| `POST` | `/settings/profile/photo` | `Settings\ProfileController@updatePhoto` | `auth,verified` |
| `DELETE` | `/settings/profile/photo` | `Settings\ProfileController@deletePhoto` | `auth,verified` |

> Note: `/profile/{user}` adalah halaman profil "view" (read-only). `/settings/profile` tetap untuk edit profil sendiri. Admin bisa akses `/profile/{user}` untuk semua user; guru/siswa hanya bisa lihat profil orang dalam konteks yang sama (sekelas/semapel).

#### Controllers

```
app/Http/Controllers/
  ProfileViewController.php             — View profil user (read-only, semua role)
  Settings/ProfileController.php        — EXTEND existing: tambah upload foto
```

#### Components Baru

```
resources/js/pages/Profile/
  Show.vue              — Halaman profil user

resources/js/components/Profile/
  ProfileHeader.vue     — Foto, nama, role badge, info dasar
  SiswaProfileInfo.vue  — Kelas, jurusan, rekap nilai, rekap kehadiran
  GuruProfileInfo.vue   — Mapel diampu, kelas diampu
  AdminProfileInfo.vue  — Info admin (minimal)
  ExamScoreRecap.vue    — Tabel list ujian + nilai siswa
  AttendanceRecap.vue   — Persentase kehadiran + detail per bulan
  PhotoUpload.vue       — Upload/crop foto profil (di settings)
```

#### Data yang ditampilkan

**Profil Siswa** (`/profile/{user}`):
- Foto, nama, username, role badge, email
- Kelas aktif (tahun ajaran ini)
- Jurusan
- Rekap Nilai: tabel (nama ujian, mapel, tanggal, nilai, status lulus/remedial)
- Rekap Kehadiran: persentase hadir, chart/tabel per bulan (hadir/izin/sakit/alfa)

**Profil Guru** (`/profile/{user}`):
- Foto, nama, username, role badge, email
- Daftar mata pelajaran yang diampu
- Daftar kelas yang diampu (tahun ajaran ini)

**Profil Admin** (`/profile/{user}`):
- Foto, nama, username, role badge, email
- Bio (jika ada)

**Edit Profil** (`/settings/profile` — enhance existing):
- Tambah: upload foto profil (accept: jpg, png, webp, max 2MB)
- Tambah: phone number (opsional)
- Existing: nama, email tetap

#### Acceptance Criteria

1. ✅ Setiap user bisa lihat profil sendiri di `/profile/{id}`
2. ✅ Admin bisa lihat profil semua user
3. ✅ Guru bisa lihat profil siswa di kelasnya
4. ✅ Siswa bisa lihat profil guru dan teman sekelas
5. ✅ Siswa: halaman profil menampilkan rekap nilai (semua ujian + nilai)
6. ✅ Siswa: halaman profil menampilkan rekap kehadiran (persentase + detail)
7. ✅ Guru: halaman profil menampilkan mapel + kelas diampu
8. ✅ Upload foto profil di settings (max 2MB, resize ke 300x300)
9. ✅ Foto profil ditampilkan di profil, navbar, dan forum
10. ✅ Ganti password sudah ada (verify existing)

#### Edge Cases

- User tanpa foto → tampilkan initial letter avatar (sudah common pattern)
- Siswa belum ada kelas (belum di-assign) → tampilkan "Belum ada kelas"
- Siswa belum ujian → rekap nilai kosong dengan message "Belum ada hasil ujian"
- File upload gagal (terlalu besar, format salah) → validation error yang jelas
- Foto lama di-replace → hapus file lama dari disk

#### Dependencies

- Tidak ada hard dependency
- Foto profil akan digunakan di Task 5.3 (forum) untuk avatar

---

### Task 5.5 — Kalender Akademik

**Deskripsi**: View kalender bulanan yang menampilkan events akademik.

**Kompleksitas**: Medium

#### Rekomendasi: IMPLEMENT, tapi sebagai enhancement Dashboard — bukan halaman terpisah

**Reasoning**:
1. **Worth it? Ya**, tapi dengan scope yang tepat. Kalender visual sangat membantu siswa dan guru melihat jadwal sebulan ke depan dalam sekali pandang. List di beranda hanya menampilkan "yang terdekat", sedangkan kalender menunjukkan distribusi jadwal.
2. **Bukan halaman terpisah**: Taruh di sub-section Dashboard yang bisa di-toggle (tab atau link). Jangan buat halaman terpisah yang jarang dikunjungi.
3. **Data sudah ada**: `exam_sessions` (starts_at, ends_at), `assignments` (deadline_at), `attendances` (meeting_date) — tinggal query dan render.
4. **Scope minimal**: Hanya VIEW, tidak ada CRUD event manual. Events otomatis dari data yang sudah ada.
5. **Library**: Gunakan custom calendar grid (Tailwind) atau library ringan. JANGAN pakai FullCalendar (terlalu berat untuk kebutuhan ini).

#### Database Changes

Tidak ada — semua data sudah tersedia.

#### Routes Baru

| Method | URI | Controller | Middleware |
|--------|-----|------------|------------|
| `GET` | `/siswa/kalender` | `Siswa\CalendarController@index` | `auth,verified,role:siswa` |
| `GET` | `/guru/kalender` | `Guru\CalendarController@index` | `auth,verified,role:guru` |
| `GET` | `/api/calendar/events` | `CalendarEventController@index` | `auth,verified` |

> API endpoint mengembalikan events dalam range bulan tertentu (query param: `month`, `year`).

#### Controllers Baru

```
app/Http/Controllers/
  Siswa/CalendarController.php    — Render halaman kalender siswa
  Guru/CalendarController.php     — Render halaman kalender guru
  CalendarEventController.php     — API: return events JSON per bulan
```

#### Components Baru

```
resources/js/pages/Siswa/
  Kalender.vue            — Halaman kalender siswa

resources/js/pages/Guru/
  Kalender.vue            — Halaman kalender guru

resources/js/components/Calendar/
  CalendarGrid.vue        — Grid kalender bulanan (reusable)
  CalendarDay.vue         — Single day cell dengan event dots
  CalendarEvent.vue       — Tooltip/popover detail event
  MonthNavigator.vue      — Previous/Next month buttons
```

#### Event Types

| Source | Color | Label |
|--------|-------|-------|
| `exam_sessions` (starts_at) | 🔴 Red | Ujian |
| `assignments` (deadline_at) | 🟡 Yellow | Deadline Tugas |
| `attendances` (meeting_date) | 🔵 Blue | Presensi |

#### Acceptance Criteria

1. ✅ Siswa melihat kalender bulanan dengan events dari kelas/mapel mereka
2. ✅ Guru melihat kalender bulanan dengan events yang mereka buat
3. ✅ Navigasi bulan (previous/next)
4. ✅ Klik tanggal → popup/sidebar list events hari itu
5. ✅ Color coding per jenis event
6. ✅ Responsive: grid menyesuaikan ukuran layar
7. ✅ Events di-fetch via API per bulan (jangan load semua sekaligus)
8. ✅ Cache events per bulan di Redis (TTL 10 menit)

#### Edge Cases

- Bulan tanpa event → tampilkan kalender kosong (tidak error)
- Banyak event di satu hari → tampilkan max 3 dots + "+N more"
- Timezone: semua waktu dalam WIB (Asia/Jakarta)
- Exam session spanning multiple days → tampilkan di hari pertama saja

#### Dependencies

- Tidak ada hard dependency

---

### Task 5.6 — Manajemen File/Storage

**Deskripsi**: Tools admin dan guru untuk mengelola file yang sudah diupload.

**Kompleksitas**: Medium

**Kondisi saat ini**:
- File upload sudah ada untuk: materi (PDF/DOCX/PPTX/image), soal (gambar di Tiptap), tugas (file attachment), submission siswa
- Storage di `storage/app/` — subdirectories: `materials/`, `questions/`, `assignments/`, `submissions/`
- Tidak ada dashboard usage atau cleanup tool

#### Database Changes

Tidak ada — informasi file sudah ada di tabel existing (`materials.file_path`, `materials.file_size`, `assignments.file_path`, `assignment_submissions.file_path`, `questions.media_path`).

#### Routes Baru

| Method | URI | Controller | Middleware |
|--------|-----|------------|------------|
| `GET` | `/admin/storage` | `Admin\StorageController@index` | `auth,verified,role:admin` |
| `POST` | `/admin/storage/cleanup` | `Admin\StorageController@cleanup` | `auth,verified,role:admin` |
| `GET` | `/admin/storage/scan` | `Admin\StorageController@scan` | `auth,verified,role:admin` |
| `GET` | `/guru/file-manager` | `Guru\FileManagerController@index` | `auth,verified,role:guru` |
| `DELETE` | `/guru/file-manager/{type}/{id}` | `Guru\FileManagerController@destroy` | `auth,verified,role:guru` |

#### Controllers Baru

```
app/Http/Controllers/
  Admin/StorageController.php        — Dashboard storage + cleanup
  Guru/FileManagerController.php     — File manager guru
```

#### Jobs Baru

```
app/Jobs/
  CleanupOrphanedFilesJob.php        — Scan disk vs DB, hapus file orphan
```

#### Components Baru

```
resources/js/pages/Admin/Storage/
  Index.vue              — Dashboard storage overview

resources/js/pages/Guru/FileManager/
  Index.vue              — File manager guru

resources/js/components/Storage/
  StorageOverview.vue    — Total used, breakdown per category (pie chart)
  OrphanFileList.vue     — List file yang ada di disk tapi tidak di DB
  FileTable.vue          — Tabel file (nama, ukuran, tipe, tanggal upload, link)
```

#### Data yang ditampilkan

**Admin Storage Dashboard**:
- Total storage terpakai (scan disk atau sum dari DB)
- Breakdown per kategori: Materi, Soal (gambar), Tugas, Submission
- Top 10 file terbesar
- Orphaned files: file di disk yang sudah tidak terkait record DB
- Tombol "Cleanup" → dispatch job untuk hapus orphaned files

**Guru File Manager**:
- List semua file yang diupload oleh guru tersebut
- Filter by tipe (materi, soal, tugas)
- Sort by ukuran, tanggal
- Info: file mana yang masih dipakai (linked ke materi/tugas aktif)
- Hapus file yang tidak terpakai (dengan konfirmasi)

#### Acceptance Criteria

1. ✅ Admin melihat total storage terpakai
2. ✅ Admin melihat breakdown storage per kategori
3. ✅ Admin bisa scan orphaned files (file di disk tanpa record DB)
4. ✅ Admin bisa cleanup orphaned files (dispatch job, tidak blocking)
5. ✅ Guru melihat list semua file yang diupload
6. ✅ Guru bisa filter file by tipe
7. ✅ Guru bisa hapus file yang tidak terpakai lagi (dengan validasi)
8. ✅ Cleanup job berjalan via queue (tidak blocking web request)

#### Edge Cases

- File sedang dipakai → tidak bisa dihapus (validasi: cek relasi ke materials/assignments/questions)
- Storage scan pada folder besar → queue job, jangan scan synchronous
- Permission: guru hanya lihat file milik sendiri
- Symlink storage belum dibuat → `php artisan storage:link` harus jalan

#### Dependencies

- Tidak ada hard dependency

---

## Phase 6 — Security Hardening

### Task 6.1 — Rate Limiting

**Deskripsi**: Tambah rate limiting pada endpoint-endpoint kritis.

**Kompleksitas**: Simple

**Kondisi saat ini**:
- ✅ Login: sudah ada rate limiting via Fortify — 5 attempts/minute per IP+username (di `FortifyServiceProvider.php`)
- ✅ Two-factor: sudah ada rate limiting — 5 attempts/minute
- ❌ Exam endpoints: belum ada rate limiting
- ❌ Bulk operations: belum ada rate limiting
- ❌ API endpoints: belum ada rate limiting

#### Database Changes

Tidak ada.

#### Implementation Plan

**Tambah di `AppServiceProvider` atau `RouteServiceProvider`:**

```php
// Exam save-answers: max 6 per menit per user (auto-save setiap 30 detik)
RateLimiter::for('exam-save', fn (Request $request) =>
    Limit::perMinute(6)->by($request->user()->id)
);

// Exam log-activity: max 30 per menit per user
RateLimiter::for('exam-activity', fn (Request $request) =>
    Limit::perMinute(30)->by($request->user()->id)
);

// Bulk import: max 3 per menit per user
RateLimiter::for('bulk-import', fn (Request $request) =>
    Limit::perMinute(3)->by($request->user()->id)
);

// General API: max 60 per menit per user
RateLimiter::for('api-general', fn (Request $request) =>
    Limit::perMinute(60)->by($request->user()?->id ?: $request->ip())
);
```

**Apply ke routes:**
- `siswa/ujian/{ujian}/save-answers` → `throttle:exam-save`
- `api/exam/log-activity` → `throttle:exam-activity`
- `admin/users/import`, `guru/bank-soal/{bankSoal}/soal/import` → `throttle:bulk-import`

#### Acceptance Criteria

1. ✅ Login: sudah ada (verifikasi masih berfungsi)
2. ✅ save-answers: max 6/menit per user
3. ✅ log-activity: max 30/menit per user
4. ✅ Bulk import: max 3/menit per user
5. ✅ Rate limit response: 429 Too Many Requests dengan pesan jelas
6. ✅ Rate limit headers: `X-RateLimit-Limit`, `X-RateLimit-Remaining`

#### Edge Cases

- Auto-save 30s interval = 2/menit normal. Limit 6/menit memberi buffer untuk retry
- Jangan terlalu ketat di exam endpoints — jangan sampai jawaban siswa gagal tersimpan
- Rate limit per user (bukan per IP) untuk exam — siswa bisa share IP di lab sekolah

#### Dependencies

- Tidak ada

---

### Task 6.2 — Input Sanitization & XSS

**Deskripsi**: Sanitize semua user-generated content, terutama output Tiptap rich text editor.

**Kompleksitas**: Medium

**Kondisi saat ini**:
- Tiptap editor dipakai di: soal, pengumuman, forum, materi (text type)
- ❌ Tidak ada HTML sanitization sebelum simpan ke DB (berdasarkan grep: hanya ada `strip_tags` di beberapa service untuk analisis, bukan sanitization)
- ❌ File upload: validasi mungkin hanya extension, belum MIME type check
- Vue/Inertia: secara default `v-html` TIDAK di-escape — perlu hati-hati

#### Database Changes

Tidak ada.

#### Implementation Plan

**1. HTML Sanitization (Server-side)**

Install package: `mews/purifier` (wrapper HTMLPurifier untuk Laravel)

```bash
composer require mews/purifier
```

Buat config `config/purifier.php` dengan whitelist tags:
```
Allowed tags: p, br, strong, em, u, s, ul, ol, li, h1-h6, blockquote,
              code, pre, a[href], img[src|alt|width|height], table, thead,
              tbody, tr, th, td, span[style]
Stripped: script, iframe, object, embed, form, input, button, style,
          on* attributes (onclick, onerror, etc.)
```

Buat middleware atau trait `SanitizesHtml`:
```php
trait SanitizesHtml
{
    protected function sanitizeHtml(string $html): string
    {
        return Purifier::clean($html);
    }
}
```

Apply pada semua Form Request yang menerima rich text:
- Question content, explanation
- Announcement content
- Forum thread/reply content
- Material text_content
- Assignment description

**2. File Upload Validation**

Buat helper atau Form Request rule:
```php
// Validasi MIME type (bukan hanya extension)
'file' => ['file', 'mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,jpg,png,webp',
            'max:10240', // 10MB
            new ValidMimeType], // Custom rule: cek actual MIME via finfo
```

Custom rule `ValidMimeType`: baca file header (magic bytes), bandingkan dengan extension. Reject jika mismatch (e.g., `.jpg` tapi isinya `.exe`).

**3. Output Escaping**

- Review semua penggunaan `v-html` di Vue components
- Pastikan hanya digunakan untuk content yang sudah di-sanitize (dari Tiptap)
- Untuk non-rich-text content: gunakan `{{ }}` (auto-escaped oleh Vue)

#### Acceptance Criteria

1. ✅ Semua rich text content di-sanitize sebelum disimpan ke DB
2. ✅ Script tags, event handlers (onclick, onerror), dan iframe di-strip
3. ✅ Allowed tags: formatting dasar, list, heading, link, image, table
4. ✅ File upload: validasi MIME type (bukan hanya extension)
5. ✅ File upload: reject executable files (.exe, .bat, .sh, .php)
6. ✅ Semua `v-html` usage di-audit dan hanya dipakai untuk sanitized content
7. ✅ Test: inject `<script>alert('xss')</script>` di rich text → stripped

#### Edge Cases

- Tiptap generates `<p><br></p>` untuk empty lines → jangan strip
- Image inline di soal → `<img src="...">` harus tetap diizinkan tapi `src` hanya dari domain sendiri
- `<a href="javascript:...">` → strip javascript: protocol
- Content existing di DB yang belum di-sanitize → migration/job untuk batch sanitize? (opsional, tergantung data)

#### Dependencies

- Tidak ada hard dependency
- Idealnya selesai sebelum Task 5.3 (forum) go live

---

### Task 6.3 — Session Security

**Deskripsi**: Hardening session management.

**Kompleksitas**: Simple

**Kondisi saat ini**:
- Session driver: Redis ✅ (dari config/rules)
- Session timeout: default Laravel (120 menit)
- ❌ Belum ada concurrent session limit
- ❌ Belum ada force logout dari semua device (perlu verifikasi)
- Session regeneration setelah login: ✅ Laravel default behavior via Fortify

#### Database Changes

Tidak ada — sessions sudah di Redis.

#### Implementation Plan

**1. Session Timeout (Configurable)**

```env
SESSION_LIFETIME=30   # menit (default Laravel 120, ubah ke 30)
```

Di `config/session.php`:
```php
'lifetime' => env('SESSION_LIFETIME', 30),
'expire_on_close' => false,
```

**2. Force Logout All Devices**

Verifikasi: Fortify sudah punya fitur `logoutOtherDevices` via `PasswordConfirmation`. Jika belum, implement:
- Tambah route `POST /settings/logout-other-devices`
- Gunakan `Auth::logoutOtherDevices($password)` (built-in Laravel)

**3. Concurrent Session Limit (Exam Mode)**

Buat middleware `SingleSessionExam`:
```php
class SingleSessionExam
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $activeAttempt = $user->activeExamAttempt();

        if ($activeAttempt) {
            // Cek apakah session ID saat ini sama dengan yang memulai exam
            $examSessionKey = "exam_session:{$activeAttempt->id}:session_id";
            $storedSessionId = Redis::get($examSessionKey);

            if ($storedSessionId && $storedSessionId !== session()->getId()) {
                // Tolak: login dari device/session berbeda saat ujian
                abort(403, 'Anda sedang mengerjakan ujian di perangkat lain.');
            }
        }

        return $next($request);
    }
}
```

Apply pada exam routes.

**4. Session Regeneration**

- ✅ Sudah default di Fortify setelah login
- Verifikasi: `$request->session()->regenerate()` dipanggil setelah login

#### Acceptance Criteria

1. ✅ Session timeout 30 menit (configurable via .env)
2. ✅ User bisa force logout dari semua device lain
3. ✅ Siswa hanya boleh 1 active session saat mengerjakan ujian
4. ✅ Login dari device baru saat ujian → ditolak dengan pesan jelas
5. ✅ Session ID di-regenerate setelah login (verifikasi)
6. ✅ Expired session → redirect ke login dengan pesan "Session expired"

#### Edge Cases

- Siswa browser crash saat ujian → session baru valid karena exam resume flow sudah ada
- Dua tab di browser yang sama → same session ID, OK
- Guru login dari HP + laptop → tidak dibatasi (hanya siswa saat ujian)
- Redis restart → semua session hilang, user harus login ulang (acceptable)

#### Dependencies

- Tidak ada

---

### Task 6.4 — Exam Security Hardening

**Deskripsi**: Tambahan layer keamanan UI saat siswa mengerjakan ujian.

**Kompleksitas**: Medium

**Kondisi saat ini**:
- ✅ Fullscreen mode: sudah ada (requestFullscreen)
- ✅ Tab switch detection: sudah ada (visibilitychange event)
- ✅ Activity logging: sudah ada (exam_activity_logs)
- ✅ Device fingerprint: sudah ada
- ❌ Belum ada CSS-based anti-screenshot
- ❌ Belum ada right-click/copy/paste prevention
- ❌ Belum ada DevTools detection

#### Database Changes

Tidak ada.

#### Implementation Plan

**1. CSS Anti-Screenshot / Anti-Copy**

Di `ExamInterface.vue`:
```css
.exam-container {
  user-select: none;
  -webkit-user-select: none;
  -webkit-touch-callout: none;
  -webkit-print-color-adjust: exact;
}

@media print {
  .exam-container {
    display: none !important;
  }
  body::after {
    content: "Pencetakan tidak diizinkan saat ujian.";
    display: block;
    font-size: 2rem;
    text-align: center;
    padding: 2rem;
  }
}
```

**2. Keyboard & Context Menu Prevention**

```typescript
// composables/useExamSecurity.ts
export function useExamSecurity() {
  const preventActions = (e: KeyboardEvent) => {
    // Prevent Ctrl+C, Ctrl+V, Ctrl+P, Ctrl+S, PrintScreen
    if (e.ctrlKey && ['c','v','p','s','a','u'].includes(e.key.toLowerCase())) {
      e.preventDefault();
      logActivity('copy_attempt');
    }
    if (e.key === 'PrintScreen') {
      e.preventDefault();
      logActivity('screenshot_attempt');
    }
    // Prevent F12 (DevTools)
    if (e.key === 'F12') {
      e.preventDefault();
      logActivity('devtools_attempt');
    }
    // Prevent Ctrl+Shift+I (DevTools)
    if (e.ctrlKey && e.shiftKey && e.key === 'I') {
      e.preventDefault();
      logActivity('devtools_attempt');
    }
  };

  const preventContextMenu = (e: MouseEvent) => {
    e.preventDefault();
    logActivity('right_click');
  };

  // ...lifecycle hooks
}
```

**3. DevTools Detection**

```typescript
// Detect DevTools via timing (debugger statement) — not 100% reliable tapi cukup sebagai deterrent
function detectDevTools() {
  const threshold = 160;
  const widthDiff = window.outerWidth - window.innerWidth > threshold;
  const heightDiff = window.outerHeight - window.innerHeight > threshold;

  if (widthDiff || heightDiff) {
    logActivity('devtools_open');
  }
}

setInterval(detectDevTools, 3000);
```

> Note: DevTools detection tidak sempurna (bisa di-bypass). Ini hanya sebagai deterrent + logging. Bukan hard block.

**4. Log Security Events**

Tambah event types di `ExamActivityEventType` enum:
```php
case ScreenshotAttempt = 'screenshot_attempt';
case DevtoolsAttempt = 'devtools_attempt';
case DevtoolsOpen = 'devtools_open';
case PrintAttempt = 'print_attempt';
```

#### Acceptance Criteria

1. ✅ Text di halaman ujian tidak bisa di-select (user-select: none)
2. ✅ Right-click disabled saat ujian, event di-log
3. ✅ Ctrl+C, Ctrl+V, Ctrl+P di-block saat ujian, event di-log
4. ✅ Print (Ctrl+P) di-block, halaman print menampilkan pesan
5. ✅ F12 / Ctrl+Shift+I di-block, event di-log
6. ✅ DevTools detection (size-based) berjalan setiap 3 detik
7. ✅ Semua security events di-log ke exam_activity_logs
8. ✅ Proctor bisa lihat security events di Proctor Dashboard

#### Edge Cases

- Browser extension bisa bypass JavaScript restrictions → acceptable, ini deterrent bukan 100% security
- Siswa pakai external screenshot tool → tidak bisa dicegah via web, tapi DevTools detection membantu
- Disable right-click jangan sampai mengganggu exam UI (e.g., text input di esai harus tetap bisa paste jika diizinkan)
- Esai type: pertimbangkan IZINKAN paste di textarea esai (configurable per exam?)

#### Dependencies

- Tidak ada hard dependency
- Enhancement dari anti-cheat yang sudah ada di Phase 1-2

---

### Task 6.5 — Data Protection

**Deskripsi**: Review dan hardening proteksi data.

**Kompleksitas**: Simple

**Kondisi saat ini**:
- ✅ Password: bcrypt (Laravel default)
- ✅ CSRF: Laravel default (semua POST/PUT/DELETE form punya CSRF token)
- ✅ SQL Injection: Eloquent ORM (parameterized queries)
- ❌ HTTPS enforcement: belum dikonfigurasi
- ❌ CORS: belum di-review
- ❌ CSP headers: belum ada

#### Database Changes

Tidak ada.

#### Implementation Plan

**1. HTTPS Enforcement**

```php
// app/Providers/AppServiceProvider.php
public function boot(): void
{
    if ($this->app->environment('production')) {
        URL::forceScheme('https');
    }
}
```

```env
# .env (production)
APP_URL=https://cbt.smkbinamandiri.sch.id
SESSION_SECURE_COOKIE=true
```

**2. CORS Review**

Review `config/cors.php`:
```php
return [
    'paths' => ['api/*'],
    'allowed_origins' => [env('APP_URL')],  // Hanya domain sendiri
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
    'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'X-XSRF-TOKEN'],
    'supports_credentials' => true,
];
```

**3. Security Headers (via Middleware)**

Buat middleware `SecurityHeaders`:
```php
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // CSP — permissive enough for Vite + Inertia
        // In production, tighten nonce/hash based on actual assets
        if (app()->environment('production')) {
            $response->headers->set(
                'Content-Security-Policy',
                "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self' wss:; frame-ancestors 'self';"
            );
        }

        return $response;
    }
}
```

**4. Raw SQL Audit**

Grep codebase untuk:
- `DB::raw(` — pastikan tidak ada user input langsung
- `DB::select(` — pastikan pakai bindings
- `whereRaw(` — pastikan pakai parameter binding

**5. Sensitive Data Review**

- Pastikan `User::$hidden` mencakup: `password`, `remember_token`, `two_factor_secret`, `two_factor_recovery_codes`
- Pastikan API responses tidak bocorkan sensitive fields
- Review Inertia shared data: pastikan tidak share password/secret ke frontend

#### Acceptance Criteria

1. ✅ HTTPS enforced di production (forceScheme + secure cookie)
2. ✅ CORS: hanya izinkan domain sendiri
3. ✅ Security headers: X-Content-Type-Options, X-Frame-Options, X-XSS-Protection, Referrer-Policy
4. ✅ CSP header di production
5. ✅ Tidak ada raw SQL dengan user input tanpa binding
6. ✅ User model `$hidden` mencakup semua sensitive fields
7. ✅ Inertia shared data tidak bocorkan sensitive info

#### Edge Cases

- Vite dev server → CSP perlu izinkan localhost HMR (hanya di development)
- WebSocket (Reverb) → CSP `connect-src` perlu izinkan `wss:`
- Image upload (inline di Tiptap) → CSP `img-src` perlu izinkan `data:` dan `blob:`

#### Dependencies

- Tidak ada

---

### Task 6.6 — Audit & Logging Review

**Deskripsi**: Review dan lengkapi audit trail yang sudah ada dari Phase 4.

**Kompleksitas**: Simple

**Kondisi saat ini**:
- ✅ `AuditLog` model ada dengan: user_id, action, auditable_type/id, old/new values, IP, user_agent
- ✅ `Auditable` trait dipakai di: User, Material, Assignment, Announcement, ExamSession
- ✅ Admin bisa lihat audit log di `/admin/audit-log` dengan filter
- ❌ Login/logout belum pasti di-audit (perlu verifikasi)
- ❌ Grade edits belum pasti di-audit
- ❌ Export audit log belum ada
- ❌ Log retention policy belum ada

#### Database Changes

Tidak ada.

#### Implementation Plan

**1. Verifikasi & Lengkapi Audit Coverage**

Pastikan event berikut di-log:
| Event | Status | Action Needed |
|-------|--------|---------------|
| Login | ❓ Verify | Tambah listener pada `Login` event |
| Logout | ❓ Verify | Tambah listener pada `Logout` event |
| Failed login | ❓ Verify | Tambah listener pada `Failed` event |
| CRUD User | ✅ Via Auditable trait | — |
| CRUD ExamSession | ✅ Via Auditable trait | — |
| Edit nilai (StudentAnswer.score) | ❓ Verify | Tambah Auditable ke StudentAnswer jika belum |
| Proctor override (extend time, terminate) | ❓ Verify | Cek ExamActivityLog |
| Publish hasil ujian | ❓ Verify | Cek audit pada ExamSession.is_results_published |
| CRUD Question | ❓ Verify | Tambah Auditable ke Question jika belum |
| CRUD Material | ✅ Via Auditable trait | — |
| CRUD Assignment | ✅ Via Auditable trait | — |
| CRUD Announcement | ✅ Via Auditable trait | — |
| Backup created | ❓ Verify | — |

**2. Login/Logout Audit**

```php
// EventServiceProvider atau listener
Event::listen(Login::class, function (Login $event) {
    AuditLog::create([
        'user_id' => $event->user->id,
        'action' => 'login',
        'auditable_type' => User::class,
        'auditable_id' => $event->user->id,
        'ip_address' => request()->ip(),
        'user_agent' => request()->userAgent(),
        'description' => "User {$event->user->name} logged in",
    ]);
});
```

**3. Export Audit Log**

- Tambah route `GET /admin/audit-log/export` → export CSV/Excel
- Filter sama dengan tampilan (by user, action, date range)
- Dispatch via queue (bisa besar)

**4. Log Retention Policy**

- Config: `AUDIT_LOG_RETENTION_DAYS=365` (default 1 tahun)
- Scheduled command: `audit:cleanup` — hapus log lebih dari retention period
- Jalankan via scheduler (daily)

```php
// app/Console/Commands/CleanupAuditLogsCommand.php
$days = config('audit.retention_days', 365);
AuditLog::where('created_at', '<', now()->subDays($days))->delete();
```

#### Acceptance Criteria

1. ✅ Login, logout, failed login di-audit
2. ✅ CRUD user, exam session, question, material, assignment, announcement di-audit
3. ✅ Edit nilai (score) di-audit dengan old + new value
4. ✅ Proctor override actions di-audit
5. ✅ Publish/unpublish hasil ujian di-audit
6. ✅ Admin bisa export audit log ke CSV/Excel
7. ✅ Log retention: auto-cleanup setelah 365 hari (configurable)
8. ✅ Scheduled command `audit:cleanup` terdaftar di scheduler

#### Edge Cases

- Audit log export besar (100K+ rows) → queue job + download link via notification
- Retention cleanup jangan menghapus audit log yang sedang di-export
- System actions (scheduler, queue) → user_id null, IP null — handle gracefully

#### Dependencies

- Tidak ada

---

## Phase 7 — UI/UX Polish

> **Catatan**: Phase 7 di-plan secara high-level sekarang. Detail implementation plan dibuat setelah Phase 5 dan 6 selesai, karena fitur baru dari Phase 5 juga perlu di-design.

### Task 7.1 — Design System Audit & Consistency

**Kompleksitas**: Medium

**Scope**:
- Audit semua halaman untuk konsistensi: spacing, typography, color, component usage
- Pastikan semua halaman menggunakan shadcn-vue components secara konsisten
- Standarkan: card styles, table styles, form layouts, button styles
- Buat style guide internal (di docs/) sebagai referensi

**Acceptance Criteria**:
1. ✅ Semua halaman menggunakan shadcn-vue components (tidak ada custom buttons/inputs yang inkonsisten)
2. ✅ Spacing dan typography konsisten (gunakan Tailwind scale: gap-4, text-sm, dll)
3. ✅ Color palette konsisten (primary, destructive, muted, dll dari shadcn theme)
4. ✅ Style guide document dibuat

---

### Task 7.2 — Empty States & Loading States

**Kompleksitas**: Simple

**Scope**:
- Setiap halaman list/tabel: empty state illustration + message + CTA
- Loading states: skeleton loaders untuk data-heavy pages
- Error states: friendly error page (404, 403, 500)

**Acceptance Criteria**:
1. ✅ Semua halaman list/tabel punya empty state yang informatif
2. ✅ Loading skeleton pada: dashboard, tabel data, forum
3. ✅ Custom error pages (404, 403, 500) dengan branding sekolah
4. ✅ Toast/notification untuk success/error actions

---

### Task 7.3 — Mobile Responsiveness Audit

**Kompleksitas**: Medium

**Scope**:
- Audit semua halaman pada viewport: 375px (mobile), 768px (tablet), 1024px+ (desktop)
- Prioritas mobile: beranda publik, siswa dashboard, pengumuman, forum, profil
- Exam interface: tetap desktop-only (requirement)
- Navigasi mobile: hamburger menu, bottom nav, atau drawer

**Acceptance Criteria**:
1. ✅ Semua halaman non-exam responsive pada 375px+
2. ✅ Navigation mobile: sidebar collapsible atau drawer
3. ✅ Tabel data: horizontal scroll atau card view pada mobile
4. ✅ Form: stacked layout pada mobile

---

### Task 7.4 — Dark Mode Polish

**Kompleksitas**: Simple

**Scope**:
- Audit semua halaman dalam dark mode
- Fix: contrast issues, color mismatches, border visibility
- Ensure: charts/graphs readable in dark mode
- Settings: dark mode toggle sudah ada (`settings/Appearance.vue`)

**Acceptance Criteria**:
1. ✅ Semua halaman readable dalam dark mode
2. ✅ Tidak ada text-on-text yang sulit dibaca
3. ✅ Charts dan grafik punya dark mode variant
4. ✅ Images/logos punya dark mode variant jika needed

---

### Task 7.5 — Accessibility (a11y) Basics

**Kompleksitas**: Medium

**Scope**:
- Keyboard navigation: semua interactive elements reachable via Tab
- Focus indicators: visible focus ring pada semua focusable elements
- ARIA labels: pada icon-only buttons, modals, dropdowns
- Color contrast: WCAG AA minimum (4.5:1 untuk text)
- Screen reader: semantic HTML (headings, landmarks, labels)

**Acceptance Criteria**:
1. ✅ Semua halaman navigable via keyboard
2. ✅ Focus indicators visible (Tailwind `focus-visible:ring`)
3. ✅ Icon-only buttons punya `aria-label`
4. ✅ Modals punya proper focus trap dan escape to close
5. ✅ Form inputs punya labels (bukan placeholder-only)

---

### Task 7.6 — Micro-interactions & Feedback

**Kompleksitas**: Simple

**Scope**:
- Button loading states (spinner saat submit)
- Form validation: inline errors, real-time validation
- Toast notifications: konsisten untuk semua CRUD operations
- Page transitions: Inertia progress indicator
- Confirm dialogs: konsisten pattern (shadcn AlertDialog)

**Acceptance Criteria**:
1. ✅ Semua form submit buttons punya loading state
2. ✅ Inline validation errors pada semua form fields
3. ✅ Toast notification setelah setiap CRUD action
4. ✅ Inertia NProgress bar visible saat page navigation
5. ✅ Destructive actions (hapus) selalu pakai confirm dialog

---

### Task 7.7 — Performance & Perceived Speed

**Kompleksitas**: Medium

**Scope**:
- Lazy load components yang berat (charts, Tiptap editor)
- Image optimization: lazy loading, proper sizing
- Inertia partial reloads di mana applicable
- Prefetch links pada navigasi utama

**Acceptance Criteria**:
1. ✅ Tiptap editor lazy-loaded (tidak di-bundle di main chunk)
2. ✅ Chart components lazy-loaded
3. ✅ Images di halaman list pakai `loading="lazy"`
4. ✅ Dashboard pakai Inertia partial reload untuk refresh data
5. ✅ Navigation links pakai Inertia prefetch on hover

---

## Dependency Graph

```
Phase 5:
  5.1 Beranda Publik ─────────────────── (independent)
  5.2 Beranda Login (Dashboard) ──────── depends on 5.1 (shared components)
  5.3 Forum Sekolah-wide ─────────────── (independent)
  5.4 Profil User ────────────────────── (independent)
  5.5 Kalender Akademik ──────────────── (independent)
  5.6 Manajemen File ─────────────────── (independent)

Phase 6 (can start in parallel with Phase 5):
  6.1 Rate Limiting ──────────────────── (independent)
  6.2 Input Sanitization ─────────────── should complete before 5.3 goes live
  6.3 Session Security ───────────────── (independent)
  6.4 Exam Security ──────────────────── (independent)
  6.5 Data Protection ────────────────── (independent)
  6.6 Audit Review ───────────────────── (independent)

Phase 7 (after Phase 5 & 6):
  7.1 Design System ──────────────────── (independent, do first)
  7.2 Empty/Loading States ───────────── after 7.1
  7.3 Mobile Responsiveness ──────────── after 7.1
  7.4 Dark Mode ──────────────────────── after 7.1
  7.5 Accessibility ──────────────────── after 7.1
  7.6 Micro-interactions ─────────────── (independent)
  7.7 Performance ────────────────────── (independent, do last)
```

### Recommended Implementation Order

```
Sprint A (parallelizable):
  ├── 5.1 Beranda Publik
  ├── 6.1 Rate Limiting
  ├── 6.2 Input Sanitization
  └── 6.5 Data Protection

Sprint B (parallelizable):
  ├── 5.2 Dashboard Enhancement
  ├── 5.4 Profil User
  ├── 6.3 Session Security
  └── 6.4 Exam Security

Sprint C (parallelizable):
  ├── 5.3 Forum Sekolah-wide
  ├── 5.5 Kalender Akademik
  └── 6.6 Audit Review

Sprint D:
  └── 5.6 Manajemen File

Sprint E (Phase 7 — sequential):
  ├── 7.1 Design System Audit
  ├── 7.2 Empty/Loading States
  ├── 7.3 Mobile Responsiveness
  ├── 7.4 Dark Mode
  ├── 7.5 Accessibility
  ├── 7.6 Micro-interactions
  └── 7.7 Performance
```

### Complexity Summary

| Task | Complexity | New Tables | New Routes | New Pages |
|------|-----------|------------|------------|-----------|
| 5.1 Beranda Publik | Medium | 0 (1 column add) | 1 | 1 (rewrite) |
| 5.2 Dashboard Enhancement | Complex | 0 | 0 | 0 (enhance 3) |
| 5.3 Forum Sekolah-wide | Medium | 3 | 12 | 4 |
| 5.4 Profil User | Medium | 0 (3 columns add) | 3 | 1 |
| 5.5 Kalender Akademik | Medium | 0 | 3 | 2 |
| 5.6 Manajemen File | Medium | 0 | 5 | 2 |
| 6.1 Rate Limiting | Simple | 0 | 0 | 0 |
| 6.2 Input Sanitization | Medium | 0 | 0 | 0 |
| 6.3 Session Security | Simple | 0 | 1 | 0 |
| 6.4 Exam Security | Medium | 0 | 0 | 0 |
| 6.5 Data Protection | Simple | 0 | 0 | 0 |
| 6.6 Audit Review | Simple | 0 | 1 | 0 |
| 7.1-7.7 UI/UX Polish | Medium | 0 | 0 | 3 (error pages) |
| **TOTAL** | — | **3 new + 4 col adds** | **~26** | **~13** |
