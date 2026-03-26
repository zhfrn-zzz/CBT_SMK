# Phase 8 — Konfigurabilitas & Polish untuk Multi-Sekolah

## Konteks

Sistem LMS + CBT ini akan dijual ke sekolah lain. Saat ini konfigurasi sekolah hardcoded di `.env` dan `config/school.php`. Semua pengaturan harus bisa dikonfigurasi dari admin panel tanpa edit code/file server.

### State Saat Ini

| Aspek | Status |
|-------|--------|
| Config sekolah | `config/school.php` baca dari `.env` — 4 key saja: name, address, logo_path, tagline |
| Settings table | Belum ada |
| Helper `setting()` | Belum ada, belum ada `app/helpers.php` |
| Password siswa saat import | `StudentImport` generate random 8-char, tapi **dibuang** — controller hanya flash "X siswa berhasil diimport" |
| Download kredensial | Tidak ada — plaintext password dari `getResults()` tidak ditampilkan/didownload |
| Kartu peserta ujian | Tidak ada |
| Watermark anti-screenshot | Tidak ada |
| Halaman guru dedicated | Tidak ada — guru dikelola via `/admin/users?role=guru` |
| README.md | Hanya `# CBT_SMK` |
| Exam settings global | Hanya `EXAM_SECURITY_HARDENING` di `.env` — sisanya per-exam di `exam_sessions` |
| DomPDF | Terinstall (v3.1), sudah dipakai di printPdf() dan exportRapor() |
| Maatwebsite Excel | Terinstall, dipakai di StudentImport dan DataExchange |
| shadcn-vue Tabs | Tersedia |

---

## Priority Order & Dependencies

```
Task 8.1 (System Settings)         ← fondasi, harus pertama
  ↓
Task 8.4 (Watermark)               ← butuh setting toggle dari 8.1
Task 8.3 (Cetak Kartu Peserta)     ← butuh setting logo/nama sekolah dari 8.1
  ↓
Task 8.2 (Auto-Generate Password)  ← independen tapi dibutuhkan oleh 8.6
  ↓
Task 8.6 (Download Kredensial)     ← butuh 8.2 selesai
  ↓
Task 8.5 (Halaman Guru)            ← independen
Task 8.7 (README.md)               ← independen, bisa kapan saja
```

### Urutan Implementasi yang Disarankan

1. **Task 8.1** — System Settings (HIGH, fondasi)
2. **Task 8.2** — Auto-Generate Password (HIGH, security)
3. **Task 8.6** — Download Kredensial (HIGH, UX kritis — tanpa ini password hilang)
4. **Task 8.4** — Watermark (MEDIUM)
5. **Task 8.3** — Cetak Kartu Peserta (MEDIUM)
6. **Task 8.5** — Halaman Guru (LOW-MEDIUM)
7. **Task 8.7** — README.md (LOW)

---

## Task 8.1 — Pengaturan Sistem (System Settings)

### Deskripsi

Halaman `/admin/settings` dengan 4 tab: Pengaturan Umum, Tampilan, Ujian, Email. Semua tersimpan di database (tabel `settings` key-value), bukan `.env`. Helper function `setting('key')` tersedia global. Cache di Redis, invalidate saat update.

### Database Changes

**Tabel baru: `settings`**
```
id              BIGINT UNSIGNED PK AUTO_INCREMENT
group           VARCHAR(50) NOT NULL  -- 'general', 'appearance', 'exam', 'email'
key             VARCHAR(100) NOT NULL UNIQUE
value           TEXT NULLABLE
type            VARCHAR(20) DEFAULT 'string'  -- 'string', 'boolean', 'integer', 'json', 'file'
created_at      TIMESTAMP
updated_at      TIMESTAMP

INDEX: (group)
UNIQUE: (key)
```

**Seed data default:**

| group | key | value | type |
|-------|-----|-------|------|
| general | app_name | SMK LMS | string |
| general | school_name | SMK Bina Mandiri | string |
| general | school_address | (kosong) | string |
| general | school_phone | (kosong) | string |
| general | school_email | (kosong) | string |
| general | school_website | (kosong) | string |
| general | school_tagline | Mencetak Generasi Mandiri dan Berkarakter | string |
| appearance | logo_path | images/logo.png | file |
| appearance | logo_small_path | (kosong) | file |
| appearance | primary_color | #2563eb | string |
| appearance | secondary_color | #64748b | string |
| appearance | login_bg_type | color | string |
| appearance | login_bg_value | #f8fafc | string |
| appearance | footer_text | (kosong) | string |
| appearance | show_powered_by | true | boolean |
| exam | default_duration_minutes | 60 | integer |
| exam | auto_submit_on_timeout | true | boolean |
| exam | show_result_after_submit | false | boolean |
| exam | anti_cheat_enabled | true | boolean |
| exam | max_tab_switches_default | 3 | integer |
| exam | allow_mobile_exam | false | boolean |
| exam | device_lock_default | true | boolean |
| exam | watermark_enabled | true | boolean |
| email | smtp_host | (kosong) | string |
| email | smtp_port | 587 | integer |
| email | smtp_username | (kosong) | string |
| email | smtp_password | (kosong) | string |
| email | smtp_encryption | tls | string |

### Backend

**Model: `app/Models/Setting.php`**
- fillable: `group`, `key`, `value`, `type`
- Cast `value` berdasarkan `type` field (accessor)
- Scope `byGroup($group)`, `byKey($key)`
- Static method `get($key, $default)`, `set($key, $value)`, `getByGroup($group)`

**Helper: `app/helpers.php`**
```php
function setting(string $key, mixed $default = null): mixed
```
- Baca dari Redis cache `settings:all` (hash map)
- Fallback ke database jika cache miss
- Return `$default` jika key tidak ditemukan
- Register di `composer.json` autoload.files

**Service: `app/Services/SettingService.php`**
- `getAll(): array` — ambil semua settings, cache di Redis
- `get(string $key, mixed $default): mixed`
- `set(string $key, mixed $value): void` — update DB + invalidate cache
- `setMany(array $settings): void` — bulk update (untuk form save)
- `getByGroup(string $group): array`
- `clearCache(): void` — flush Redis key `settings:all`
- `handleFileUpload(string $key, UploadedFile $file): string` — simpan file, return path
- `deleteFile(string $key): void`

**Cache strategy:**
- Redis key: `settings:all` (hash, TTL: 24 jam)
- Invalidate seluruh key saat ada update (simple, settings jarang berubah)
- Warmup cache di `AppServiceProvider::boot()` atau lazy-load saat pertama akses

**Controller: `app/Http/Controllers/Admin/SettingController.php`**
- `index()` — return Inertia page dengan semua settings grouped by tab
- `updateGeneral(Request $request)` — validate & save general settings
- `updateAppearance(Request $request)` — validate & save appearance + handle file uploads
- `updateExam(Request $request)` — validate & save exam defaults
- `updateEmail(Request $request)` — validate & save SMTP config
- `testEmail(Request $request)` — kirim test email, return success/error

**Form Requests:**
- `UpdateGeneralSettingsRequest` — string validasi, max length
- `UpdateAppearanceSettingsRequest` — file validation (image, max 2MB), hex color format
- `UpdateExamSettingsRequest` — integer ranges, boolean
- `UpdateEmailSettingsRequest` — SMTP field validation

**Middleware/Provider:**
- `ShareSettingsMiddleware` atau di `HandleInertiaRequests` — share `setting('app_name')`, `setting('logo_path')`, dan settings yang dibutuhkan frontend ke setiap Inertia response via `Inertia::share()`
- Alternatif: share minimal settings saja (app_name, logo, colors) untuk performa

**Migrasi penggunaan `config('school.*')`:**
- Cari semua penggunaan `config('school.name')`, `config('school.address')`, dll
- Ganti dengan `setting('school_name')`, `setting('school_address')`, dll
- Cari di: controllers, views/pages, blade templates (jika ada), PDF views
- File `config/school.php` tetap ada sebagai fallback default values untuk seeder

### Frontend

**Page: `resources/js/pages/Admin/Settings/Index.vue`**
- Gunakan shadcn-vue `Tabs` component (sudah tersedia)
- 4 tab: Umum, Tampilan, Ujian, Email
- Setiap tab = form terpisah dengan submit sendiri (partial save)
- Flash message on save success

**Tab Umum:**
- Input fields: app_name, school_name, school_address, school_phone, school_email, school_website, school_tagline
- Semua `<Input>` dengan `<Label>`
- Validasi client-side: required untuk nama, email format, url format

**Tab Tampilan:**
- File upload: logo (accept image/*, preview, max 2MB)
- File upload: logo kecil/favicon (accept image/*, preview)
- Color picker: primary_color — `<Input type="color">` + hex text input
- Color picker: secondary_color
- Radio/Select: login_bg_type (color/image)
- Conditional: login_bg_value — color picker atau file upload
- Input: footer_text
- Toggle: show_powered_by
- Live preview panel (optional, bisa versi 2)

**Tab Ujian:**
- Number input: default_duration_minutes (min: 1, max: 480)
- Toggle: auto_submit_on_timeout
- Toggle: show_result_after_submit
- Toggle: anti_cheat_enabled
- Number input: max_tab_switches_default (min: 1, max: 99)
- Toggle: allow_mobile_exam
- Toggle: device_lock_default
- Toggle: watermark_enabled

**Tab Email (prioritas rendah):**
- Input: smtp_host, smtp_port, smtp_username
- Password input: smtp_password (masked)
- Select: smtp_encryption (none/tls/ssl)
- Button: "Kirim Email Test" — hit endpoint, show success/error toast

**CSS Variable Dinamis:**
- Inject `--primary-color` dan `--secondary-color` dari settings ke `<html>` style
- Bisa via `HandleInertiaRequests` share ke frontend, lalu set di App.vue `onMounted`
- Atau via middleware yang inject inline `<style>` (tergantung pendekatan)

### Routes

```
GET    /admin/settings                    → SettingController@index
PUT    /admin/settings/general            → SettingController@updateGeneral
PUT    /admin/settings/appearance         → SettingController@updateAppearance
PUT    /admin/settings/exam               → SettingController@updateExam
PUT    /admin/settings/email              → SettingController@updateEmail
POST   /admin/settings/test-email         → SettingController@testEmail
```

### TypeScript Types

```typescript
// resources/js/types/settings.ts
interface Setting {
  id: number;
  group: string;
  key: string;
  value: string | null;
  type: 'string' | 'boolean' | 'integer' | 'json' | 'file';
}

interface SettingsPageProps {
  settings: {
    general: Record<string, string | null>;
    appearance: Record<string, string | null>;
    exam: Record<string, string | null>;
    email: Record<string, string | null>;
  };
}
```

### Edge Cases

- Logo upload: validasi dimensi minimum (misal 100x100), format (png/jpg/svg), max 2MB
- Warna hex: validasi format `#RRGGBB` (6 digit)
- SMTP password: jangan return ke frontend saat GET (mask dengan `********`), hanya simpan saat ada value baru
- Concurrent admin edit: last-write-wins (acceptable untuk settings)
- Cache race condition: gunakan `Cache::forget()` + `Cache::put()` atomic, bukan manual delete + rebuild
- Migration: seeder harus idempotent (`firstOrCreate` per key)
- Fallback: jika Redis down, baca langsung dari DB (sudah handled oleh `setting()` helper)
- Default values: jika key belum ada di DB, helper return default dari parameter kedua

### Acceptance Criteria

1. Halaman `/admin/settings` accessible oleh admin, menampilkan 4 tab
2. Setiap tab bisa disimpan independen
3. Helper `setting('school_name')` return value dari database
4. Semua penggunaan `config('school.*')` sudah diganti dengan `setting()`
5. Settings di-cache di Redis, invalidate otomatis saat update
6. Upload logo berfungsi, file tersimpan di `storage/app/public/settings/`
7. Color picker update CSS variable di frontend
8. Test email button berfungsi (kirim ke alamat test)
9. Seeder mengisi default values
10. Feature test: CRUD settings, cache invalidation, helper function

### Estimated Complexity: **HIGH**

---

## Task 8.2 — Auto-Generate Password Siswa

### Deskripsi

Saat admin create siswa manual atau bulk import, password auto-generated dan ditampilkan sekali. Admin/guru bisa reset password siswa individual.

### State Saat Ini

- `StudentImport.php` sudah generate `Str::random(8)` jika password tidak disediakan
- `getResults()` sudah return array `[name, username, password]` (plaintext)
- **TAPI**: `UserImportController@import` hanya flash count, **tidak menampilkan/menyimpan** results
- Manual create (`UserController@store`): admin harus isi password manual
- Tidak ada fitur reset password per-siswa

### Database Changes

**Tidak ada perubahan schema.** Password tetap di-hash di kolom `password` tabel `users`.

**Tambahan opsional:** Temporary credential storage di session/cache saja (tidak di DB).

### Backend

**Modifikasi `UserController@store`:**
- Jika role = siswa dan password field kosong:
  - Auto-generate: `Str::random(8)`
  - Hash dan simpan ke user
  - Flash password plaintext ke session: `session()->flash('generated_password', $password)`
  - Return Inertia redirect with flash data
- Jika password diisi manual: gunakan yang diisi (behavior existing)

**Modifikasi `UserImportController@import`:**
- Setelah import sukses, simpan results (name, username, password) ke session atau cache
  - `session()->put('import_credentials', $import->getResults())`
  - Redirect ke halaman users index dengan flash `show_credentials: true`
  - Credentials tersedia untuk download (lihat Task 8.6)
- Credentials HANYA tersedia sekali, auto-expire setelah didownload atau setelah session expire

**Endpoint baru: Reset Password Siswa**
- `POST /admin/users/{user}/reset-password`
- Generate password baru: `Str::random(8)`
- Hash dan update user password
- Return JSON response dengan password plaintext (one-time display)
- Audit log: "Password reset untuk {user.name} oleh {admin.name}"
- Hanya bisa reset siswa (bukan admin/guru sendiri)

**Controller method:**
```php
public function resetPassword(User $user): JsonResponse
{
    abort_unless($user->isSiswa(), 403);
    $password = Str::random(8);
    $user->update(['password' => Hash::make($password)]);
    AuditService::log('password_reset', $user, ...);
    return response()->json(['password' => $password]);
}
```

### Frontend

**Modifikasi `Create.vue` (manual create siswa):**
- Jika role = siswa: password field optional (placeholder: "Kosongkan untuk auto-generate")
- Setelah submit sukses, jika `flash.generated_password` ada:
  - Tampilkan modal/dialog: "Siswa berhasil dibuat"
  - Tampilkan: Nama, Username/NIS, Password (large font, selectable text)
  - Tombol "Salin Password" (copy to clipboard)
  - Warning: "Password ini hanya ditampilkan sekali. Pastikan dicatat."
  - Tombol "Tutup" dismiss modal

**Modifikasi `Index.vue` (user list):**
- Setelah bulk import redirect kembali ke index:
  - Jika `flash.show_credentials` = true, tampilkan credential modal/section
  - Tampilkan tabel: NIS, Nama, Password
  - Tombol "Download Kredensial" (→ Task 8.6)
  - Tombol "Salin Semua" (copy as text)
  - Warning: "Data ini hanya tersedia sekali."

**Tombol Reset Password di row aksi (Index.vue / Edit.vue):**
- Hanya tampil untuk user dengan role siswa
- Klik → confirmation dialog: "Reset password untuk {nama}?"
- Setelah OK → hit endpoint → tampilkan modal dengan password baru
- Modal: nama, NIS, password baru, tombol copy, warning satu kali

### Routes

```
POST   /admin/users/{user}/reset-password   → UserController@resetPassword
```

### Edge Cases

- Password complexity: 8 karakter, campuran huruf+angka (gunakan `Str::random(8)` yang sudah alphanumeric)
- Jangan allow reset password untuk admin atau guru (scope: siswa only — atau bisa diperluas ke guru juga? Rekomendasi: allow untuk guru juga, berguna)
- Rate limiting: throttle reset password endpoint (max 10 per menit per admin)
- Audit trail: log setiap password reset
- Concurrent reset: idempotent, password terakhir yang menang
- Flash data: auto-expire setelah 1 request (Laravel default untuk flash)
- Import credentials in session: set max TTL 30 menit, auto-cleanup

### Acceptance Criteria

1. Create siswa manual tanpa password → auto-generate, tampilkan sekali
2. Create siswa manual dengan password → gunakan password yang diisi
3. Bulk import → semua password ter-generate, tersedia di session
4. Reset password siswa → generate baru, tampilkan modal
5. Password plaintext TIDAK tersimpan di database (hanya hash)
6. Audit log tercatat untuk setiap password reset
7. Feature test: auto-generate, reset endpoint, session storage

### Estimated Complexity: **MEDIUM**

---

## Task 8.3 — Cetak Kartu Peserta Ujian (PDF)

### Deskripsi

Per ujian, guru bisa cetak kartu peserta berisi identitas siswa. 4-6 kartu per halaman A4. Pakai DomPDF yang sudah terinstall.

### Database Changes

**Tidak ada.** Semua data sudah tersedia di tabel existing (`exam_sessions`, `users`, `classrooms`).

### Backend

**Controller: `app/Http/Controllers/Guru/ExamSessionController.php`**

Tambah method `printParticipantCards(ExamSession $examSession)`:
- Load exam session with classrooms, students (via classrooms.students pivot)
- Collect data per siswa: nama, NIS (username), kelas, foto (photo_path), jurusan
- Load school settings: `setting('school_name')`, `setting('logo_path')`
- Render blade view: `resources/views/pdf/participant-cards.blade.php`
- Generate PDF via DomPDF, orientation portrait, paper A4
- Return download response: `kartu-peserta-{exam_name}-{date}.pdf`

**Blade View: `resources/views/pdf/participant-cards.blade.php`**

Layout:
```
┌─────────────────────────────────────────┐
│ [Logo] Nama Sekolah                     │
│ Kartu Peserta Ujian: {nama_ujian}       │
│─────────────────────────────────────────│
│ ┌─────────┐ ┌─────────┐ ┌─────────┐   │
│ │ [Foto]  │ │ [Foto]  │ │ [Foto]  │   │
│ │ Nama    │ │ Nama    │ │ Nama    │   │
│ │ NIS     │ │ NIS     │ │ Nama    │   │
│ │ Kelas   │ │ Kelas   │ │ Kelas   │   │
│ │ Tgl     │ │ Tgl     │ │ Tgl     │   │
│ └─────────┘ └─────────┘ └─────────┘   │
│ ┌─────────┐ ┌─────────┐ ┌─────────┐   │
│ │  ...    │ │  ...    │ │  ...    │   │
│ └─────────┘ └─────────┘ └─────────┘   │
└─────────────────────────────────────────┘
```

Setiap kartu berisi:
- Logo sekolah (dari settings, fallback ke default)
- Nama sekolah (dari settings)
- Nama ujian
- Nama lengkap siswa
- NIS (username)
- Kelas + Jurusan
- Foto siswa (jika ada, placeholder jika tidak)
- Tanggal ujian (starts_at formatted)
- Token ujian
- Ruangan: opsional (bisa dari classroom name)

Grid: 2 kolom × 3 baris = 6 kartu per halaman (atau 2×2 = 4 jika perlu lebih lega)

**Styling:**
- Pure CSS (inline styles untuk DomPDF compatibility)
- Border dashed untuk garis potong
- Font size compact tapi readable (10-11pt)
- Foto: 2×3cm placeholder box

### Frontend

**Modifikasi `Guru/Ujian/Show.vue`:**
- Tambah tombol "Cetak Kartu Peserta" di action bar (di samping tombol existing seperti "Cetak Soal")
- Tombol ini link ke route cetak PDF (buka di tab baru): `<a :href="route" target="_blank">`
- Disable jika exam belum punya classroom assignment

### Routes

```
GET    /guru/ujian/{ujian}/print-cards   → ExamSessionController@printParticipantCards
```

### Edge Cases

- Exam tanpa classroom: tampilkan pesan error "Belum ada kelas yang ditugaskan"
- Siswa tanpa foto: tampilkan placeholder box dengan inisial
- Jumlah siswa banyak (500+): DomPDF bisa lambat — pertimbangkan chunking atau queue job untuk PDF besar (>100 siswa)
- Logo tidak ditemukan: skip logo, hanya tampilkan nama sekolah
- Token security: kartu berisi token ujian — warn guru bahwa kartu harus dijaga
- DomPDF memory: set `DOMPDF_CHROOT` dan memory limit adequate di config
- Encoding: pastikan UTF-8 untuk nama Indonesia

### Acceptance Criteria

1. Tombol "Cetak Kartu Peserta" muncul di halaman detail ujian
2. Klik generate PDF download
3. PDF berisi kartu per siswa, 6 per halaman
4. Kartu menampilkan: logo, nama sekolah, nama ujian, nama siswa, NIS, kelas, tanggal
5. Foto siswa muncul jika ada
6. PDF readable dan bisa dicetak dengan baik
7. Feature test: endpoint return PDF response

### Estimated Complexity: **MEDIUM**

---

## Task 8.4 — Watermark Nama Siswa di Exam Interface

### Deskripsi

CSS overlay di ExamInterface.vue. Teks nama lengkap siswa, diagonal, repeated, opacity rendah (3-5%). Anti-screenshot. Configurable on/off dari settings.

### Database Changes

**Tidak ada.** Menggunakan `setting('watermark_enabled')` dari Task 8.1.

### Backend

**Modifikasi `Siswa/ExamController@exam`:**
- Sertakan `watermark_enabled` di exam payload (dari `setting('watermark_enabled')`)
- Sertakan `student_name` di payload (sudah ada via auth user)

### Frontend

**Komponen baru: `resources/js/Components/Exam/WatermarkOverlay.vue`**

```vue
<template>
  <div v-if="enabled" class="watermark-overlay" aria-hidden="true">
    <!-- Repeated text pattern -->
  </div>
</template>
```

Implementasi CSS:
- `position: fixed; inset: 0; z-index: 9999`
- `pointer-events: none` — tidak mengganggu klik/interaksi
- `user-select: none` — tidak bisa di-select
- `opacity: 0.03` sampai `0.05` (sangat tipis)
- Teks diagonal: `transform: rotate(-30deg)` atau `-45deg`
- Repeated pattern: CSS `background-image` dengan SVG text, atau generate div grid
- Font size: ~14-16px
- Warna: `#000` dengan opacity via `rgba(0,0,0,0.03)`
- Pattern: nama siswa + NIS, repeated setiap ~200px horizontal & vertical
- `print` media query: tetap tampil saat print/screenshot (atau bahkan lebih gelap)

**Pendekatan teknis (pilih salah satu):**

1. **CSS background-image SVG** (rekomendasi):
   - Generate SVG data URI yang berisi teks nama
   - Set sebagai `background-image` dengan `background-repeat: repeat`
   - Performant, tidak buat banyak DOM nodes
   - Contoh: `background-image: url("data:image/svg+xml,...")`

2. **Canvas-based**: render text ke canvas, convert ke data URL, set as background
   - Lebih flexible tapi lebih complex

3. **DOM grid**: buat grid of `<span>` elements
   - Simple tapi banyak DOM nodes, bisa impact performance

Rekomendasi: **opsi 1 (SVG data URI)**. Paling ringan dan clean.

**Integrasi di `ExamInterface.vue`:**
- Import `WatermarkOverlay` component
- Pass props: `enabled` (dari exam payload), `studentName` (dari auth user)
- Render di top-level template, setelah semua konten

**Anti-removal measures:**
- Component render di level tinggi, sulit di-inspect dan remove
- Jangan beri class name yang obvious (hindari `.watermark`)
- CSS `!important` pada pointer-events dan opacity
- Re-create watermark jika DOM mutation detected (opsional, advanced)

### Edge Cases

- Nama panjang: truncate ke ~30 karakter untuk pattern
- Karakter spesial di nama: escape untuk SVG compatibility
- Mobile: tetap render tapi mungkin lebih subtle (opacity 0.02)
- Dark mode: watermark color adjust (putih di dark mode?)
- Performance: SVG background approach minimal impact, tapi test di device low-end
- Setting off: completely hide, jangan render DOM sama sekali (v-if, bukan v-show)
- Print: buat watermark lebih visible di `@media print` (opacity 0.08)

### Acceptance Criteria

1. Watermark muncul di ExamInterface saat `watermark_enabled = true`
2. Teks = nama lengkap siswa, diagonal, repeated
3. Opacity rendah, tidak mengganggu readability soal
4. Tidak bisa di-select (user-select: none)
5. Tidak menghalangi interaksi (pointer-events: none)
6. Configurable on/off dari admin settings (Tab Ujian)
7. Screenshot mengandung watermark yang bisa identify siswa
8. Feature test: watermark prop passed correctly

### Estimated Complexity: **LOW-MEDIUM**

---

## Task 8.5 — Halaman Data Guru Dedicated

### Audit & Keputusan

**Status saat ini:** Guru dikelola via halaman Users yang sama (`/admin/users`) dengan filter role. Ketika admin pilih role=guru, kolom tabel berubah menampilkan "Mengajar" (badges classroom·subject).

**Pertimbangan:**

| Opsi | Pro | Kontra |
|------|-----|--------|
| Filter di Users (saat ini) | Satu tempat kelola semua user, less code | UX kurang — guru punya kebutuhan spesifik (teaching assignments), form Create harus conditional |
| Halaman dedicated `/admin/guru` | UX lebih fokus, form lebih clean, bisa tambah fitur spesifik guru | Duplikasi beberapa logic, maintenance 2 halaman |

**Rekomendasi: Halaman dedicated `/admin/guru`.**

Alasan:
1. Guru punya relasi kompleks (teaching assignments) yang butuh UI khusus
2. Edit guru saat ini TIDAK bisa edit teaching assignments — ini harus diperbaiki
3. Import guru butuh format Excel berbeda dari siswa
4. Fitur masa depan: jadwal mengajar, beban kerja, dll
5. Buyer (sekolah lain) expect dedicated menu item untuk "Data Guru"
6. Halaman Users tetap bisa menampilkan semua role, tapi CRUD guru redirect ke dedicated page

### Database Changes

**Tidak ada.** Guru sudah pakai tabel `users` dengan role=guru dan relasi `teaching_assignments`.

**Opsional:** Tambah kolom `nip` di tabel `users` jika ingin pisahkan dari `username`. Tapi saat ini NIP sudah disimpan sebagai `username`, jadi **tidak perlu**.

### Backend

**Controller: `app/Http/Controllers/Admin/GuruController.php`**

- `index(Request $request)`:
  - Query users where role=guru
  - Eager load: teachingAssignments.classroom, teachingAssignments.subject
  - Search: nama, username/NIP, email
  - Filter: subject_id, department_id
  - Pagination: 15 per page
  - Return Inertia page

- `create()`:
  - Return form data: subjects, classrooms (grouped by department), academic years
  - Form khusus guru (tanpa conditional role switching)

- `store(GuruRequest $request)`:
  - Create user dengan `role: guru` (hardcoded, bukan dari form)
  - Assign teaching assignments (multiple classroom+subject pairs)
  - Auto-generate password (opsional, mengikuti pattern Task 8.2)

- `edit(User $user)`:
  - Load user dengan teaching assignments
  - **Perbaikan dari saat ini:** tampilkan dan bisa edit teaching assignments

- `update(GuruRequest $request, User $user)`:
  - Update user data
  - **Sync teaching assignments** — delete yang dihapus, create yang baru (pakai `sync` logic)

- `destroy(User $user)`:
  - Cek tidak ada exam session aktif yang dimiliki guru ini
  - Soft warning jika guru punya bank soal

- `resetPassword(User $user)`:
  - Sama seperti Task 8.2 tapi untuk guru

**Import guru:**
- `GuruImportController@import`
- Import class: `App\Imports\GuruImport`
- Format Excel: NIP, Nama, Email, Telepon, Mapel (kode/nama), Kelas (opsional)
- Auto-assign teaching berdasarkan mapel + kelas jika disediakan

**Form Request: `GuruRequest`**
```php
'name' => ['required', 'string', 'max:255'],
'username' => ['required', 'string', 'max:255', Rule::unique('users')->ignore($id)],  // NIP
'email' => ['nullable', 'email', Rule::unique('users')->ignore($id)],
'phone' => ['nullable', 'string', 'max:20'],
'password' => [$isCreate ? 'nullable' : 'nullable', 'string', Password::defaults()],
'is_active' => ['boolean'],
'teachings' => ['nullable', 'array'],
'teachings.*.classroom_id' => ['required_with:teachings', 'exists:classrooms,id'],
'teachings.*.subject_id' => ['required_with:teachings', 'exists:subjects,id'],
```

### Frontend

**Pages:**
- `resources/js/pages/Admin/Guru/Index.vue` — tabel guru
- `resources/js/pages/Admin/Guru/Create.vue` — form create guru
- `resources/js/pages/Admin/Guru/Edit.vue` — form edit guru (dengan teaching assignments)

**Index.vue:**
- Kolom: Nama, NIP, Email, Telepon, Mapel yang Diampu, Kelas yang Diampu, Status, Aksi
- Mapel: badges per subject
- Kelas: badges per classroom
- Search + filter (by mapel, by department)
- Aksi: Edit, Reset Password, Hapus
- Tombol: Tambah Guru, Import Guru
- Import: upload Excel, download template

**Create.vue:**
- Nama (required)
- NIP (required, unique)
- Email (optional)
- Telepon (optional)
- Password (optional, "kosongkan untuk auto-generate")
- Status aktif (toggle)
- Teaching Assignments (dynamic array):
  - Per baris: Dropdown Kelas + Dropdown Mapel
  - Filter kelas by tahun ajaran dan jurusan (cascading)
  - Tombol + Tambah Mengajar
  - Tombol - Hapus per baris

**Edit.vue:**
- Same as Create tapi pre-populated
- **Teaching assignments bisa diubah** (perbaikan dari saat ini)
- Password opsional (kosongkan = tidak ubah)

### Routes

```
GET     /admin/guru                    → GuruController@index
GET     /admin/guru/create             → GuruController@create
POST    /admin/guru                    → GuruController@store
GET     /admin/guru/{guru}/edit        → GuruController@edit
PUT     /admin/guru/{guru}             → GuruController@update
DELETE  /admin/guru/{guru}             → GuruController@destroy
POST    /admin/guru/{guru}/reset-password → GuruController@resetPassword
POST    /admin/guru/import             → GuruImportController@import
GET     /admin/guru/import/template    → GuruImportController@template
```

### Navigasi

- Tambah menu item "Data Guru" di sidebar admin (di bawah "Users" atau sebagai sibling)
- Icon: `Users` atau `GraduationCap`
- Halaman Users (`/admin/users`): tetap tampilkan semua role, tapi link "Lihat Detail" untuk guru redirect ke `/admin/guru/{id}/edit`

### Edge Cases

- Guru tanpa teaching assignment: boleh (guru baru belum ditugaskan)
- Teaching assignment duplikat (classroom+subject sama): prevent di backend validation
- Hapus guru yang punya exam session aktif: block dengan pesan error
- Hapus guru yang punya bank soal: soft warning, confirm dulu
- Import guru: NIP duplikat → skip atau error per-row
- Guru yang juga admin: tidak didukung (role single, bukan multi-role)

### Acceptance Criteria

1. Halaman `/admin/guru` menampilkan daftar guru dengan data lengkap
2. CRUD guru berfungsi (create, edit with teaching assignments, delete)
3. Teaching assignments bisa ditambah/hapus di form edit
4. Import guru dari Excel berfungsi
5. Reset password guru berfungsi
6. Menu sidebar menampilkan link "Data Guru"
7. Feature test: CRUD, import, reset password

### Estimated Complexity: **MEDIUM-HIGH**

---

## Task 8.6 — Download Kredensial Siswa

### Deskripsi

Setelah bulk import atau reset password, admin bisa download kredensial dalam format PDF atau Excel. Password plaintext HANYA tersedia saat generate/reset.

### Dependensi

- **Task 8.2** harus selesai (auto-generate password & session storage)
- **Task 8.1** untuk logo/nama sekolah di PDF header

### Database Changes

**Tidak ada.** Kredensial disimpan sementara di session/cache.

### Backend

**Pendekatan penyimpanan sementara:**
- Setelah import/reset, simpan credentials di cache (bukan session — session terlalu volatile)
- Cache key: `credentials:{admin_id}:{timestamp}` atau UUID
- TTL: 30 menit
- Data: array of `[name, username, password]`
- Setelah download, hapus dari cache (one-time download)

**Controller: Tambah method di `UserImportController` atau buat `CredentialController`**

Rekomendasi: **`CredentialController`** terpisah karena dipakai dari multiple context.

`app/Http/Controllers/Admin/CredentialController.php`:

- `downloadPdf(string $credentialKey)`:
  - Ambil credentials dari cache by key
  - Abort 404 jika expired/tidak ada
  - Render blade view: `resources/views/pdf/student-credentials.blade.php`
  - Generate PDF via DomPDF
  - Hapus cache key setelah generate (one-time)
  - Return download: `kredensial-siswa-{date}.pdf`

- `downloadExcel(string $credentialKey)`:
  - Ambil credentials dari cache
  - Generate Excel via Maatwebsite: kolom NIS, Nama, Password
  - Hapus cache key
  - Return download: `kredensial-siswa-{date}.xlsx`

**PDF Layout:**
```
┌─────────────────────────────────────────┐
│ [Logo] Nama Sekolah                     │
│ Daftar Kredensial Siswa                 │
│ Tanggal: 26 Maret 2026                 │
│─────────────────────────────────────────│
│ No │ NIS      │ Nama            │ Password │
│ 1  │ 10001    │ Ahmad Fauzi     │ xK9mL2pQ │
│ 2  │ 10002    │ Budi Santoso    │ aB3nR7tY │
│ ...│ ...      │ ...             │ ...      │
│─────────────────────────────────────────│
│ PERHATIAN: Dokumen ini bersifat rahasia │
│ Simpan dengan aman. Jangan disebarkan.  │
└─────────────────────────────────────────┘
```

**Modifikasi `UserImportController@import`:**
- Setelah import sukses:
  - Generate credential key (UUID)
  - Simpan results ke cache: `Cache::put("credentials:{$key}", $results, 1800)`
  - Flash credential key ke session: `session()->flash('credential_key', $key)`
  - Redirect ke index

**Modifikasi `UserController@resetPassword` (dari Task 8.2):**
- Untuk individual reset: tidak perlu download (modal display sudah cukup)
- Opsional: bulk reset → generate credential key, same flow

### Frontend

**Modifikasi `Index.vue`:**
- Setelah import redirect, jika `flash.credential_key` ada:
  - Tampilkan alert/banner: "Import berhasil. Download kredensial sebelum meninggalkan halaman."
  - Tombol "Download PDF" → `GET /admin/credentials/{key}/pdf`
  - Tombol "Download Excel" → `GET /admin/credentials/{key}/excel`
  - Timer countdown 30 menit (visual reminder)
  - Warning: "Kredensial akan expired dalam {waktu}. Download sekarang."

**Modal setelah individual reset (dari Task 8.2):**
- Tetap tampil di modal
- Tambah tombol "Download PDF" untuk single credential (opsional, bisa skip — copy sudah cukup untuk 1 orang)

### Routes

```
GET    /admin/credentials/{key}/pdf      → CredentialController@downloadPdf
GET    /admin/credentials/{key}/excel    → CredentialController@downloadExcel
```

### Edge Cases

- Credential key expired: return 404 dengan pesan "Kredensial sudah expired. Lakukan import ulang."
- Double download attempt: setelah pertama download, key dihapus → 404 pada attempt kedua
  - **Alternatif:** allow multiple download selama TTL belum habis (lebih user-friendly)
  - **Rekomendasi:** allow multiple download, hapus saat TTL expire saja
- Browser back setelah download: credential key masih di flash, tapi cache mungkin sudah dihapus
- Large import (500+ siswa): PDF bisa besar — test DomPDF performance
- Excel lebih ringan untuk jumlah besar — recommend Excel sebagai default
- Security: credential endpoint harus behind auth middleware + admin role check
- Audit log: catat setiap download credential

### Acceptance Criteria

1. Setelah bulk import, tombol download PDF dan Excel muncul
2. Download PDF berisi tabel NIS + Nama + Password dengan header sekolah
3. Download Excel berisi kolom NIS, Nama, Password
4. Kredensial expire setelah 30 menit
5. Password di dokumen = password yang bisa dipakai login
6. Security: hanya admin yang bisa akses endpoint
7. Feature test: download endpoint, cache expiry

### Estimated Complexity: **MEDIUM**

---

## Task 8.7 — README.md Proper

### Deskripsi

README.md professional untuk repo. Bahasa Indonesia. Tanpa emoji berlebihan, tanpa "Made with heart" atau badge berlebihan.

### Struktur README

```markdown
# SMK LMS — Sistem Manajemen Pembelajaran & Ujian Berbasis Komputer

Deskripsi singkat sistem (2-3 kalimat). Target: SMK, 500+ siswa concurrent.

## Fitur Utama

### Learning Management System (LMS)
- Manajemen materi pembelajaran
- Penugasan dan pengumpulan tugas
- Forum diskusi
- Pengumuman
- Presensi online
- Kalender akademik

### Computer Based Test (CBT)
- 7 tipe soal (PG, B/S, Esai, Isian Singkat, Menjodohkan, Ordering, Multiple Answer)
- Bank soal dengan randomisasi
- Auto-grading untuk soal objektif
- Proctor dashboard (monitoring real-time)
- Anti-cheat (tab detection, device lock, fullscreen enforcement)
- Ujian remedial
- Export hasil ke Excel/PDF

### Manajemen Sekolah
- Multi-role: Admin, Guru, Siswa
- Struktur akademik (tahun ajaran, jurusan, kelas, mata pelajaran)
- Import/export data siswa via Excel
- Audit trail

## Tech Stack

(tabel: Layer | Technology | Version — dari CLAUDE.md)

## Requirements

- PHP >= 8.2
- MySQL >= 8.0
- Redis >= 7.0
- Node.js >= 20
- Composer >= 2.x

## Instalasi

### Clone & Setup
(langkah: clone, composer install, npm install, copy env, generate key)

### Database
(langkah: create DB, migrate, seed)

### Redis
(langkah: pastikan Redis running)

### Development Server
(langkah: php artisan serve + npm run dev)

## Konfigurasi

### Environment Variables
(tabel variabel penting di .env)

### Pengaturan Sekolah
(penjelasan admin settings panel — setelah Task 8.1)

## Deployment

### Production Requirements
(server specs recommendation: CPU, RAM, disk)

### Setup Produksi
(langkah singkat: nginx/apache config, supervisor untuk queue, SSL)

## Struktur Proyek
(tree singkat dari CLAUDE.md)

## Testing
(cara jalankan test: php artisan test, npx vitest)

## Lisensi
(sesuai LICENSE file yang ada)
```

### Panduan Penulisan

- Bahasa Indonesia formal tapi tidak kaku
- Tidak pakai emoji sama sekali, atau maksimal 0-2 di tempat yang natural
- Tidak ada badge GitHub (build status, coverage, dll) — belum ada CI/CD
- Tidak ada "Made with love" atau sejenisnya
- Screenshot placeholder: `<!-- Screenshot: halaman dashboard -->` (akan diisi nanti)
- Jangan terlalu panjang — fokus pada informasi yang dibutuhkan developer/admin sekolah
- Target pembaca: developer yang akan deploy ke sekolah baru

### File Terkait

- Hanya edit `README.md` di root project
- Tidak perlu edit file lain

### Acceptance Criteria

1. README.md lengkap dengan semua section di atas
2. Instruksi instalasi bisa diikuti dari awal sampai running
3. Bahasa Indonesia, profesional, tidak berlebihan
4. Screenshot placeholder untuk diisi kemudian
5. Informasi akurat sesuai state codebase saat ini

### Estimated Complexity: **LOW**

---

## Ringkasan Estimasi

| Task | Complexity | Dependencies |
|------|-----------|-------------|
| 8.1 System Settings | HIGH | Tidak ada (fondasi) |
| 8.2 Auto-Generate Password | MEDIUM | Tidak ada |
| 8.3 Cetak Kartu Peserta | MEDIUM | 8.1 (logo, nama sekolah) |
| 8.4 Watermark | LOW-MEDIUM | 8.1 (setting toggle) |
| 8.5 Halaman Guru | MEDIUM-HIGH | Tidak ada |
| 8.6 Download Kredensial | MEDIUM | 8.2 (password generation) |
| 8.7 README.md | LOW | Tidak ada |

## File yang Akan Dibuat/Dimodifikasi

### File Baru

| File | Task |
|------|------|
| `database/migrations/xxxx_create_settings_table.php` | 8.1 |
| `database/seeders/SettingSeeder.php` | 8.1 |
| `app/Models/Setting.php` | 8.1 |
| `app/Services/SettingService.php` | 8.1 |
| `app/helpers.php` | 8.1 |
| `app/Http/Controllers/Admin/SettingController.php` | 8.1 |
| `app/Http/Requests/Admin/UpdateGeneralSettingsRequest.php` | 8.1 |
| `app/Http/Requests/Admin/UpdateAppearanceSettingsRequest.php` | 8.1 |
| `app/Http/Requests/Admin/UpdateExamSettingsRequest.php` | 8.1 |
| `app/Http/Requests/Admin/UpdateEmailSettingsRequest.php` | 8.1 |
| `resources/js/pages/Admin/Settings/Index.vue` | 8.1 |
| `resources/js/types/settings.ts` | 8.1 |
| `resources/js/Components/Exam/WatermarkOverlay.vue` | 8.4 |
| `resources/views/pdf/participant-cards.blade.php` | 8.3 |
| `resources/views/pdf/student-credentials.blade.php` | 8.6 |
| `app/Http/Controllers/Admin/CredentialController.php` | 8.6 |
| `app/Http/Controllers/Admin/GuruController.php` | 8.5 |
| `app/Http/Controllers/Admin/GuruImportController.php` | 8.5 |
| `app/Http/Requests/Admin/GuruRequest.php` | 8.5 |
| `app/Imports/GuruImport.php` | 8.5 |
| `resources/js/pages/Admin/Guru/Index.vue` | 8.5 |
| `resources/js/pages/Admin/Guru/Create.vue` | 8.5 |
| `resources/js/pages/Admin/Guru/Edit.vue` | 8.5 |

### File Dimodifikasi

| File | Task | Perubahan |
|------|------|-----------|
| `composer.json` | 8.1 | Tambah autoload.files untuk helpers.php |
| `routes/web.php` | 8.1, 8.2, 8.3, 8.5, 8.6 | Tambah routes baru |
| `app/Http/Middleware/HandleInertiaRequests.php` | 8.1 | Share settings ke frontend |
| `app/Http/Controllers/Admin/UserController.php` | 8.2 | Auto-generate password, reset endpoint |
| `app/Http/Controllers/Admin/UserImportController.php` | 8.2, 8.6 | Store credentials di cache |
| `app/Http/Requests/Admin/UserRequest.php` | 8.2 | Password nullable untuk siswa |
| `resources/js/pages/Admin/Users/Create.vue` | 8.2 | Password optional, show generated |
| `resources/js/pages/Admin/Users/Index.vue` | 8.2, 8.6 | Credential display, download buttons |
| `resources/js/pages/Admin/Users/Edit.vue` | 8.2 | Reset password button |
| `app/Http/Controllers/Guru/ExamSessionController.php` | 8.3 | printParticipantCards method |
| `resources/js/pages/Guru/Ujian/Show.vue` | 8.3 | Tombol cetak kartu |
| `app/Http/Controllers/Siswa/ExamController.php` | 8.4 | Pass watermark setting |
| `resources/js/pages/Siswa/Ujian/ExamInterface.vue` | 8.4 | Include WatermarkOverlay |
| `README.md` | 8.7 | Rewrite lengkap |
| Semua file yang pakai `config('school.*')` | 8.1 | Ganti ke `setting()` |

### Catatan Teknis

1. **Redis dependency**: Task 8.1 dan 8.6 heavily rely on Redis. Pastikan Redis tersedia di semua deployment target.
2. **DomPDF limitations**: DomPDF tidak support semua CSS (flexbox limited, no grid). Gunakan table-based layout untuk PDF.
3. **File upload storage**: Gunakan `storage/app/public/settings/` untuk logo dan assets. Pastikan `storage:link` sudah dijalankan.
4. **Cache warming**: Pertimbangkan `SettingsCacheWarmer` artisan command untuk cold start setelah deploy.
5. **Backward compatibility**: `config/school.php` tetap ada sebagai fallback. `setting()` helper cek DB dulu, fallback ke config, lalu ke parameter default.
6. **Testing strategy**: Setiap task harus punya feature test. Gunakan `RefreshDatabase` trait. Mock Redis di test environment jika perlu.
