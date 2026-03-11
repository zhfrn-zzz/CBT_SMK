# PRD.md — Product Requirements Document

# SMK LMS + CBT System

## 1. Executive Summary

Sistem LMS dan CBT terintegrasi untuk SMK, dirancang menangani 500+ siswa ujian bersamaan pada infrastruktur sekolah (Intel Xeon 20 core, 16GB RAM, HDD). Sistem menggantikan ujian kertas dan menyediakan platform pembelajaran digital terpusat.

Dibangun di atas Laravel Official Vue Starter Kit (Laravel 12 + Vue 3 + Inertia.js + TypeScript + Tailwind CSS + shadcn-vue).

## 2. User Roles

### 2.1 Admin
Operator sekolah / guru jurusan yang mengelola sistem secara keseluruhan.
- Mengelola semua user (guru, siswa)
- Mengatur tahun ajaran, kelas, jurusan, mata pelajaran
- Memonitor sistem, melihat audit log
- Akses penuh ke seluruh fitur

### 2.2 Guru
Tenaga pengajar yang membuat materi, soal, dan mengelola pembelajaran.
- Membuat dan mengelola materi pembelajaran
- Membuat bank soal dan sesi ujian
- Menilai tugas dan ujian esai
- Melihat hasil dan analisis per kelas yang diampu

### 2.3 Siswa
Peserta didik yang mengakses materi dan mengerjakan ujian.
- Mengakses materi dan mengerjakan tugas
- Mengikuti ujian CBT
- Melihat nilai dan pembahasan

---

## 3. Feature Specification — Phase 1 (Core CBT)

> **Goal**: Sistem CBT yang bisa dipakai untuk ujian 500+ siswa. Ini adalah MVP.
> **Estimasi**: 4-6 minggu development

### 3.1 Authentication & User Management

#### F1.1 — Multi-Role Authentication
- Login dengan username/email + password (starter kit auth sudah termasuk)
- Extend user model dengan field `role` (admin, guru, siswa)
- Role-based redirect: Admin → admin dashboard, Guru → guru dashboard, Siswa → siswa dashboard
- Session management via Redis
- Logout dari semua device (force logout)

**Acceptance Criteria:**
- [ ] User bisa login dan ter-redirect sesuai role
- [ ] Middleware mencegah akses lintas role (siswa tidak bisa akses halaman guru)
- [ ] Session tersimpan di Redis, bukan file

#### F1.2 — User CRUD oleh Admin
- Admin bisa create, read, update, delete user (guru & siswa)
- Form input manual untuk single user
- Bulk import siswa via file Excel/CSV
- Format import: NIS, Nama, Kelas, Jurusan, Password (atau auto-generate)
- Validasi duplikasi NIS
- Bulk import harus via Queue (non-blocking untuk file besar)
- DataTable menggunakan TanStack Table (sortable, searchable, paginated)

**Acceptance Criteria:**
- [ ] Admin bisa tambah user satu per satu via form
- [ ] Admin bisa upload file Excel/CSV dan system import semua siswa
- [ ] Import 500 siswa selesai dalam < 30 detik
- [ ] Duplikasi NIS ditolak dengan error message yang jelas
- [ ] Password auto-generate menghasilkan output yang bisa di-download (Excel) untuk dibagikan

#### F1.3 — Manajemen Kelas & Struktur Akademik
- CRUD Jurusan (misal: TKJ, RPL, MM)
- CRUD Kelas (misal: XI TKJ 1) — terikat ke jurusan dan tahun ajaran
- CRUD Mata Pelajaran — terikat ke jurusan
- CRUD Tahun Ajaran (misal: 2025/2026) dengan status aktif/non-aktif
- Assign siswa ke kelas (bulk)
- Assign guru ke mata pelajaran + kelas

**Acceptance Criteria:**
- [ ] Tahun ajaran bisa diaktifkan/nonaktifkan; hanya 1 yang aktif
- [ ] Siswa otomatis terfilter berdasarkan tahun ajaran aktif
- [ ] Guru hanya melihat kelas dan mapel yang di-assign ke mereka
- [ ] Perpindahan tahun ajaran tidak menghapus data lama

### 3.2 Bank Soal

#### F1.4 — CRUD Bank Soal
- Guru membuat bank soal per mata pelajaran
- Setiap bank soal punya: nama, mata pelajaran, deskripsi
- Di dalam bank soal, guru membuat soal-soal

#### F1.5 — Pembuatan Soal
Tipe soal untuk Phase 1:
- **Pilihan Ganda (PG)**: 4-5 opsi, 1 jawaban benar
- **Benar/Salah**: 2 opsi fixed
- **Esai**: Jawaban teks panjang, dinilai manual

Setiap soal memiliki:
- Teks soal (rich text via Tiptap — bold, italic, underline, list, code block)
- Media attachment (gambar) — opsional
- Tipe soal
- Jawaban benar (untuk PG dan B/S)
- Bobot nilai (default 1, bisa diubah)
- Pembahasan (ditampilkan setelah ujian selesai)

**Acceptance Criteria:**
- [ ] Guru bisa buat soal PG dengan 4-5 pilihan dan tandai jawaban benar
- [ ] Guru bisa upload gambar untuk soal
- [ ] Soal tersimpan dan bisa di-edit setelahnya
- [ ] Rich text editor (Tiptap) berfungsi (minimal bold, italic, list)
- [ ] Bobot nilai bisa dikustomisasi per soal

#### F1.6 — Import Soal dari Excel
- Template Excel yang bisa di-download guru
- Format: Teks Soal | Tipe | Opsi A | Opsi B | Opsi C | Opsi D | Jawaban Benar | Bobot | Pembahasan
- Validasi format dan content sebelum import
- Preview sebelum confirm import
- Import via Queue

**Acceptance Criteria:**
- [ ] Guru bisa download template Excel
- [ ] Upload Excel menampilkan preview soal sebelum import
- [ ] Error per baris ditampilkan (misal: soal PG tanpa jawaban benar)
- [ ] Import 200 soal selesai dalam < 15 detik

### 3.3 Pelaksanaan CBT

#### F1.7 — Pembuatan Sesi Ujian
Guru/Admin membuat sesi ujian dengan konfigurasi:
- Nama ujian
- Mata pelajaran
- Pilih soal dari bank soal (manual pick atau random dari pool)
- **Question Pool**: pilih N soal random dari M soal di bank (opsional)
- Kelas peserta (bisa multiple kelas)
- Durasi ujian (menit)
- Jadwal mulai dan selesai (window waktu ujian tersedia)
- Multi-sesi: bisa buat beberapa sesi dengan soal sama tapi jadwal berbeda
- Token/kode akses (auto-generate, 6 karakter alfanumerik)
- Randomisasi urutan soal (on/off)
- Randomisasi urutan jawaban PG (on/off)
- KKM (Kriteria Ketuntasan Minimal) untuk ujian ini

**Acceptance Criteria:**
- [ ] Guru bisa buat sesi ujian dengan semua konfigurasi di atas
- [ ] Token unik per sesi, ditampilkan untuk dibagikan pengawas
- [ ] Jika question pool aktif, setiap siswa mendapat set soal berbeda
- [ ] Preview ujian tersedia sebelum dipublish

#### F1.8 — Mengerjakan Ujian (Student Exam Interface)
Ini adalah fitur paling kritikal. Interface yang dilihat siswa saat ujian:

**Pre-Exam:**
- Siswa melihat daftar ujian yang tersedia (sesuai kelas & jadwal)
- Input token/kode akses
- Halaman konfirmasi: nama ujian, jumlah soal, durasi, aturan
- Tombol "Mulai Ujian" → masuk fullscreen

**During Exam:**
- **Tampilan soal**: satu soal per halaman (single question view)
- **Navigation panel** (sidebar): grid nomor soal dengan warna status:
  - Abu-abu: belum dijawab
  - Hijau: sudah dijawab
  - Kuning: ditandai ragu-ragu (flagged)
- **Timer countdown**: ditampilkan permanent di header, sinkron dengan server
- **Tombol navigasi**: Previous, Next, langsung klik nomor soal
- **Flag/tandai soal**: tombol untuk tandai soal yang mau direview nanti
- **Auto-save**: jawaban disimpan di localStorage browser + dikirim ke server (Redis) setiap 30 detik
- **Indikator save**: visual feedback bahwa jawaban sudah tersimpan

**End of Exam:**
- Tombol "Selesai & Kumpulkan" → konfirmasi dialog (shadcn AlertDialog) yang menampilkan:
  - Jumlah soal terjawab vs total
  - Jumlah soal yang di-flag
  - Warning jika ada soal belum dijawab
- Auto-submit saat waktu habis (server-enforced)
- Halaman konfirmasi setelah submit: "Ujian berhasil dikumpulkan"

**Acceptance Criteria:**
- [ ] Ujian hanya bisa diakses dalam window jadwal dan dengan token yang benar
- [ ] Timer countdown akurat (selisih < 2 detik dengan server time)
- [ ] Auto-save berjalan setiap 30 detik tanpa mengganggu pengerjaan
- [ ] Navigasi antar soal instan (< 100ms) — semua soal sudah loaded di client
- [ ] Flag soal bekerja dan terrefleksi di navigation panel
- [ ] Auto-submit terjadi tepat saat waktu habis
- [ ] Ujian yang sudah di-submit tidak bisa dibuka lagi
- [ ] Semua soal sudah di-load di client saat ujian dimulai (satu request awal)

#### F1.9 — Resume Session (Crash Recovery)
- Jika browser crash, mati lampu, atau koneksi terputus:
  - Siswa login ulang → sistem deteksi ada ujian aktif
  - Otomatis redirect ke ujian yang sedang berlangsung
  - Jawaban yang sudah ter-save di Redis di-restore
  - Timer melanjutkan dari waktu server (bukan reset)
  - Jawaban di localStorage juga di-reconcile dengan server (mana yang lebih baru)

**Acceptance Criteria:**
- [ ] Siswa bisa melanjutkan ujian setelah browser crash
- [ ] Jawaban yang sudah di-save tidak hilang
- [ ] Timer tidak reset — menggunakan waktu server
- [ ] Reconciliation: jawaban terbaru (client vs server) yang dipakai

#### F1.10 — Auto-Save Architecture
Detail teknis:

```
[Browser State] → setiap perubahan jawaban → [localStorage]
                                            ↓ (setiap 30 detik)
                                     [API call ke server]
                                            ↓
                                     [Redis buffer]
                                            ↓ (Queue job setiap 60 detik)
                                     [MySQL persist]
```

- Client menyimpan SEMUA jawaban sebagai satu objek JSON: `{ "soal_1": "A", "soal_2": "C", ... }`
- Setiap auto-save mengirim seluruh objek (bukan per-soal) — simpel dan idempotent
- Server menyimpan ke Redis key: `exam:{session_id}:student:{student_id}:answers`
- Queue job periodically persist dari Redis ke MySQL (tabel `student_answers`)
- Saat submit, langsung persist ke MySQL dan hapus Redis key

#### F1.11 — Anti-Cheat Dasar
- **Fullscreen mode**: ujian berjalan dalam fullscreen. Jika keluar, tampilkan warning.
- **Tab switch detection**: `visibilitychange` event. Log setiap kali siswa pindah tab.
- **Log disimpan**: timestamp + event type ke tabel `exam_activity_logs`
- **Bukan blocking** — siswa tidak di-kick otomatis, tapi log tersedia untuk guru/pengawas

**Acceptance Criteria:**
- [ ] Ujian meminta fullscreen saat dimulai
- [ ] Warning ditampilkan saat siswa keluar fullscreen / pindah tab
- [ ] Setiap pelanggaran tercatat dengan timestamp
- [ ] Guru bisa melihat log pelanggaran per siswa setelah ujian

### 3.4 Grading & Hasil

#### F1.12 — Auto-Grading
- Soal PG dan Benar/Salah: otomatis dinilai saat submit
- Perhitungan: `(jawaban benar × bobot) / total bobot × 100`
- Soal esai: ditandai "belum dinilai", menunggu input guru
- Nilai total dihitung setelah semua soal (termasuk esai) dinilai

**Acceptance Criteria:**
- [ ] Nilai PG & B/S langsung tersedia setelah submit
- [ ] Jika ada soal esai, total nilai berstatus "partial" sampai esai dinilai
- [ ] Perhitungan bobot akurat

#### F1.13 — Manual Grading (Esai)
- Interface grading: tampilkan soal + jawaban siswa + rubrik/pembahasan
- Guru input nilai per soal esai (0 sampai bobot maksimal)
- Guru bisa tambah komentar/feedback per jawaban
- Navigasi antar siswa: Previous Student / Next Student
- Filter: belum dinilai, sudah dinilai
- Bulk action: bisa lihat satu soal esai untuk semua siswa sekaligus

**Acceptance Criteria:**
- [ ] Guru bisa menilai esai satu per satu per siswa
- [ ] Guru bisa melihat semua jawaban siswa untuk 1 soal (view per-soal)
- [ ] Setelah semua esai dinilai, total nilai otomatis terupdate
- [ ] Progress grading terlihat (X dari Y siswa sudah dinilai)

#### F1.14 — Hasil Ujian & Pembahasan
- **Untuk Guru**: tabel hasil per kelas (TanStack Table) — nama siswa, nilai, status (lulus/remedial berdasarkan KKM), waktu pengerjaan
- **Untuk Siswa**: setelah guru publish hasil:
  - Nilai total
  - Detail per soal: jawaban siswa, jawaban benar, pembahasan
  - Status lulus/remedial
- Guru bisa memilih kapan hasil di-publish ke siswa
- Export hasil ke Excel (queue job)

**Acceptance Criteria:**
- [ ] Guru melihat rekap nilai satu kelas dalam DataTable (sortable, searchable)
- [ ] Siswa hanya bisa lihat hasil setelah guru publish
- [ ] Pembahasan per soal ditampilkan dengan benar
- [ ] Export Excel menghasilkan file yang rapi dan bisa langsung dipakai

### 3.5 Dashboard Phase 1

#### F1.15 — Dashboard per Role (Basic)
- **Admin**: jumlah user, jumlah ujian aktif, ujian hari ini
- **Guru**: kelas yang diampu, ujian mendatang, esai yang perlu dinilai
- **Siswa**: ujian mendatang, ujian yang sudah selesai, nilai terakhir

---

## 4. Feature Specification — Phase 2 (CBT Advanced + Proctor)

> **Goal**: Fitur-fitur yang membuat CBT production-ready dan aman.
> **Estimasi**: 3-4 minggu setelah Phase 1

### 4.1 Proctor & Monitoring

#### F2.1 — Proctor Dashboard (Real-time)
- Halaman khusus untuk pengawas/guru selama ujian berlangsung
- Menampilkan real-time via WebSocket (Laravel Reverb):
  - Daftar semua peserta ujian
  - Status per siswa: Belum mulai / Sedang mengerjakan / Sudah submit
  - Progress per siswa: X dari Y soal terjawab
  - Flag pelanggaran: ikon warning jika siswa pindah tab
  - Waktu tersisa per siswa
- Auto-refresh tanpa reload halaman

#### F2.2 — Manual Override oleh Pengawas
Selama ujian berlangsung, pengawas/guru bisa:
- **Extend waktu** siswa tertentu (misal +10 menit)
- **Reset session** siswa (tanpa hapus jawaban yang sudah tersimpan)
- **Terminate ujian** siswa tertentu (force submit)
- **Batalkan soal** — tandai soal sebagai invalid, semua siswa dapat nilai penuh untuk soal tersebut
- Semua override tercatat di audit log

### 4.2 Tipe Soal Tambahan

#### F2.3 — Tipe Soal Extended
- **Isian Singkat**: jawaban teks pendek, auto-grade dengan keyword matching (case-insensitive, bisa multiple keyword alternatif)
- **Menjodohkan (Matching)**: drag-and-drop atau dropdown untuk pasangkan kolom A ke kolom B
- **Multiple Correct Answer**: pilihan ganda dengan lebih dari satu jawaban benar
- **Ordering/Ranking**: urutkan item dengan drag-and-drop

### 4.3 Security Enhancement

#### F2.4 — Device/IP Locking
- Saat siswa mulai ujian, catat IP address dan device fingerprint
- Jika ada login dari device/IP berbeda selama ujian aktif, tolak dengan pesan error
- Admin bisa override (unlock) jika siswa perlu pindah komputer

#### F2.5 — Enhanced Tab Switch Policy
- Konfigurasi per ujian: berapa kali max tab switch sebelum auto-submit
- Warning bertingkat: peringatan 1, peringatan 2, auto-submit

### 4.4 Remedial System

#### F2.6 — Sistem Remedial
- Setelah hasil dipublish, siswa dengan nilai < KKM otomatis ditandai "Remedial"
- Guru bisa buat ujian remedial (soal baru atau dari bank soal yang sama)
- Nilai remedial tersimpan terpisah, tapi bisa "menggantikan" nilai asli (configurable: ambil yang tertinggi, atau cap di KKM)
- Tracking: siswa mana yang sudah remedial, nilainya berapa

---

## 5. Feature Specification — Phase 3 (LMS Core)

> **Goal**: Fitur pembelajaran di luar ujian.
> **Estimasi**: 3-4 minggu setelah Phase 2

### 5.1 Materi Pembelajaran

#### F3.1 — Upload & Manajemen Materi
- Guru upload materi per mata pelajaran per kelas
- Tipe file: PDF, DOCX, PPTX, gambar, link video YouTube
- Organisasi: per topik/bab, bisa diurutkan
- Siswa bisa tandai "sudah dibaca/ditonton"

#### F3.2 — Learning Progress Tracking
- Per siswa: persentase materi yang sudah diakses
- Per kelas: overview completion rate
- Guru bisa lihat siapa yang belum buka materi tertentu

### 5.2 Tugas & Assignment

#### F3.3 — Pembuatan Tugas
- Guru buat tugas dengan: judul, deskripsi, lampiran (opsional), deadline
- Assign ke kelas atau siswa tertentu
- Tipe submission: file upload atau teks

#### F3.4 — Submission & Grading Tugas
- Siswa submit tugas (upload file / input teks) sebelum deadline
- Late submission bisa diizinkan atau ditolak (configurable)
- Guru menilai: nilai angka + feedback teks
- Status: belum submit, sudah submit, sudah dinilai, terlambat

### 5.3 Interaksi

#### F3.5 — Discussion Forum
- Forum per mata pelajaran per kelas
- Thread-based: siswa buat thread, semua bisa reply
- Guru bisa pin thread penting
- Notifikasi untuk reply di thread yang diikuti

#### F3.6 — Pengumuman
- Guru/Admin buat pengumuman per kelas atau broadcast ke semua
- Ditampilkan di dashboard siswa
- Pengumuman bisa di-pin

### 5.4 Presensi

#### F3.7 — Kehadiran Siswa
- Guru buka sesi presensi per pertemuan
- Siswa menandai hadir (bisa via kode QR atau kode akses)
- Status: Hadir, Izin, Sakit, Alfa
- Rekap kehadiran per siswa per bulan/semester
- Export rekap presensi ke Excel

---

## 6. Feature Specification — Phase 4 (Analytics & Polish)

> **Goal**: Analytics, integrasi, dan polish untuk production.
> **Estimasi**: 2-3 minggu setelah Phase 3

### 6.1 Analytics & Reporting

#### F4.1 — Analisis Butir Soal
Per ujian, hitung dan tampilkan:
- **Tingkat kesulitan** per soal: % siswa yang menjawab benar
- **Daya beda**: korelasi jawaban benar pada soal tersebut dengan nilai total
- Rekomendasi: soal terlalu mudah (>90% benar), terlalu sulit (<10% benar), daya beda rendah
- Visualisasi chart

#### F4.2 — Dashboard Analytics (Advanced)
- **Admin**: tren nilai per kelas, per jurusan, per semester. Perbandingan antar kelas.
- **Guru**: distribusi nilai per ujian (histogram), rata-rata per KD, siswa yang konsisten remedial

#### F4.3 — KI/KD Tagging
- Soal di bank soal bisa di-tag ke KD tertentu
- Hasil ujian bisa di-breakdown per KD: "Siswa X lemah di KD 3.4"
- Mapping KD ke mata pelajaran

### 6.2 Notifications

#### F4.4 — Notification System
- In-app notification (bell icon + dropdown)
- Trigger events: ujian dijadwalkan, deadline tugas mendekat (H-1), nilai dipublish, materi baru, pengumuman
- Notifikasi tersimpan di database, bisa ditandai sudah dibaca
- Opsional: integrasi WhatsApp gateway (Fonnte / Wablas) untuk notifikasi kritis

### 6.3 Audit & Security

#### F4.5 — Audit Trail
- Log semua aksi penting: login, CRUD user, CRUD soal, edit nilai, override ujian
- Siapa, kapan, apa yang diubah (old value → new value)
- Hanya bisa dilihat oleh Admin
- Retensi: minimal 1 tahun

#### F4.6 — Backup & Restore
- Automated daily backup MySQL (via cron + mysqldump)
- Backup file materi (rsync)
- Instruksi restore yang terdokumentasi
- Admin bisa trigger manual backup dari dashboard

### 6.4 Data Exchange

#### F4.7 — Export/Import Institusional
- Export nilai dalam format yang kompatibel untuk rapor sekolah
- Export data siswa + nilai untuk kebutuhan Dapodik
- Import data siswa dari Dapodik (jika format tersedia)

#### F4.8 — Print Soal (Backup Fallback)
- Guru bisa generate PDF dari sesi ujian
- Format print-friendly: nomor soal, pilihan jawaban, lembar jawaban terpisah
- Untuk skenario darurat jika server down saat ujian

---

## 7. Non-Functional Requirements

### 7.1 Performance
- Halaman load < 2 detik pada koneksi LAN sekolah
- CBT interface: navigasi antar soal < 100ms
- Auto-save tidak boleh mengganggu UX (background process)
- Sistem harus handle 500 concurrent CBT users tanpa degradasi signifikan
- API response time < 500ms untuk operasi standar

### 7.2 Reliability
- Auto-save memastikan maximum data loss: 30 detik jawaban
- Resume session harus bekerja setelah: browser crash, mati lampu, koneksi terputus
- Sistem harus gracefully handle: Redis down (fallback ke database), queue failure (retry mechanism)

### 7.3 Security
- Password hashing menggunakan bcrypt (Laravel default)
- CSRF protection pada semua form
- Rate limiting pada login endpoint (mencegah brute force)
- Input sanitization pada semua user input (XSS prevention)
- SQL injection prevention via Eloquent ORM (parameterized queries)

### 7.4 Accessibility
- Interface harus usable di: Chrome, Firefox, Edge (versi terbaru)
- Responsive minimal untuk tablet (pengawas mungkin pakai tablet)
- CBT interface optimized untuk desktop/laptop (primary use case)

### 7.5 Maintainability
- Code harus readable oleh PHP/Laravel developer yang bukan original author
- Setiap Service class memiliki clear single responsibility
- Database migration yang reversible (up + down method)
- README dengan setup instruction yang lengkap
