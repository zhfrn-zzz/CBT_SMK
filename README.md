# SMK LMS — Sistem Manajemen Pembelajaran & Ujian Berbasis Komputer

Sistem informasi terintegrasi untuk Sekolah Menengah Kejuruan yang menggabungkan Learning Management System (LMS) dan Computer Based Test (CBT) dalam satu platform. Dirancang untuk menangani 500+ siswa ujian bersamaan pada infrastruktur server sekolah standar.

<!-- TODO: tambahkan screenshot -->

---

## Fitur Utama

### Learning Management System

Guru dapat mengunggah dan mengorganisir materi pembelajaran per mata pelajaran, membuat tugas dengan tenggat waktu, serta memantau progres belajar siswa. Tersedia juga forum diskusi per kelas dan sistem pengumuman untuk komunikasi antara guru dan siswa. Fitur presensi digital membantu pencatatan kehadiran siswa secara efisien.

### Computer Based Test

Platform ujian digital mendukung tujuh tipe soal: pilihan ganda, benar/salah, esai, jawaban singkat, menjodohkan, pilihan ganda kompleks, dan mengurutkan. Soal objektif dinilai otomatis, sementara esai tersedia antarmuka koreksi manual untuk guru. Sistem auto-save menyimpan jawaban setiap 30 detik ke browser dan server, sehingga jawaban siswa tetap aman meskipun terjadi gangguan koneksi atau perangkat. Timer ujian disinkronkan dengan server untuk mencegah manipulasi waktu. Tersedia dashboard proktor untuk monitoring ujian secara real-time, termasuk fitur perpanjangan waktu, reset sesi, dan terminasi ujian.

### Manajemen Sekolah

Admin dapat mengelola pengguna dengan tiga peran (Admin, Guru, Siswa) beserta import massal via Excel. Struktur akademik lengkap mencakup tahun ajaran, jurusan, kelas, dan mata pelajaran. Tersedia analisis butir soal, laporan hasil ujian, serta ekspor data ke format Excel dan PDF.

---

## Tech Stack

| Layer | Teknologi | Versi |
|-------|-----------|-------|
| Backend | Laravel | 12.x |
| Frontend | Vue 3 + Inertia.js + TypeScript | Vue 3.5+, Inertia 2.x |
| Komponen UI | shadcn-vue + TanStack Table | Terbaru |
| Styling | Tailwind CSS | 4.x |
| Database | MySQL | 8.x |
| Cache & Queue | Redis | 7.x |
| WebSocket | Laravel Reverb | 1.x |
| Testing | Pest PHP | Terbaru |
| Rich Text Editor | Tiptap | Terbaru |

---

## Requirements

- PHP >= 8.2 (beserta ekstensi yang dibutuhkan Laravel 12)
- Composer 2.x
- MySQL 8.x
- Redis 7.x (wajib -- digunakan untuk session, cache, queue, dan buffer jawaban ujian)
- Node.js >= 20

---

## Instalasi

```bash
# Clone repository
git clone <repository-url> smk-lms
cd smk-lms

# Install dependensi
composer install
npm install

# Konfigurasi environment
cp .env.example .env
php artisan key:generate
```

Sesuaikan file `.env` dengan konfigurasi database dan Redis:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=smk_lms
DB_USERNAME=root
DB_PASSWORD=

REDIS_HOST=127.0.0.1
REDIS_PORT=6379

SESSION_DRIVER=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis
```

Lanjutkan setup:

```bash
# Migrasi dan seed database
php artisan migrate --seed

# Buat symbolic link untuk storage
php artisan storage:link

# Build assets frontend
npm run build

# Jalankan development server
php artisan serve
```

Untuk development, jalankan Vite dev server di terminal terpisah:

```bash
npm run dev
```

Jika menggunakan fitur queue (grading, notifikasi), jalankan worker:

```bash
php artisan queue:work redis
```

---

## Akun Default

Setelah menjalankan seeder, tersedia akun berikut untuk pengujian:

| Peran | Username | Email | Password |
|-------|----------|-------|----------|
| Admin | admin | admin@smklms.test | password |
| Guru | guru | guru@smklms.test | password |
| Siswa | (100 akun otomatis) | — | password |

Seeder juga membuat 5 akun guru tambahan dengan data lengkap (NIP, mata pelajaran) dan 100 akun siswa yang terdistribusi ke beberapa kelas.

---

## Konfigurasi

### Environment Variables Penting

| Variable | Nilai | Keterangan |
|----------|-------|------------|
| `APP_TIMEZONE` | `Asia/Jakarta` | Zona waktu aplikasi (WIB) |
| `SESSION_DRIVER` | `redis` | Wajib Redis untuk performa |
| `CACHE_STORE` | `redis` | Wajib Redis untuk performa |
| `QUEUE_CONNECTION` | `redis` | Wajib Redis untuk queue job |
| `BCRYPT_ROUNDS` | `12` | Rounds hashing password |
| `EXAM_SECURITY_HARDENING` | `true` | Aktifkan fitur keamanan ujian |

### Pengaturan Sekolah

Konfigurasi identitas sekolah, tahun ajaran aktif, dan pengaturan sistem lainnya dapat dilakukan melalui panel Admin setelah login.

---

## Deployment Produksi

Rekomendasi minimum server: prosesor multi-core, RAM 16GB, dengan Redis wajib terpasang. Penggunaan SSD sangat disarankan, namun sistem tetap dioptimasi untuk HDD.

### Komponen yang Perlu Disiapkan

1. **Nginx** sebagai web server dengan konfigurasi PHP-FPM
2. **Supervisor** untuk menjaga queue worker tetap berjalan:
   ```ini
   [program:smk-lms-worker]
   command=php /path/to/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
   numprocs=2
   autostart=true
   autorestart=true
   ```
3. **Redis** sebagai service yang berjalan otomatis saat boot
4. **SSL/TLS** via Let's Encrypt atau sertifikat lainnya
5. Jalankan `npm run build` dan `php artisan optimize` sebelum serve

Pastikan `APP_ENV=production`, `APP_DEBUG=false`, dan `APP_URL` sesuai domain.

---

## Testing

```bash
# Backend tests (Pest PHP)
php artisan test

# Jalankan test spesifik
php artisan test --filter=NamaTest
```

---

## Struktur Proyek

```
app/
├── Enums/                          # PHP Enums (UserRole, QuestionType, ExamStatus)
├── Http/Controllers/{Admin,Guru,Siswa}/
├── Http/Requests/                  # Form Request validation
├── Models/                         # Eloquent models
├── Services/                       # Business logic layer
├── Jobs/                           # Queue jobs
├── Policies/                       # Authorization policies
resources/js/
├── components/ui/                  # shadcn-vue base components
├── Components/                     # Custom components (Exam/, DataTable/)
├── Pages/{Admin,Guru,Siswa}/       # Inertia pages per role
├── composables/                    # Vue composables (useExamTimer, useAutoSave)
├── types/                          # TypeScript interfaces
routes/web.php                      # Semua route Inertia
```

---

## Lisensi

Copyright (c) 2026 SMK BINA MANDIRI BEKASI. All Rights Reserved.

Lihat file [LICENSE](LICENSE) untuk detail.
