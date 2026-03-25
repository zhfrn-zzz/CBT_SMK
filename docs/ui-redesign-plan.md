# UI/UX Redesign Plan — SMK Bina Mandiri LMS + CBT

> **Status:** Planning (belum implementasi)
> **Tanggal:** 2026-03-24
> **Scope:** Full visual overhaul — semua 95+ halaman Vue
> **Style direction:** Modern bold (terinspirasi Quizizz) tapi lebih professional/serius untuk lingkungan sekolah
> **Target users:** Guru (40-60 tahun, kurang tech-savvy), Siswa (15-18 tahun), Admin

---

## Daftar Isi

1. [Design System & Tokens](#1-design-system--tokens)
2. [Beranda Publik (Welcome)](#2-beranda-publik-welcomevue)
3. [Login Page](#3-login-page)
4. [Sidebar & AppLayout](#4-sidebar--applayout)
5. [Admin Dashboard](#5-admin-dashboard)
6. [Guru Dashboard](#6-guru-dashboard)
7. [Siswa Dashboard](#7-siswa-dashboard)
8. [Admin CRUD Pages](#8-admin-crud-pages)
9. [Guru Pages](#9-guru-pages)
10. [Siswa Pages](#10-siswa-pages)
11. [CBT Exam Interface](#11-cbt-exam-interface-prioritas-tertinggi)
12. [Forum Sekolah](#12-forum-sekolah)
13. [Profil & Settings](#13-profil--settings)
14. [Kalender](#14-kalender)
15. [Notifications](#15-notifications)
16. [Error Pages](#16-error-pages)
17. [Global Polish & Patterns](#17-global-polish--patterns)
18. [Implementasi Roadmap](#18-implementasi-roadmap)

---

## 1. Design System & Tokens

### 1.1 Color System — CSS Variables (`resources/css/app.css`)

Ganti seluruh `:root` dan `.dark` color variables. Sistem saat ini pakai grayscale generik — ganti ke brand colors SMK Bina Mandiri.

```
:root {
  /* === Brand Colors === */
  --primary: 217.2 91.2% 59.8%;        /* #2563EB — Blue (logo sekolah) */
  --primary-foreground: 0 0% 100%;      /* White text on blue */
  --primary-dark: 223.8 76.3% 48.4%;    /* #1D4ED8 — Hover blue */

  /* === Semantic Colors === */
  --accent-red: 0 72.2% 50.6%;          /* #DC2626 — Badge penting (logo sekolah) */
  --success: 160 84.1% 39.4%;           /* #10B981 — Berhasil */
  --warning: 37.7 92.1% 50.2%;          /* #F59E0B — Flag, deadline */
  --danger: 0 84.2% 60.2%;              /* #EF4444 — Hapus, error */

  /* === Surface Colors === */
  --background: 210 40% 98%;            /* #F8FAFC — Page bg (slate-50) */
  --foreground: 215.4 25% 16.9%;        /* #1E293B — Text primary (slate-800) */
  --card: 0 0% 100%;                    /* #FFFFFF */
  --card-foreground: 215.4 25% 16.9%;   /* #1E293B */

  /* === Muted / Secondary === */
  --muted: 210 40% 96.1%;               /* #F1F5F9 (slate-100) */
  --muted-foreground: 215 16.3% 46.9%;  /* #64748B (slate-500) */
  --secondary: 210 40% 96.1%;           /* #F1F5F9 */
  --secondary-foreground: 215.4 25% 16.9%;

  /* === Border / Input === */
  --border: 214.3 31.8% 91.4%;          /* #E2E8F0 (slate-200) */
  --input: 214.3 31.8% 91.4%;
  --ring: 217.2 91.2% 59.8%;            /* Blue focus ring */

  /* === Destructive === */
  --destructive: 0 84.2% 60.2%;         /* #EF4444 */
  --destructive-foreground: 0 0% 100%;

  /* === Sidebar === */
  --sidebar-background: 0 0% 100%;      /* White sidebar */
  --sidebar-foreground: 215 16.3% 46.9%;
  --sidebar-primary: 217.2 91.2% 59.8%;
  --sidebar-primary-foreground: 0 0% 100%;
  --sidebar-accent: 210 40% 96.1%;
  --sidebar-accent-foreground: 215.4 25% 16.9%;
  --sidebar-border: 214.3 31.8% 91.4%;
  --sidebar-ring: 217.2 91.2% 59.8%;

  /* === Chart Colors === */
  --chart-1: 217.2 91.2% 59.8%;         /* Blue */
  --chart-2: 160 84.1% 39.4%;           /* Green */
  --chart-3: 37.7 92.1% 50.2%;          /* Amber */
  --chart-4: 0 72.2% 50.6%;             /* Red */
  --chart-5: 262.1 83.3% 57.8%;         /* Purple */

  --radius: 0.625rem;                   /* 10px — slightly rounder */
}
```

Dark mode `.dark` — tetap support tapi bukan default. Sesuaikan ke brand blue tones (bukan pure gray).

### 1.2 Typography Scale

**Font:** Ganti `Instrument Sans` → **`Inter`** (lebih readable di semua ukuran, dukungan weight lengkap).
Load via Google Fonts atau local (`public/fonts/`).

| Token           | Size   | Weight   | Line Height | Penggunaan                          |
|-----------------|--------|----------|-------------|-------------------------------------|
| `text-xs`       | 12px   | 400      | 16px        | Captions, timestamps                |
| `text-sm`       | 14px   | 400/600  | 20px        | Form labels (bold), secondary text  |
| `text-base`     | 16px   | 400      | 24px        | **Body text minimum** (accessibility)|
| `text-lg`       | 18px   | 500      | 28px        | Soal ujian, subtitle                |
| `text-xl`       | 20px   | 600      | 28px        | Card titles                         |
| `text-2xl`      | 24px   | 700      | 32px        | Page titles                         |
| `text-3xl`      | 30px   | 700      | 36px        | Dashboard hero stats                |
| `text-4xl`      | 36px   | 800      | 40px        | Welcome hero, exam timer            |
| `text-5xl`      | 48px   | 800      | 48px        | Landing page hero heading           |

### 1.3 Spacing System

Gunakan Tailwind default spacing scale. Konvensi:
- **Page padding:** `px-4 sm:px-6 lg:px-8` + `py-6`
- **Card padding:** `p-6` (desktop), `p-4` (mobile)
- **Section gap:** `space-y-6`
- **Form field gap:** `space-y-4`
- **Stats cards grid gap:** `gap-4 sm:gap-6`
- **Between page header & content:** `mt-6`

### 1.4 Component Tokens

#### Button Variants (shadcn `Button`)

| Variant       | Background          | Text           | Hover             | Border | Min Height | Penggunaan                |
|---------------|---------------------|----------------|-------------------|--------|------------|---------------------------|
| `default`     | `bg-primary`        | White          | `bg-primary-dark`  | —      | **44px**   | Primary actions            |
| `secondary`   | `bg-secondary`      | Slate-800      | `bg-slate-200`     | —      | 44px       | Secondary actions          |
| `destructive` | `bg-danger`         | White          | `bg-red-600`       | —      | 44px       | Delete, bahaya             |
| `outline`     | Transparent         | Primary        | `bg-primary/10`    | border | 44px       | Cancel, di samping primary |
| `ghost`       | Transparent         | Slate-600      | `bg-slate-100`     | —      | 44px       | Toolbar, subtle actions    |
| `success`     | `bg-success`        | White          | `bg-emerald-600`   | —      | 44px       | Confirm, approve **(baru)**|
| `warning`     | `bg-warning`        | White          | `bg-amber-600`     | —      | 44px       | Flag, penting **(baru)**   |

**Catatan:** Minimum height 44px untuk SEMUA button — touch-friendly & accessibility. Padding `px-6 py-2.5`. Font `text-sm font-semibold`. Loading state: `Spinner` component di sebelah kiri teks.

#### Card Styles

```
Standard Card:     bg-card rounded-xl border border-border shadow-sm
Hover Card:        hover:shadow-md hover:border-primary/20 transition-all duration-200
Stats Card:        bg-card rounded-xl border border-border p-6 + icon container 48x48 rounded-lg
Active/Selected:   ring-2 ring-primary border-primary
```

#### Badge Variants

| Variant     | Background        | Text         | Penggunaan                    |
|-------------|-------------------|--------------|-------------------------------|
| `default`   | `bg-primary/10`   | Primary blue | Status default                |
| `success`   | `bg-emerald-50`   | Emerald-700  | Selesai, aktif, hadir         |
| `warning`   | `bg-amber-50`     | Amber-700    | Menunggu, mendekati deadline  |
| `danger`    | `bg-red-50`       | Red-700      | Gagal, error, penting         |
| `info`      | `bg-blue-50`      | Blue-700     | Info, pengumuman              |
| `secondary` | `bg-slate-100`    | Slate-600    | Draft, inactive               |

Semua badge: `text-xs font-semibold px-2.5 py-1 rounded-full`

#### Input Styles

```
Base:     h-11 rounded-lg border-border bg-white px-3 text-base placeholder:text-muted-foreground
Focus:    ring-2 ring-primary/20 border-primary
Error:    border-danger ring-2 ring-danger/20
Disabled: opacity-50 bg-muted cursor-not-allowed
Label:    text-sm font-semibold text-foreground (min 14px bold)
```

Height `h-11` (44px) — sesuai accessibility minimum.

#### Table Styles (DataTable dengan TanStack Table)

```
Container:  bg-card rounded-xl border border-border overflow-hidden
Header:     bg-slate-50 text-xs font-semibold uppercase tracking-wider text-muted-foreground
Row:        hover:bg-slate-50/50 border-b border-border transition-colors
Zebra:      even:bg-slate-50/30
Cell:       px-4 py-3 text-sm
Action:     DropdownMenu (bukan icon-only buttons)
Empty:      py-16 text-center — icon + pesan + CTA button
```

### 1.5 Icon Library

**Lucide Icons** (`lucide-vue-next`) — sudah terintegrasi via shadcn-vue.
- Size default: `w-5 h-5` (20px) untuk inline, `w-6 h-6` untuk navigation
- Stats cards: `w-8 h-8` dalam container `w-12 h-12 rounded-lg bg-{color}/10`
- **WAJIB:** Icon selalu bersama label text, TIDAK icon-only tanpa tooltip
- Jika space terbatas (table actions), icon + tooltip via shadcn `Tooltip`

### 1.6 Komponen shadcn-vue yang Dipakai

Sudah terinstall (bisa langsung pakai):
`Alert`, `AlertDialog`, `Avatar`, `Badge`, `Breadcrumb`, `Button`, `Card`, `Checkbox`, `Collapsible`, `Dialog`, `DropdownMenu`, `Input`, `InputOTP`, `Label`, `NavigationMenu`, `Pagination`, `Progress`, `Select`, `Separator`, `Sheet`, `Sidebar`, `Skeleton`, `Sonner`, `Spinner`, `Switch`, `Table`, `Tabs`, `Textarea`, `Tooltip`

**Perlu ditambahkan:**
- `RadioGroup` — untuk opsi PG di ujian
- `Toggle` / `ToggleGroup` — untuk toolbar
- `Popover` — untuk kalender day popover, filter
- `Calendar` — shadcn calendar component
- `Command` — command palette search
- `HoverCard` — user info preview
- `ScrollArea` — navigation panel ujian
- `Accordion` — FAQ, collapsible sections

### 1.7 Tailwind Classes Kunci (Cheat Sheet)

```
Page wrapper:     min-h-screen bg-background
Page container:   max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6
Page header:      flex items-center justify-between mb-6
Page title:       text-2xl font-bold text-foreground
Section:          space-y-6
Stats grid:       grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4
Form card:        bg-card rounded-xl border border-border p-6 shadow-sm
Form layout:      max-w-2xl space-y-4
Full DataTable:   bg-card rounded-xl border border-border overflow-hidden
Breadcrumb area:  mb-4 (tepat di atas page title)
```

**Notes implementor:**
- Semua token warna harus reference CSS variables, BUKAN hardcoded hex
- Tailwind v4: tidak ada `tailwind.config.ts`, semua di `app.css` `@theme inline`
- Pastikan `Inter` font di-load di `resources/views/app.blade.php` atau via Vite

**Estimated effort:** Hard — ini fondasi seluruh redesign. Harus selesai pertama.

---

## 2. Beranda Publik (`Welcome.vue`)

### Layout Description

Full-width landing page tanpa sidebar — standalone layout (bukan AppLayout). Scroll vertikal single page.

**Sections dari atas ke bawah:**

1. **Navbar** — Logo SMK Bina Mandiri kiri, nama sekolah, tombol "Masuk" kanan (primary button)
2. **Hero Section** — Full-width, gradient blue (`from-primary to-blue-800`), min-height 70vh
   - Logo sekolah besar (120px) centered
   - Heading: "SMK Bina Mandiri Kota Bekasi" (`text-4xl sm:text-5xl font-extrabold text-white`)
   - Subheading: "Sistem Pembelajaran & Ujian Digital" (`text-xl text-blue-100`)
   - CTA button: "Masuk ke Sistem" (white bg, blue text, rounded-full, `h-14 px-8 text-lg font-bold`)
   - Subtle pattern/illustration background (CSS gradient + subtle grid pattern)
3. **Fitur Highlights** — 4 cards grid (`grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6`)
   - CBT Online (`Monitor` icon, blue)
   - Bank Soal (`Library` icon, emerald)
   - Manajemen Kelas (`Users` icon, amber)
   - Analisis Nilai (`BarChart3` icon, purple)
   - Setiap card: icon `w-12 h-12` dalam circle berwarna, title bold, deskripsi 1-2 kalimat
4. **Pengumuman Publik** — Section dengan heading + list 5 pengumuman terbaru, card style
5. **Jadwal Ujian** — Table sederhana atau card list, 5 ujian terdekat (nama, tanggal, kelas)
6. **Footer** — Nama sekolah, alamat, © 2026, link kebijakan privasi

### Komponen shadcn-vue
`Button`, `Card`, `Badge`, `Separator`, `NavigationMenu`

### Tailwind Classes Kunci
```
Hero:        bg-gradient-to-br from-primary to-blue-800 min-h-[70vh] flex flex-col items-center justify-center text-center px-4
Feature card: bg-card rounded-2xl border border-border p-8 text-center hover:shadow-lg transition-shadow
Section:     py-16 sm:py-24 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto
Footer:      bg-slate-900 text-slate-400 py-12
```

### Notes Implementor
- Welcome.vue saat ini tidak pakai AppLayout — pertahankan standalone
- Logo sekolah harus disiapkan di `public/images/logo-smk-bm.png` (beberapa ukuran)
- Hero background bisa pakai CSS art / SVG pattern — jangan image berat
- Responsive: di mobile, hero text `text-3xl`, feature grid jadi 1 kolom
- Jika user belum login, tampilkan CTA. Jika sudah login, redirect ke dashboard

**Estimated effort:** Medium

---

## 3. Login Page

### Layout Description

Menggunakan `AuthSplitLayout.vue` — split horizontal dua kolom.

**Kiri (55% width) — Branding Panel:**
- Background gradient: `bg-gradient-to-br from-primary to-blue-800`
- Logo SMK Bina Mandiri tengah (96px)
- Nama sekolah (`text-3xl font-bold text-white`)
- Tagline ("Sistem Pembelajaran & Ujian Digital")
- Subtle decorative elements (concentric circles, dots pattern — CSS only)
- Hidden di mobile (`hidden lg:flex`)

**Kanan (45% width) — Form Panel:**
- Background putih, centered content `max-w-sm mx-auto`
- Heading: "Masuk ke Akun" (`text-2xl font-bold`)
- Subheading: "Masukkan kredensial Anda untuk melanjutkan" (`text-muted-foreground`)
- **Username field:** Label "NIP / NIS / Username", icon `User` di kiri (input group), `h-11`
- **Password field:** Label "Kata Sandi", icon `Lock` di kiri, toggle show/hide `Eye`/`EyeOff` di kanan, `h-11`
- **Remember me:** Checkbox + label "Ingat saya"
- **Submit button:** Full width, `h-12`, "Masuk" dengan `Spinner` saat loading
- **Forgot password link:** Di bawah form, `text-sm text-primary hover:underline`
- **Error display:** `AlertError` component di atas form (merah, deskriptif)
- Di mobile: form full width tanpa split, logo kecil di atas form

### Komponen shadcn-vue
`Button`, `Input`, `Label`, `Checkbox`, `Alert`, `Spinner`

### Tailwind Classes Kunci
```
Split container:  flex min-h-screen
Brand panel:      hidden lg:flex lg:w-[55%] bg-gradient-to-br from-primary to-blue-800 items-center justify-center
Form panel:       w-full lg:w-[45%] flex items-center justify-center p-8
Form card:        w-full max-w-sm space-y-6
Input group:      relative — icon absolute left-3 top-1/2 -translate-y-1/2
Error:            bg-red-50 border border-red-200 rounded-lg p-4 text-red-700
```

### Notes Implementor
- Saat ini `AuthSplitLayout.vue` sudah ada — extend, jangan buat baru
- Password show/hide toggle gunakan `PasswordInput.vue` yang sudah ada
- Error harus deskriptif Bahasa Indonesia: "Email atau kata sandi salah" bukan "Invalid credentials"
- Input focus: ring biru sesuai design system
- Mobile: branding panel collapse, form centered full width

**Estimated effort:** Easy

---

## 4. Sidebar & AppLayout

### Layout Description

Menggunakan shadcn `Sidebar` component system yang sudah ada (`AppSidebarLayout.vue`).

**Sidebar (280px expanded, 64px collapsed):**

**Header area:**
- Logo SMK Bina Mandiri (32px) + teks "SMK Bina Mandiri" (`text-sm font-bold`)
- Collapsed: hanya icon logo
- Border bottom separator

**Menu Groups dengan `SidebarGroup` + `SidebarGroupLabel`:**

#### Admin Menu (8+ items)
```
📊 Menu Utama
   ├── Dashboard                    (LayoutDashboard)
   
👥 Manajemen
   ├── Pengguna                     (Users)
   ├── Jurusan                      (GraduationCap)
   ├── Kelas                        (School)
   ├── Mata Pelajaran               (BookOpen)
   └── Tahun Akademik               (Calendar)

📋 Sistem
   ├── Kategori Forum               (MessageSquare)
   ├── Penyimpanan                   (HardDrive)
   ├── Pertukaran Data               (ArrowLeftRight)
   ├── Audit Log                     (FileText)
   └── Analitik                      (BarChart3)
```

#### Guru Menu (10+ items)
```
📊 Menu Utama
   ├── Dashboard                    (LayoutDashboard)
   ├── Kalender                     (Calendar)
   
📚 Pembelajaran
   ├── Materi                       (FileText)
   ├── Tugas                        (ClipboardList)
   ├── Pengumuman                   (Megaphone)
   
📝 Ujian
   ├── Bank Soal                    (Library)
   ├── Ujian                        (Monitor)
   ├── Penilaian                    (CheckCircle)
   
👥 Kelas
   ├── Presensi                     (UserCheck)
   ├── Forum                        (MessageCircle)
   └── File Manager                 (FolderOpen)
```

#### Siswa Menu (7+ items)
```
📊 Menu Utama
   ├── Dashboard                    (LayoutDashboard)
   ├── Kalender                     (Calendar)

📚 Pembelajaran
   ├── Materi                       (FileText)
   ├── Tugas                        (ClipboardList)
   ├── Pengumuman                   (Megaphone)

📝 Ujian & Nilai
   ├── Ujian                        (Monitor)
   ├── Nilai                        (Award)

👥 Lainnya
   ├── Presensi                     (UserCheck)
   └── Forum                        (MessageCircle)
```

**Active state:** `bg-primary/10 text-primary font-semibold border-r-2 border-primary` (atau `bg-sidebar-accent`)
**Hover state:** `bg-sidebar-accent/50 transition-colors`
**Group labels:** `text-xs font-semibold uppercase tracking-wider text-muted-foreground px-3 py-2`

**Nav Footer (`NavFooter.vue`):**
- User avatar (`Avatar` component) + nama + role badge kecil
- Klik → `DropdownMenu`: Profil Saya, Pengaturan, separator, Keluar (merah)
- Notification bell count di header area (bukan footer)

**Mobile (< 1024px):**
- Sidebar collapse ke `Sheet` (slide dari kiri)
- Hamburger button di header kiri
- Overlay backdrop saat terbuka

**Header Bar (`AppHeader.vue`):**
- Kiri: `SidebarTrigger` (hamburger) + `Breadcrumbs` component
- Kanan: Dark mode toggle, `NotificationBell` (icon + red dot unread count), user avatar dropdown

### Komponen shadcn-vue
`Sidebar` (full system), `Avatar`, `Badge`, `DropdownMenu`, `Sheet`, `Breadcrumb`, `Separator`, `Tooltip`, `Button`

### Tailwind Classes Kunci
```
Sidebar:        w-[280px] bg-sidebar border-r border-sidebar-border
Active item:    bg-primary/10 text-primary font-semibold
Group label:    text-xs font-semibold uppercase tracking-wider text-muted-foreground
Header bar:     h-16 border-b border-border bg-card px-4 flex items-center justify-between
User avatar:    w-8 h-8 rounded-full ring-2 ring-primary/20
```

### Notes Implementor
- `AppSidebar.vue` sudah ada dengan `NavMain.vue` — refactor menu items per role
- `NavUser.vue` sudah ada — enhance dengan role badge
- `Breadcrumbs.vue` sudah ada — pastikan setiap halaman pass breadcrumb items prop
- Menu items di-define di TypeScript, bukan hardcode di template
- Badge count (misalnya "3" di Penilaian untuk esai pending) via deferred props

**Estimated effort:** Medium

---

## 5. Admin Dashboard

### Layout Description

Page di dalam `AppLayout`. Content area single column.

**Page Header:**
- Title: "Dashboard Admin" (`text-2xl font-bold`)
- Subtitle: tanggal hari ini, Bahasa Indonesia format ("Senin, 24 Maret 2026")

**Stats Cards Row** — `grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4`

| Card            | Icon            | Color     | Data              |
|-----------------|-----------------|-----------|-------------------|
| Total Siswa     | `Users`         | Blue      | Angka besar bold  |
| Total Guru      | `GraduationCap` | Emerald   | Angka besar bold  |
| Ujian Aktif     | `Monitor`       | Amber     | Angka + "sedang berlangsung" |
| Login Hari Ini  | `Activity`      | Purple    | Angka + trend arrow |

Setiap card:
```
Card > CardContent p-6
  ├── Row: icon container (w-12 h-12 rounded-lg bg-{color}/10 flex items-center justify-center)
  │         + right-aligned: angka text-3xl font-bold
  └── Row: label text-sm text-muted-foreground + optional trend badge
```

**Alert: Ujian Aktif** — Jika ada ujian sedang berlangsung:
```
Alert variant="info" — icon Monitor, "2 ujian sedang berlangsung saat ini", link ke halaman Ujian
```
Prominent position, di bawah stats cards. `bg-blue-50 border-blue-200`.

**Main Content Grid** — `grid grid-cols-1 lg:grid-cols-3 gap-6`

**Kolom Kiri (2/3 — `lg:col-span-2`):**
1. **Pengumuman Terbaru** — Card dengan `CardHeader` ("Pengumuman Terbaru" + "Lihat Semua" link). List 5 item: judul + tanggal + badge status (Aktif/Draft). Empty state jika kosong.
2. **Audit Log Terbaru** — Card, list 10 aktivitas terakhir: avatar user + "User melakukan X" + timestamp relative. Scroll internal jika panjang.

**Kolom Kanan (1/3):**
1. **Quick Actions** — Card dengan tombol vertikal:
   - "Tambah Pengguna" (`UserPlus` icon)
   - "Kelola Kelas" (`School` icon)
   - "Lihat Analitik" (`BarChart3` icon)
   Semua tombol `variant="outline"` full width, `h-11`, icon + text.
2. **Info Sistem** — Card: tahun akademik aktif, semester, total storage used (progress bar).

### Komponen shadcn-vue
`Card`, `Badge`, `Button`, `Alert`, `Avatar`, `Progress`, `Separator`, `Skeleton` (loading)

### Tailwind Classes Kunci
```
Stats card:      bg-card rounded-xl border p-6 flex items-start justify-between
Icon container:  w-12 h-12 rounded-lg bg-blue-50 flex items-center justify-center
Stat number:     text-3xl font-bold text-foreground
Stat label:      text-sm text-muted-foreground mt-1
Section card:    bg-card rounded-xl border shadow-sm
Activity item:   flex items-start gap-3 py-3 border-b last:border-0
```

### Notes Implementor
- Deferred props untuk stats dan lists — tampilkan Skeleton loading
- Audit log items bisa pakai relative time (vue composable `useTimeAgo` atau library)
- Quick actions navigasi via Inertia `Link`
- Stats angka animasi count-up saat pertama load (optional, CSS transition)

**Estimated effort:** Medium

---

## 6. Guru Dashboard

### Layout Description

Page di dalam `AppLayout`. Personalized welcome + action-oriented.

**Page Header:**
- "Selamat Pagi, Bapak/Ibu {Nama}" (`text-2xl font-bold`) — greeting sesuai waktu
- Subtitle tanggal hari ini

**Stats Cards** — `grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4`

| Card              | Icon           | Color   | Data                     |
|-------------------|----------------|---------|--------------------------|
| Kelas Saya        | `School`       | Blue    | Jumlah kelas diampu      |
| Ujian Mendatang   | `Calendar`     | Amber   | Jumlah 7 hari ke depan   |
| Esai Pending      | `FileEdit`     | Red     | Angka + "perlu dinilai"  |
| Total Materi      | `FileText`     | Emerald | Angka materi diunggah    |

**Alert: Esai Pending** — Jika ada esai belum dinilai:
```
Alert variant="warning" — "Anda memiliki 12 jawaban esai yang belum dinilai", button "Nilai Sekarang"
```

**Main Grid** — `grid grid-cols-1 lg:grid-cols-3 gap-6`

**Kolom Kiri (2/3):**
1. **Ujian Aktif Real-time** — Card. Jika ada ujian sedang berlangsung: nama ujian, progress bar (siswa selesai/total), badge "BERLANGSUNG" (hijau pulsing dot), link ke Proctor. Jika tidak ada: "Tidak ada ujian berlangsung" muted text.
2. **Daftar Penilaian Pending** — Card. Table mini: nama ujian + kelas + jumlah belum dinilai + badge tipe (PG otomatis, Esai manual) + action "Nilai". Max 5 rows + "Lihat Semua".
3. **Presensi Hari Ini** — Card. Status per kelas hari ini: nama kelas + jam + badge (Sudah/Belum dibuka). Aktif: tombol "Buka Presensi".

**Kolom Kanan (1/3):**
1. **Quick Actions** — Card dengan 3 tombol besar:
   - "Buat Ujian Baru" (primary, `Plus` icon)
   - "Buat Materi" (outline, `FileText` icon)
   - "Buka Presensi" (outline, `UserCheck` icon)
   Semua full width `h-11`.
2. **Jadwal Mendatang** — Card. 3-5 event terdekat (ujian/tugas deadline) dengan tanggal, compact list.

### Komponen shadcn-vue
`Card`, `Badge`, `Button`, `Alert`, `Progress`, `Table`, `Separator`, `Skeleton`

### Tailwind Classes Kunci
```
Greeting:        text-2xl font-bold text-foreground
Welcome card:    bg-gradient-to-r from-primary to-blue-600 rounded-xl p-6 text-white (optional)
Pulsing dot:     w-2 h-2 rounded-full bg-emerald-500 animate-pulse
Quick action:    w-full h-11 text-sm font-semibold
```

### Notes Implementor
- Greeting berdasarkan jam: Pagi (<12), Siang (12-15), Sore (15-18), Malam (>18)
- Ujian aktif real-time: polling via Inertia polling atau WebSocket channel
- Esai pending count bisa dari deferred prop
- Presensi hari ini: query berdasarkan jadwal hari ini

**Estimated effort:** Medium

---

## 7. Siswa Dashboard

### Layout Description

Page di dalam `AppLayout`. Student-focused, info-at-a-glance.

**Page Header:**
- "Halo, {Nama}! 👋" (`text-2xl font-bold`)
- Subtitle: kelas + jurusan (misal: "XII RPL 1 — Rekayasa Perangkat Lunak")

**Stats Cards** — `grid grid-cols-1 sm:grid-cols-3 gap-4`

| Card             | Icon          | Color   | Data                     |
|------------------|---------------|---------|--------------------------|
| Ujian Mendatang  | `Calendar`    | Blue    | Jumlah + "ujian"         |
| Ujian Selesai    | `CheckCircle` | Emerald | Jumlah + "selesai"       |
| Rata-rata Nilai  | `Award`       | Amber   | Angka satu desimal + /100|

**Alert: Ujian Segera** — Jika ada ujian dalam 24 jam:
```
Alert variant="warning" — "Ujian Matematika dimulai dalam 2 jam 30 menit!", button "Lihat Detail"
```

**Alert: Remedial** — Jika ada remedial:
```
Alert variant="danger" — "Anda memiliki 2 ujian remedial yang harus dikerjakan", link
```

**Main Grid** — `grid grid-cols-1 lg:grid-cols-3 gap-6`

**Kolom Kiri (2/3):**
1. **Jadwal Ujian** — Card. List ujian mendatang: nama + mapel + tanggal/jam + countdown badge ("2 hari lagi", "3 jam lagi" — kuning jika <24 jam). Max 5 + "Lihat Semua".
2. **Deadline Tugas** — Card. List tugas belum dikumpulkan: nama tugas + mapel + deadline + status badge. Sorted by deadline terdekat.
3. **Materi Baru** — Card. 3-5 materi terbaru yang ditambahkan guru: judul + mapel + tanggal + icon tipe (PDF, video, teks). Link ke view.
4. **Nilai Terakhir** — Card. 5 nilai ujian terbaru: nama ujian + nilai (besar, color-coded: hijau ≥75, merah <75) + tanggal. Link ke detail.

**Kolom Kanan (1/3):**
1. **Profil Mini** — Card: avatar besar (64px), nama, NIS, kelas. Tombol "Lihat Profil".
2. **Rekap Kehadiran** — Card: mini donut chart atau progress bars: Hadir (hijau), Sakit (kuning), Izin (biru), Alpha (merah). Persentase kehadiran besar.
3. **Badge Remedial** — Jika ada: Card merah muted, count remedial, link ke daftar ujian remedial.

### Komponen shadcn-vue
`Card`, `Badge`, `Button`, `Alert`, `Avatar`, `Progress`, `Separator`, `Skeleton`

### Tailwind Classes Kunci
```
Countdown badge: text-xs font-semibold px-2 py-0.5 rounded-full bg-amber-50 text-amber-700
Nilai tinggi:    text-emerald-600 font-bold
Nilai rendah:    text-red-600 font-bold
Profile card:    text-center p-6
```

### Notes Implementor
- Countdown waktu ujian: computed dari `started_at` vs `now`, format relative
- Nilai color logic: `>= KKM = hijau`, `< KKM = merah`
- Rekap kehadiran bisa pakai `Progress` bar per status, atau SVG donut
- Badge remedial hanya muncul kalau ada data

**Estimated effort:** Medium

---

## 8. Admin CRUD Pages

### Pattern Umum (Berlaku untuk SEMUA admin CRUD pages)

Semua admin CRUD pages mengikuti pattern yang konsisten. Design sekali, apply ke semua.

#### Index Page Pattern

```
┌─────────────────────────────────────────────────────┐
│ Breadcrumb: Dashboard > [Resource]                  │
│                                                     │
│ [Page Title]                          [+ Tambah]    │
│ Subtitle deskripsi                                  │
│                                                     │
│ ┌─────────────────────────────────────────────────┐ │
│ │ 🔍 Cari...          [Filter ▼]  [Export ▼]     │ │
│ ├─────────────────────────────────────────────────┤ │
│ │ ☐  Nama        Email         Role    Aksi      │ │
│ │ ☐  Ahmad...    ahmad@...     Siswa   [⋯]       │ │
│ │ ☐  Budi...     budi@...      Guru    [⋯]       │ │
│ │    ...zebra striping...                         │ │
│ ├─────────────────────────────────────────────────┤ │
│ │ Menampilkan 1-10 dari 250    [< 1 2 3 ... >]   │ │
│ └─────────────────────────────────────────────────┘ │
│                                                     │
│ (Empty state jika data kosong:                      │
│  icon besar + "Belum ada data" + tombol tambah)     │
└─────────────────────────────────────────────────────┘
```

- **Search bar:** `Input` dengan `Search` icon, placeholder "Cari {resource}...", debounced 300ms
- **Filter:** `Popover` atau `Select` dropdown per field (role, status, dll)
- **DataTable:** TanStack Table, sortable headers (click toggle asc/desc), checkbox seleksi
- **Action column:** `DropdownMenu` trigger "⋯" — items: Lihat, Edit, Hapus (merah). BUKAN icon buttons.
- **Pagination:** `Pagination` component di bawah table
- **Empty state:** `div py-16 text-center` — Lucide icon `w-16 h-16 text-muted-foreground/50`, heading "Belum ada {resource}", description, button "Tambah {Resource}"

#### Create / Edit Page Pattern

```
┌─────────────────────────────────────────────────────┐
│ Breadcrumb: Dashboard > [Resource] > Tambah/Edit    │
│                                                     │
│ [Tambah/Edit Resource]                              │
│                                                     │
│ ┌──────────────────────────── max-w-2xl ──────────┐ │
│ │ Card                                             │ │
│ │   Label: Nama *                                  │ │
│ │   [Input field                              ]    │ │
│ │   Error: Nama wajib diisi                        │ │
│ │                                                  │ │
│ │   Label: Email *                                 │ │
│ │   [Input field                              ]    │ │
│ │                                                  │ │
│ │   Label: Role *                                  │ │
│ │   [Select dropdown                         ▼]    │ │
│ │                                                  │ │
│ │           [Batal]  [Simpan — spinner]             │ │
│ └──────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────┘
```

- Form dalam `Card` component, `max-w-2xl`
- Fields: `Label` (bold 14px) + `Input`/`Select`/`Textarea` + `InputError` underneath
- Required fields: label dengan `*` merah
- Action buttons: `Button variant="outline"` (Batal) + `Button variant="default"` (Simpan) — right aligned
- Loading state: Simpan button disabled + `Spinner`

#### Show / Detail Page Pattern

```
┌─────────────────────────────────────────────────────┐
│ Breadcrumb: Dashboard > [Resource] > [Nama]         │
│                                                     │
│ [Nama Resource]                   [Edit] [Hapus]    │
│                                                     │
│ ┌────── Card ────────────────────────────────────┐  │
│ │ Grid 2 kolom:                                   │ │
│ │   Label: Nama         Value: Ahmad Rizki         │ │
│ │   Label: Email        Value: ahmad@...           │ │
│ │   Label: Role         Badge: Siswa               │ │
│ │   ...                                            │ │
│ └────────────────────────────────────────────────┘  │
│                                                     │
│ ┌────── Related Data (Table/List) ───────────────┐  │
│ │ ...                                             │ │
│ └────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────┘
```

- Detail dalam Card, label-value grid (`grid grid-cols-1 sm:grid-cols-2 gap-4`)
- Label: `text-sm font-semibold text-muted-foreground`
- Value: `text-base text-foreground`

### Halaman-halaman Spesifik

#### 8.1 Users (Index, Create, Edit)
- **Index:** DataTable columns: Avatar+Nama, NIP/NIS, Email, Role (Badge color-coded), Status (Badge), Kelas, Aksi
- **Create/Edit:** Form fields: Nama, NIP/NIS, Email, Password (create only), Role (Select), Kelas (Select, conditional on Siswa), Jurusan, Status
- **Bulk actions:** Import Excel (button), Export (dropdown: Excel/CSV)
- `Admin/Users/Index.vue`, `Create.vue`, `Edit.vue`

#### 8.2 Academic Years (Index, Create, Edit)
- **Index:** DataTable: Nama (2024/2025), Semester, Status (Aktif badge hijau), Periode, Aksi
- **Create/Edit:** Nama, tanggal mulai, tanggal selesai, semester, set aktif (Switch)
- `Admin/AcademicYears/Index.vue`, `Create.vue`, `Edit.vue`

#### 8.3 Departments (Index, Create, Edit)
- **Index:** DataTable: Nama Jurusan, Kode, Jumlah Kelas (badge), Jumlah Siswa, Aksi
- **Create/Edit:** Nama, Kode, Deskripsi (Textarea)
- `Admin/Departments/Index.vue`, `Create.vue`, `Edit.vue`

#### 8.4 Classrooms (Index, Create, Edit, Show)
- **Index:** DataTable: Nama Kelas, Jurusan (badge), Tingkat (X/XI/XII), Wali Kelas, Jumlah Siswa, Aksi
- **Create/Edit:** Nama, Jurusan (Select), Tingkat (Select), Wali Kelas (Select), Tahun Akademik
- **Show:** Detail kelas + DataTable siswa anggota + tombol kelola siswa
- `Admin/Classrooms/Index.vue`, `Create.vue`, `Edit.vue`, `Show.vue`

#### 8.5 Subjects (Index, Create, Edit)
- **Index:** DataTable: Nama Mapel, Kode, Jurusan, Tingkat, Guru Pengampu, Aksi
- **Create/Edit:** Nama, Kode, Jurusan (Select multi), Tingkat (Checkbox group), Deskripsi
- `Admin/Subjects/Index.vue`, `Create.vue`, `Edit.vue`

#### 8.6 Forum Categories (Index — inline CRUD via Dialog)
- **Index:** Card grid atau simple table: Nama Kategori, Warna (color swatch), Icon, Jumlah Thread, Aksi
- CRUD via `Dialog` — form kecil: Nama, Warna (color picker atau predefined palette), Icon select, Deskripsi
- `Admin/ForumCategories/Index.vue`

#### 8.7 Storage (Index)
- **Dashboard layout:** Storage usage overview — total/used/free bar, breakdown per tipe file (images, documents, etc.)
- File browser below: list files, sort by size/date, delete old files
- `Admin/Storage/Index.vue`

#### 8.8 Data Exchange (Index)
- **Import/Export panel:** Tabs: Import | Export
- Import tab: drag-drop upload area, template download link, preview table before confirm, progress bar
- Export tab: pilih data (Users, Kelas, Nilai dll), format (Excel/CSV), date range, generate button
- `Admin/DataExchange/Index.vue`

#### 8.9 Audit Log (Index)
- **DataTable:** Timestamp, User (avatar+nama), Aksi (badge: Create/Update/Delete), Resource, Detail (collapsible JSON diff), IP Address
- **Filters:** date range, user, action type
- Infinite scroll atau pagination
- `Admin/AuditLog/Index.vue`

#### 8.10 Analytics (Index, ClassroomDetail)
- **Index:** Overview cards + chart area
  - Cards: Nilai rata-rata SMK, Tingkat kelulusan, Ujian bulan ini, Student per department pie chart
  - Charts: Line chart nilai tren per bulan, Bar chart per jurusan, Distribution histogram
  - Link per kelas → detail
- **ClassroomDetail:** Breakdown per siswa, score distribution, attendance chart
- `Admin/Analytics/Index.vue`, `ClassroomDetail.vue`

### Komponen shadcn-vue (semua CRUD)
`Card`, `Button`, `Input`, `Label`, `Select`, `Textarea`, `Switch`, `Checkbox`, `Table`, `Badge`, `DropdownMenu`, `Dialog`, `AlertDialog`, `Pagination`, `Skeleton`, `Separator`, `Breadcrumb`, `Tooltip`, `Tabs`, `Progress`

### Tailwind Classes Kunci
```
Page header:     flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6
Search bar:      relative w-full sm:w-80
Filter group:    flex items-center gap-2
Table wrapper:   bg-card rounded-xl border overflow-hidden
Table head:      bg-slate-50 text-xs font-semibold uppercase tracking-wider
Table row:       hover:bg-slate-50/50 border-b transition-colors
Empty state:     py-16 flex flex-col items-center justify-center text-center
Form card:       bg-card rounded-xl border shadow-sm p-6 max-w-2xl
Action buttons:  flex items-center justify-end gap-3 mt-6
```

### Notes Implementor
- Buat reusable pattern: `PageHeader` component (title + breadcrumb + action button)
- Buat reusable `DataTableToolbar` (search + filters + actions)
- Buat reusable `EmptyState` component (icon + message + CTA)
- Semua Delete actions harus via `AlertDialog` confirm: "Apakah Anda yakin ingin menghapus {item}? Tindakan ini tidak dapat dibatalkan."
- Pagination: server-side via Inertia, 10-25 items per page

**Estimated effort:** Hard (banyak halaman, tapi pattern reusable)

---

## 9. Guru Pages

### 9.1 Bank Soal

#### Index (`Guru/BankSoal/Index.vue`)
- **Layout:** Standard index pattern
- **DataTable columns:** Nama Bank Soal, Mapel (badge), Jumlah Soal, Tipe (badge multi), Terakhir Diedit, Aksi
- **Action:** Tambah Bank Soal, Import Soal
- **Filter:** Mapel, Tipe Soal

#### Create / Edit (`Guru/BankSoal/Create.vue`, `Edit.vue`)
- **Form:** Nama, Mapel (Select), Deskripsi (Textarea), Kelas tujuan (multi-select)
- Standard form card pattern

#### Show / Detail (`Guru/BankSoal/Show.vue`)
- **Header:** Nama bank soal, mapel badge, jumlah soal + button "Tambah Soal"
- **Tabs:** Semua Soal | Per Kompetensi
- **Soal list:** Card per soal — nomor, preview teks (truncated), tipe badge, difficulty badge, aksi (edit/hapus/duplicate)
- Drag-and-drop reorder (optional, bisa manual order number)
- **Empty:** "Belum ada soal. Tambahkan soal pertama Anda."

#### Kompetensi (`Guru/BankSoal/Kompetensi.vue`)
- Manage kompetensi/KD yang terkait bank soal
- Inline CRUD via Dialog

#### Soal Create / Edit (`Guru/BankSoal/Soal/Create.vue`, `Edit.vue`)
- **Form yang KOMPLEKS:**
  - Tipe Soal: `Select` (PG, B/S, Esai, Isian Singkat, Menjodohkan, Ordering, Multiple Answer)
  - Konten Soal: `TiptapEditor` (rich text — sudah ada component)
  - Media: Upload gambar/audio (optional)
  - Bobot Nilai: number input
  - Kompetensi: Select
  - **Dynamic section per tipe:**
    - **PG:** List opsi A-E, tiap opsi punya TiptapEditor mini, radio untuk kunci jawaban
    - **B/S:** Toggle Benar/Salah sebagai kunci
    - **Esai:** Kunci jawaban (Textarea) + rubrik penilaian (optional Textarea)
    - **Isian Singkat:** Input jawaban yang diterima (bisa multiple)
    - **Menjodohkan:** Dua kolom (kiri: pernyataan, kanan: jawaban), drag match atau dropdown
    - **Ordering:** Sortable list — urutan benar didefinisikan
    - **Multiple Answer:** Checkbox list opsi + multiple kunci jawaban
  - Preview soal: tab/section yang menampilkan bagaimana soal ditampilkan ke siswa

### Komponen shadcn-vue
`Card`, `Tabs`, `Badge`, `Button`, `Input`, `Label`, `Select`, `Textarea`, `Dialog`, `RadioGroup`, `Checkbox`, `Switch`, `DropdownMenu`, `Separator`, `Skeleton`

Custom components: `TiptapEditor`, `QuestionForm`

**Estimated effort:** Hard (7 tipe soal, form kompleks)

---

### 9.2 Ujian

#### Index (`Guru/Ujian/Index.vue`)
- **DataTable columns:** Nama Ujian, Mapel, Kelas, Tanggal, Durasi, Status (Badge: Draft/Dijadwalkan/Aktif/Selesai), Peserta, Aksi
- **Status badges:** Draft (abu), Dijadwalkan (biru), Aktif (hijau pulsing), Selesai (slate)
- **Filter:** Status, Mapel, Kelas
- **Actions per row:** DropdownMenu: Lihat, Edit, Duplikasi, Proctor (if active), Hapus

#### Create / Edit (`Guru/Ujian/Create.vue`, `Edit.vue`)
- **Multi-step form atau card sections:**
  1. **Info Dasar:** Nama, Mapel (Select), Kelas target (multi-select), Deskripsi
  2. **Pengaturan:** Tanggal mulai (datetime picker), Durasi (menit), Token akses (auto-generate + show), KKM, Acak soal (Switch), Acak opsi (Switch), Tampilkan hasil langsung (Switch)
  3. **Pilih Soal:** dari Bank Soal — DataTable checkbox select, preview soal, total bobot counter
  4. **Review:** summary sebelum publish
- Form bisa single long page ATAU `Tabs`/`Stepper` (prefer Tabs: "Info", "Pengaturan", "Soal", "Review")

#### Create Remedial (`Guru/Ujian/CreateRemedial.vue`)
- Mirip Create tapi pre-filled dari ujian asli
- Extra: filter siswa yang berhak remedial (nilai < KKM), auto-select soal

#### Show (`Guru/Ujian/Show.vue`)
- **Header:** Nama ujian, status badge, mapel badge
- **Info panel:** Grid detail (tanggal, durasi, KKM, token, kelas)
- **Tabs:** Soal ({count}) | Peserta ({count}) | Hasil
  - Soal tab: numbered list soal, preview, bobot
  - Peserta tab: DataTable siswa, status (Belum Mulai/Sedang Mengerjakan/Selesai), waktu submit, nilai
  - Hasil tab: statistik ringkas (rata-rata, min, max, distribusi), link ke penilaian detail

#### Proctor (`Guru/Ujian/Proctor.vue`) — **Real-time monitoring**
- **Header:** Nama ujian + timer server-side + badge "BERLANGSUNG"
- **Stats bar:** Online/Total siswa, Selesai, Rata-rata progress
- **Grid siswa:** (card grid `grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6`)
  - Per siswa card kecil: nama, progress bar, status indicator (green dot = online, red = offline/suspicious)
  - Click → popup detail: jawaban progress, timeline events, flag warnings
- **Activity feed sidebar:** Real-time log: "Ahmad berpindah tab", "Budi submit ujian", timestamps
- **Actions:** Force submit siswa tertentu, Broadcast pesan, Akhiri ujian
- WebSocket powered via Laravel Reverb

### Komponen shadcn-vue
`Card`, `Tabs`, `Badge`, `Button`, `Input`, `Select`, `Switch`, `Dialog`, `AlertDialog`, `Table`, `Progress`, `Checkbox`, `Separator`, `Skeleton`, `Tooltip`, `ScrollArea`

**Estimated effort:** Hard (Proctor real-time, multi-step form)

---

### 9.3 Penilaian

#### Index (`Guru/Penilaian/Index.vue`)
- **DataTable columns:** Nama Ujian, Kelas, Tanggal, Total Siswa, Dinilai/Total, Status (Badge: Pending/Proses/Selesai), Aksi
- Highlight baris dengan esai pending (baris kuning muted / badge merah "X esai pending")

#### Show (`Guru/Penilaian/Show.vue`)
- **Header:** Nama ujian, kelas, statistik ringkas
- **DataTable siswa:** Nama, Nilai, Status Penilaian, Waktu Submit, Aksi (Nilai Manual jika ada esai)
- **Summary cards top:** Rata-rata, Median, Tertinggi, Terendah, % Lulus KKM

#### Manual Grading (`Guru/Penilaian/ManualGrading.vue`)
- **Layout:** Full width, dedicated grading interface
- **Left panel (60%):** Jawaban siswa — per soal scrollable:
  - Nomor soal + tipe badge + bobot
  - Pertanyaan (read-only)
  - Jawaban siswa (highlighted)
  - **Grading area:** Number input (0 - bobot max), textarea feedback (optional)
  - Navigation: "< Soal Sebelumnya | Soal Berikutnya >"
- **Right panel (40%):** Info siswa (nama, kelas), progress grading (3/15 soal dinilai), total skor running, submit button
- Keyboard shortcuts: angka keys untuk skor, Enter next soal

#### Item Analysis (`Guru/Penilaian/ItemAnalysis.vue`)
- **Per-soal analysis:** Card per soal
  - Tingkat kesulitan (% benar) — color coded bar (hijau: mudah, kuning: sedang, merah: sulit)
  - Daya pembeda (discrimination index)
  - Distribusi jawaban (bar chart per opsi), mark jawaban benar
  - Efektivitas distraktor

#### Activity Log (`Guru/Penilaian/ActivityLog.vue`)
- **DataTable:** Siswa, Event (berpindah tab, kehilangan fokus, copy-paste attempt), Timestamp, Soal #
- **Filter:** Per siswa, per event type
- Timeline view per siswa (optional)

### Komponen shadcn-vue
`Card`, `Table`, `Badge`, `Button`, `Input`, `Textarea`, `Progress`, `Tabs`, `Separator`, `ScrollArea`, `Tooltip`, `Skeleton`

**Estimated effort:** Hard (Manual Grading interface, Item Analysis charts)

---

### 9.4 Materi

#### Index (`Guru/Materi/Index.vue`)
- **DataTable columns:** Judul, Mapel (badge), Kelas Target, Tipe (badge: Teks/PDF/Video/Link), Tanggal Upload, Status (Draft/Published), Aksi
- **View toggle:** Table view (default) atau Card grid view (visual)
- **Filter:** Mapel, Tipe, Status

#### Create / Edit (`Guru/Materi/Create.vue`, `Edit.vue`)
- **Form:** Judul, Mapel (Select), Kelas target (multi-select), Tipe (Select)
- **Dynamic content per tipe:**
  - Teks: TiptapEditor (full rich text)
  - PDF: File upload drag-drop zone, preview
  - Video: YouTube URL input + embed preview (`useYouTubeEmbed` composable sudah ada)
  - Link: URL input
- **Options:** Status (Draft/Published Switch), Urutan

#### Show (`Guru/Materi/Show.vue`)
- Read-only view: header + content rendered berdasarkan tipe
- Info sidebar: mapel, kelas, tanggal, download count

### Komponen shadcn-vue
`Card`, `Tabs`, `Badge`, `Button`, `Input`, `Select`, `Textarea`, `Switch`, `Dialog`, `Separator`

Custom: `TiptapEditor`

**Estimated effort:** Medium

---

### 9.5 Tugas

#### Index (`Guru/Tugas/Index.vue`)
- **DataTable:** Judul, Mapel, Kelas, Deadline (badge warna: merah jika lewat, kuning jika <24jam), Pengumpulan (x/total), Status, Aksi

#### Create / Edit (`Guru/Tugas/Create.vue`, `Edit.vue`)
- **Form:** Judul, Deskripsi (TiptapEditor), Mapel, Kelas, Deadline (datetime), Nilai Maksimum, Allow Late Submission (Switch), Attachment upload (multiple file)

#### Show (`Guru/Tugas/Show.vue`)
- **Header + info section**
- **Tabs:** Deskripsi | Pengumpulan ({count}) | Penilaian
  - Pengumpulan: DataTable siswa, status (Dikumpulkan/Belum/Terlambat), file attachment, action "Nilai"
  - Penilaian: grading per siswa — nilai input + feedback textarea

### Komponen shadcn-vue
`Card`, `Tabs`, `Badge`, `Button`, `Input`, `Textarea`, `Select`, `Switch`, `Table`, `Dialog`, `Separator`

**Estimated effort:** Medium

---

### 9.6 Presensi

#### Index (`Guru/Presensi/Index.vue`)
- **Daftar sesi presensi:** Card/table per tanggal — kelas, mapel, jam, status (Dibuka/Ditutup), jumlah hadir
- **Filter:** Kelas, Tanggal range

#### Create (`Guru/Presensi/Create.vue`)
- **Form:** Kelas (Select), Mapel (Select — auto dari jadwal), Tanggal, Jam
- After create → redirect ke Show (pengisian)

#### Show (`Guru/Presensi/Show.vue`)
- **DataTable siswa:** Foto, Nama, NIS, Status (RadioGroup: Hadir/Sakit/Izin/Alpha), Keterangan (input)
- Quick action: "Semua Hadir" button
- Save & close

#### Recap (`Guru/Presensi/Recap.vue`)
- **Filter:** Kelas, bulan
- **Rekap table:** Nama siswa × tanggal grid (tabel besar). Cell = icon status (H/S/I/A, color coded)
- **Summary row bottom:** total per status
- **Export:** button export Excel

### Komponen shadcn-vue
`Card`, `Table`, `Badge`, `Button`, `RadioGroup`, `Input`, `Select`, `Dialog`, `Separator`, `Tooltip`

**Estimated effort:** Medium (Recap table complex layout)

---

### 9.7 Pengumuman

#### Index, Create, Edit (`Guru/Pengumuman/Index.vue`, `Create.vue`, `Edit.vue`)
- Standard CRUD pattern
- **Fields:** Judul, Konten (TiptapEditor), Target (Select: Semua/Kelas tertentu), Status (Draft/Published), Pin (Switch)
- **Index:** Card list atau DataTable, pinned items di atas dengan pin icon

**Estimated effort:** Easy

---

### 9.8 Forum Guru (`Guru/Forum/Index.vue`, `Show.vue`)
- Sama dengan Forum Sekolah pattern (Section 12) — tapi scoped ke kelas guru
- Index: list threads dari kelas guru
- Show: thread detail + replies

**Estimated effort:** Easy (reuse Forum components)

---

### 9.9 File Manager (`Guru/FileManager/Index.vue`)
- **Layout:** Sidebar folder tree + content area file grid/list
- **Toolbar:** Upload button, Create Folder, View toggle (grid/list), Sort, Search
- **File card (grid view):** thumbnail/icon, nama, ukuran, tanggal
- **File row (list view):** icon, nama, ukuran, tanggal, aksi
- **Actions per file:** Download, Rename, Move, Delete
- **Upload:** Drag-drop zone, progress bar, multiple files

### Komponen shadcn-vue
`Card`, `Button`, `Input`, `DropdownMenu`, `Dialog`, `AlertDialog`, `Progress`, `Separator`, `ScrollArea`, `Breadcrumb`

**Estimated effort:** Medium

---

### 9.10 Kalender Guru (`Guru/Kalender.vue`)
- Sama dengan Kalender global (Section 14) — events filtered ke guru

**Estimated effort:** Easy (reuse Calendar components)

---

## 10. Siswa Pages

### 10.1 Ujian Siswa

#### Index (`Siswa/Ujian/Index.vue`)
- **Tabs:** Mendatang | Aktif | Riwayat
- **Card per ujian** (bukan DataTable — more visual):
  - Nama ujian, mapel badge, guru, tanggal/jam
  - Durasi, jumlah soal
  - Status badge besar (Dijadwalkan/Aktif — hijau pulsing/Selesai)
  - Action button: "Kerjakan" (primary, aktif), "Lihat Hasil" (outline, selesai)
  - Countdown jika mendatang
- **Aktif tab:** Jika ada ujian yang bisa dikerjakan — card highlighted, CTA besar

#### Verify Token (`Siswa/Ujian/VerifyToken.vue`)
- **Centered layout** (mirip login — narrow card centered):
  - Nama ujian, mapel, durasi info
  - Input token: `InputOTP` (6 digit) atau `Input` biasa, besar `text-center text-2xl tracking-widest`
  - Error: "Token salah. Minta token dari guru."
  - Button: "Mulai Ujian" (primary, `h-12`)
  - Info: "Setelah memulai, timer akan berjalan. Pastikan Anda siap."

#### Exam Interface → Section 11 (detail terpisah)

### Komponen shadcn-vue
`Card`, `Badge`, `Button`, `Tabs`, `InputOTP` / `Input`, `Alert`, `Separator`, `Skeleton`

**Estimated effort:** Medium

---

### 10.2 Materi Siswa

#### Index (`Siswa/Materi/Index.vue`)
- **Card grid** (`grid-cols-1 sm:grid-cols-2 lg:grid-cols-3`):
  - Per materi: icon tipe (besar), judul, mapel badge, guru, tanggal
  - Click → Show
- **Filter:** Mapel, Tipe
- **Search bar**

#### Show (`Siswa/Materi/Show.vue`)
- Read-only content: rendered berdasarkan tipe (teks, embedded PDF, YouTube embed, external link)
- Breadcrumb navigasi
- Sidebar/bottom: materi lain dari mapel yang sama

### Komponen shadcn-vue
`Card`, `Badge`, `Button`, `Input`, `Select`, `Separator`, `Skeleton`

**Estimated effort:** Easy

---

### 10.3 Tugas Siswa

#### Index (`Siswa/Tugas/Index.vue`)
- **Card list per tugas:**
  - Judul, mapel badge, guru, deadline (countdown badge), status (Belum/Dikumpulkan/Dinilai/Terlambat)
  - Nilai jika sudah dinilai

#### Show (`Siswa/Tugas/Show.vue`)
- **Deskripsi tugas** (read-only rich text)
- **Attachment tugas** dari guru (download links)
- **Form pengumpulan:** File upload zone (drag-drop, max size indicator), textarea catatan, submit button
- **Jika sudah dikumpulkan:** tampilkan file yang diupload, timestamp, nilai & feedback dari guru

### Komponen shadcn-vue
`Card`, `Badge`, `Button`, `Textarea`, `Alert`, `Progress`, `Separator`

**Estimated effort:** Easy

---

### 10.4 Nilai Siswa

#### Index (`Siswa/Nilai/Index.vue`)
- **Rekap nilai** per mapel: DataTable — Mapel, Nilai Rata-rata, Ujian Terakhir, Status (Lulus/Belum)
- **Summary cards top:** Rata-rata Keseluruhan, Ranking (jika tersedia), Total Ujian

#### Show (`Siswa/Nilai/Show.vue`)
- **Detail per ujian:** Nama, tanggal, nilai besar (color-coded), KKM, status
- **Detail per soal:** Benar/Salah indicator per soal, review jawaban (jika diizinkan guru)

### Komponen shadcn-vue
`Card`, `Table`, `Badge`, `Progress`, `Separator`

**Estimated effort:** Easy

---

### 10.5 Pengumuman Siswa (`Siswa/Pengumuman/Index.vue`, `Show.vue`)
- **Index:** Card list, pinned di atas, sorted by date
- **Show:** Full content read-only
- Standard read pattern

**Estimated effort:** Easy

---

### 10.6 Presensi Siswa (`Siswa/Presensi/Index.vue`)
- **Rekap kehadiran pribadi:**
  - Summary cards: Total Hadir, Sakit, Izin, Alpha + persentase
  - Calendar mini: color dots per hari (H=hijau, S=kuning, I=biru, A=merah)
  - DataTable detail: tanggal, mapel, status, keterangan

### Komponen shadcn-vue
`Card`, `Badge`, `Table`, `Separator`, `Progress`

**Estimated effort:** Easy

---

### 10.7 Forum Siswa (`Siswa/Forum/Index.vue`, `Show.vue`)
- Sama dengan Forum pattern (Section 12) — scoped ke kelas siswa
- Bisa buat thread baru, reply

**Estimated effort:** Easy (reuse)

---

### 10.8 Kalender Siswa (`Siswa/Kalender.vue`)
- Sama pattern kalender (Section 14) — events filtered ke siswa

**Estimated effort:** Easy (reuse)

---

## 11. CBT Exam Interface (PRIORITAS TERTINGGI)

### Layout Description

**FULL SCREEN layout — BUKAN di dalam AppLayout.** Tanpa sidebar, tanpa header biasa. Layout khusus exam-only.

File: `Siswa/Ujian/ExamInterface.vue` — sudah ada, perlu redesign total.

```
┌──────────────────────────────────────────────────────────────────┐
│ HEADER BAR (h-14, bg-white, border-b, shadow-sm, sticky top-0)  │
│ ┌──────────────┬───────────────────┬───────────────────────────┐ │
│ │ Nama Ujian   │   ⏱ 01:23:45     │    [🔖 Flag] [✓ Selesai] │ │
│ │ Mapel badge  │   timer besar     │                           │ │
│ └──────────────┴───────────────────┴───────────────────────────┘ │
├──────────────────────────────────────┬───────────────────────────┤
│                                      │                           │
│  QUESTION AREA (scrollable)          │  NAVIGATION PANEL (w-72)  │
│                                      │  (sticky, scrollable)     │
│  ┌────────────────────────────────┐  │                           │
│  │ Soal 5 / 40    [Badge: PG]    │  │  Grid nomor soal:         │
│  │                                │  │  ┌──┬──┬──┬──┬──┐        │
│  │ Teks soal 18px, line-height   │  │  │1 │2 │3 │4 │5 │        │
│  │ generous (1.75), max-w-3xl    │  │  ├──┼──┼──┼──┼──┤        │
│  │                                │  │  │6 │7 │8 │9 │10│        │
│  │ [Gambar soal jika ada]        │  │  ├──┼──┼──┼──┼──┤        │
│  │                                │  │  │..│..│..│..│..│        │
│  │ Opsi jawaban:                 │  │  └──┴──┴──┴──┴──┘        │
│  │ ○ A. Lorem ipsum dolor sit    │  │                           │
│  │ ○ B. Consectetur adipiscing   │  │  Legend:                  │
│  │ ● C. Sed do eiusmod tempor    │  │  ⬜ Belum dijawab        │
│  │ ○ D. Ut labore et dolore      │  │  🟦 Sudah dijawab        │
│  │ ○ E. Magna aliqua             │  │  🟨 Ditandai (flag)      │
│  │                                │  │  🟩 Soal saat ini        │
│  │ [◀ Sebelumnya]  [Berikutnya ▶]│  │                           │
│  └────────────────────────────────┘  │  ┌─────────────────────┐ │
│                                      │  │ Dijawab: 25/40      │ │
│                                      │  │ Ditandai: 3         │ │
│                                      │  │ Belum: 12           │ │
│                                      │  └─────────────────────┘ │
├──────────────────────────────────────┴───────────────────────────┤
│ FOOTER (h-10, bg-white, border-t)                                │
│ Auto-save: ✓ Tersimpan 5 detik lalu          Soal 5 dari 40     │
└──────────────────────────────────────────────────────────────────┘
```

### Header Bar Detail

```
Kiri:     Nama ujian (text-base font-semibold truncate max-w-xs) + badge mapel
Tengah:   Timer — BESAR DAN BOLD
          Normal:     text-2xl font-bold text-foreground, bg-white
          < 5 menit: text-2xl font-bold text-amber-600, bg-amber-50 px-4 py-1 rounded-lg animate-pulse
          < 1 menit: text-2xl font-bold text-red-600, bg-red-50 px-4 py-1 rounded-lg animate-pulse
Kanan:    Button "Tandai" (outline, Flag icon) + Button "Selesai" (primary, CheckCircle icon)
Mobile:   Timer centered top, name hidden di bawah, buttons icon-only dengan tooltip
```

### Question Area Detail

**Card putih** di atas background `bg-slate-50`:
```
bg-white rounded-xl shadow-sm border p-6 sm:p-8 max-w-3xl mx-auto
```

**Soal header:**
- "Soal 5 / 40" (`text-sm font-semibold text-muted-foreground`)
- Badge tipe soal kanan atas (PG, Esai, dll)
- Separator di bawah

**Konten soal:**
- `text-lg leading-relaxed text-foreground` (18px body, line-height 1.75)
- Gambar/media: `max-w-full rounded-lg border mt-4 mb-6`
- Rich text di-render dari HTML (dari TiptapEditor)

**Opsi Jawaban per tipe soal:**

#### Pilihan Ganda (PG)
```
Setiap opsi:
  button full-width, text-left
  bg-white border border-border rounded-lg p-4 mb-3
  hover: bg-slate-50 border-primary/30
  selected: bg-primary/5 border-primary ring-2 ring-primary/20
  
  Layout: radio circle kiri (w-5 h-5) + label huruf (A.) bold + teks opsi
  Radio size: w-5 h-5 (besar, mudah diklik)
  Min height: 52px per opsi
  Spacing: mb-3 antar opsi
```

#### Benar / Salah (B/S)
```
Dua tombol besar horizontal:
  grid grid-cols-2 gap-4
  Per tombol: h-16, text-lg font-semibold, rounded-xl
  Benar: border-emerald-300, hover bg-emerald-50, selected bg-emerald-500 text-white
  Salah: border-red-300, hover bg-red-50, selected bg-red-500 text-white
```

#### Esai
```
Textarea besar:
  min-h-[200px] text-base p-4 rounded-lg border
  Placeholder: "Tulis jawaban Anda di sini..."
  Character/word count bottom right: text-xs text-muted-foreground
  Auto-resize optional
```

#### Isian Singkat
```
Input besar:
  h-14 text-lg text-center rounded-lg border
  Placeholder: "Ketik jawaban singkat..."
  Max length indicator
```

#### Menjodohkan
```
Layout dua kolom:
  Kiri: list pernyataan (numbered, read-only card)
  Kanan per pernyataan: Select dropdown pilih pasangan
  
  Pernyataan card: bg-slate-50 rounded-lg p-3, text-base
  Select: h-11, full width
  Connected by line/arrow visual (optional CSS)
```

#### Ordering (Urutan)
```
Sortable list:
  Setiap item: card draggable
  bg-white border rounded-lg p-4 flex items-center gap-3
  Handle drag (GripVertical icon) kiri
  Nomor urut (auto) + teks item
  Hover: shadow-md, cursor-grab
  Dragging: shadow-lg ring-2 ring-primary/30 opacity-80
  
  Library: @vueuse/integrations useSortable atau vue-draggable-plus
```

#### Multiple Answer (Checkbox)
```
Mirip PG tapi dengan checkbox:
  Setiap opsi: checkbox (w-5 h-5) + label + teks
  Bisa pilih lebih dari satu
  Selected: bg-primary/5 border-primary ring-2 ring-primary/20
  Instruksi: "Pilih semua jawaban yang benar" (text-sm italic text-muted-foreground, di atas opsi)
```

### Navigation Panel Detail (Sidebar Kanan)

```
w-72, bg-white, border-l, sticky top-14, overflow-y-auto
p-4

Heading: "Navigasi Soal" text-sm font-semibold

Grid: grid grid-cols-5 gap-2

Button per soal: w-10 h-10 rounded-lg text-sm font-semibold
  - Belum dijawab:   bg-slate-100 text-slate-500 border border-slate-200
  - Sudah dijawab:   bg-primary text-white
  - Ditandai (flag): bg-amber-400 text-white
  - Soal saat ini:   ring-2 ring-primary ring-offset-2 (tambahan di atas state lain)

Legend di bawah grid:
  flex flex-wrap gap-x-4 gap-y-2 text-xs text-muted-foreground mt-4
  Setiap legend: colored dot + label

Summary di bawah legend:
  Card bg-slate-50 rounded-lg p-3 mt-4
  "Dijawab: 25/40" — bold angka
  "Ditandai: 3"
  "Belum: 12"
```

**Mobile Navigation:**
- Nav panel collapse, pindah ke bottom sheet (slide up)
- Trigger: button "📋 25/40" di footer bar
- Sheet content: same grid layout

### Navigation Buttons

```
Di bawah opsi jawaban:
  flex items-center justify-between mt-8

  Button "◀ Sebelumnya" — variant="outline", h-11, disabled jika soal 1
  Button "Berikutnya ▶" — variant="default", h-11, disabled jika soal terakhir
  
  Keyboard: ArrowLeft/ArrowRight untuk navigasi
```

### Footer Bar

```
h-10 bg-white border-t px-4 flex items-center justify-between text-sm

Kiri:  Auto-save indicator
       ✓ hijau: "Tersimpan {x} detik lalu" (text-emerald-600)
       ⟳ loading: "Menyimpan..." + Spinner kecil (text-muted-foreground)  
       ✕ error: "Gagal menyimpan — mencoba ulang..." (text-red-500)

Kanan: "Soal 5 dari 40" (text-muted-foreground)
```

### Modal: Submit Confirmation

```
AlertDialog:
  Title: "Selesaikan Ujian?"
  Description: 
    "Anda telah menjawab 38 dari 40 soal.
     2 soal belum dijawab.
     3 soal ditandai untuk ditinjau.
     
     Apakah Anda yakin ingin mengumpulkan ujian?"
  
  Stats summary visual:
    3 badges: ✓ Dijawab 38 (biru) | ⚠ Belum 2 (merah) | 🔖 Ditandai 3 (kuning)
  
  Actions:
    "Kembali ke Ujian" (secondary) — kiri
    "Ya, Kumpulkan" (primary/danger) — kanan
```

### Modal: Warning Dialogs

**Tab switch warning:**
```
AlertDialog:
  Icon: AlertTriangle (amber, w-12 h-12, centered)
  Title: "Peringatan!"
  Description: "Anda berpindah tab atau jendela. Aktivitas ini tercatat oleh sistem.
               Pelanggaran ke-{n} dari maksimum {max}."
  Action: "Saya Mengerti" (primary)
```

**Fullscreen exit warning:**
```
AlertDialog:
  Title: "Kembali ke Mode Layar Penuh"
  Description: "Ujian harus dikerjakan dalam mode layar penuh."
  Action: "Aktifkan Layar Penuh" (primary)
```

**Time's up auto-submit:**
```
Dialog (non-dismissible):
  Icon: Clock (red, animated)
  Title: "Waktu Habis!"
  Description: "Jawaban Anda telah dikumpulkan secara otomatis."
  Action: "Lihat Hasil" (primary) — redirect ke result page
```

### Komponen shadcn-vue
`Button`, `Badge`, `RadioGroup`, `Checkbox`, `Textarea`, `Input`, `Select`, `AlertDialog`, `Dialog`, `ScrollArea`, `Sheet`, `Tooltip`, `Spinner`, `Progress`, `Separator`

Dibutuhkan (baru): `RadioGroup`

Custom components (existing, perlu redesign):
- `Exam/QuestionCard.vue` — per-question renderer
- `Exam/NavigationPanel.vue` — soal number grid
- `Exam/QuestionForm.vue` — answer input form per tipe

Composables (existing):
- `useExamState.ts` — reactive exam state
- `useExamTimer.ts` — countdown timer
- `useAutoSave.ts` — periodic save
- `useExamSecurity.ts` — tab switch detection, fullscreen

### Tailwind Classes Kunci

```
Exam layout:     min-h-screen bg-slate-50 flex flex-col
Header:          h-14 bg-white border-b shadow-sm sticky top-0 z-50 px-4 flex items-center
Timer normal:    text-2xl font-bold text-foreground tabular-nums
Timer warning:   text-2xl font-bold text-amber-600 bg-amber-50 px-4 py-1 rounded-lg animate-pulse
Timer danger:    text-2xl font-bold text-red-600 bg-red-50 px-4 py-1 rounded-lg animate-pulse
Content area:    flex flex-1 overflow-hidden
Question panel:  flex-1 overflow-y-auto p-4 sm:p-6
Question card:   bg-white rounded-xl shadow-sm border p-6 sm:p-8 max-w-3xl mx-auto
Nav panel:       w-72 bg-white border-l overflow-y-auto p-4 hidden lg:block
Nav button:      w-10 h-10 rounded-lg text-sm font-semibold transition-all
Option button:   w-full text-left p-4 rounded-lg border mb-3 transition-all cursor-pointer
Option selected: bg-primary/5 border-primary ring-2 ring-primary/20
Footer:          h-10 bg-white border-t px-4 flex items-center justify-between text-sm
```

### Notes Implementor
- **ZERO sidebar, ZERO AppLayout** — ExamInterface.vue menggunakan layout sendiri (tanpa `layout` Inertia property, atau custom exam layout)
- Timer harus `tabular-nums` font feature untuk angka fixed-width (tidak bergeser)
- Navigation panel: responsive — desktop di kanan, mobile di bottom sheet
- Keyboard navigation: ArrowLeft/Right soal, number keys direct jump, F flag toggle
- Auto-save visual cue harus selalu terlihat — penting untuk confidence siswa
- Semua 7 tipe soal harus punya render component masing-masing di dalam `QuestionCard`
- Content area `overflow-y-auto` — soal panjang bisa di-scroll
- PENTING: Performance — jangan heavy re-render saat navigasi soal. State sudah ada di composable.
- **Accessibility:** Focus trap dalam exam, semua opsi reachable via keyboard (Tab + Space/Enter)
- Test dengan 50+ soal untuk memastikan navigation panel tetap smooth

**Estimated effort:** Hard (interface prioritas utama, 7 tipe soal, real-time features)

---

## 12. Forum Sekolah

### Layout Description

Forum terbagi ke 3 lokasi: `Forum/` (publik/semua role), `Guru/Forum/`, `Siswa/Forum/`. Pattern sama, scope berbeda.

#### Index (`Forum/Index.vue`, `Guru/Forum/Index.vue`, `Siswa/Forum/Index.vue`)

```
┌─────────────────────────────────────────────────────┐
│ Breadcrumb: Dashboard > Forum                       │
│                                                     │
│ Forum Diskusi                    [+ Buat Thread]    │
│                                                     │
│ [🔍 Cari thread...]  [Kategori ▼]  [Sort ▼]        │
│                                                     │
│ ┌─────────────────────────────────────────────────┐ │
│ │ 📌 [Pengumuman] Thread title yang di-pin        │ │
│ │    Author Avatar + Nama + Role Badge | 5 balasan│ │
│ │    + 🔒 Locked indicator (jika locked)          │ │
│ ├─────────────────────────────────────────────────┤ │
│ │ [Diskusi] Thread title biasa                    │ │
│ │ Author Avatar + Nama + Role Badge | 12 balasan  │ │
│ │ Last reply: 2 jam lalu                          │ │
│ ├─────────────────────────────────────────────────┤ │
│ │ ...                                             │ │
│ └─────────────────────────────────────────────────┘ │
│                                                     │
│ [Pagination]                                        │
└─────────────────────────────────────────────────────┘
```

Per thread card:
- `CategoryBadge` (komponen existing — warna sesuai kategori)
- Title `text-base font-semibold hover:text-primary` — link
- Author: `Avatar` kecil (24px) + nama + role badge (Guru/Siswa/Admin, masing-masing warna)
- Reply count icon `MessageCircle` + angka
- Last reply timestamp relative
- Pinned: pin icon biru, card sedikit highlighted `bg-blue-50/50`
- Locked: lock icon, muted styling

#### Create (`Forum/Create.vue`)
- Form: Judul, Kategori (Select), Konten (TiptapEditor)
- Standard form card

#### Show / Thread Detail (`Forum/Show.vue`, `Guru/Forum/Show.vue`, `Siswa/Forum/Show.vue`)

```
┌─────────────────────────────────────────────────────┐
│ Breadcrumb: Forum > [Kategori] > [Title]            │
│                                                     │
│ [Kategori Badge] Thread Title Here                  │
│ 📌 Pinned  🔒 Locked (jika berlaku)                │
│                                                     │
│ ┌─────────────────────────────────────────────────┐ │
│ │ Avatar (40px) | Author Name [Guru Badge]        │ │
│ │               | 24 Maret 2026, 10:30            │ │
│ │                                                 │ │
│ │ Rich text content thread...                     │ │
│ │                                                 │ │
│ └─────────────────────────────────────────────────┘ │
│                                                     │
│ 12 Balasan                                          │
│ ─ separator ─                                       │
│                                                     │
│ ┌─────────────────────────────────────────────────┐ │
│ │ Avatar | Nama [Siswa Badge] | 2 jam lalu        │ │
│ │                                                 │ │
│ │ Reply content...                                │ │
│ └─────────────────────────────────────────────────┘ │
│                                                     │
│ ┌─────────────────────────────────────────────────┐ │
│ │ TiptapEditor: Tulis balasan...                  │ │
│ │                                      [Kirim]    │ │
│ └─────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────┘
```

### Komponen shadcn-vue
`Card`, `Badge`, `Button`, `Avatar`, `Input`, `Select`, `Separator`, `Pagination`, `Tooltip`

Custom: `Forum/CategoryBadge.vue`, `Forum/ThreadCard.vue`, `Forum/ReplyItem.vue`, `TiptapEditor`

### Tailwind Classes Kunci
```
Thread card:     bg-card rounded-xl border p-4 hover:shadow-sm transition-shadow cursor-pointer
Pinned card:     bg-blue-50/50 border-blue-100
Author row:      flex items-center gap-2
Role badge guru: bg-emerald-50 text-emerald-700 text-xs font-semibold px-2 py-0.5 rounded-full
Role badge siswa:bg-blue-50 text-blue-700 text-xs font-semibold px-2 py-0.5 rounded-full
Reply item:      border-l-2 border-primary/20 pl-4 py-4
Reply editor:    bg-card rounded-xl border p-4 mt-6
```

### Notes Implementor
- `CategoryBadge.vue`, `ThreadCard.vue`, `ReplyItem.vue` sudah ada — redesign styling
- TiptapEditor di reply form: mode minimal (bold, italic, link, list only)
- Pagination server-side
- Real-time reply via WebSocket (optional, bisa tanpa dulu)

**Estimated effort:** Medium

---

## 13. Profil & Settings

### Profil User (`Profile/Show.vue`)

```
┌─────────────────────────────────────────────────────┐
│ Breadcrumb: Dashboard > Profil                      │
│                                                     │
│ ┌─────────────────────────────────────────────────┐ │
│ │ Profile Header Card:                            │ │
│ │                                                 │ │
│ │  [Avatar 80px]  Nama Lengkap                    │ │
│ │                 [Role Badge] NIP/NIS             │ │
│ │                 Email                            │ │
│ │                                                 │ │
│ │                              [Edit Profil]      │ │
│ └─────────────────────────────────────────────────┘ │
│                                                     │
│ ┌── Grid 2 kolom ────────────────────────────────┐  │
│ │                                                 │  │
│ │ [Kiri: Info Detail]         [Kanan: Statistik]  │  │
│ │  Card info:                  Siswa: rekap nilai │  │
│ │   Kelas: XII RPL 1           + rekap kehadiran  │  │
│ │   Jurusan: RPL              Guru: mapel + kelas │  │
│ │   Tahun: 2024/2025           list                │  │
│ │   dll                                           │  │
│ └─────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────┘
```

- **Siswa profil:** Tambahan: tabel rekap nilai per mapel, chart/progress bar kehadiran
- **Guru profil:** Tambahan: list mapel yang diampu, kelas, statistik ujian
- **Admin profil:** Minimal, hanya info personal

### Settings Pages

Menggunakan `settings/Layout.vue` — tabs/sidebar navigasi:

#### Profile Settings (`settings/Profile.vue`)
- Upload foto: current avatar + button upload, preview, crop (optional)
- Edit nama, email
- Tombol Simpan

#### Password Settings (`settings/Password.vue`)
- Password lama, password baru, konfirmasi
- Strength indicator progress bar
- Tombol Simpan

#### Appearance (`settings/Appearance.vue`)
- Light/Dark/System toggle (sudah ada via `AppearanceTabs.vue`)
- Preview cards per mode
- Default: Light terpilih

#### Two Factor (`settings/TwoFactor.vue`)
- Status 2FA (aktif/tidak)
- Setup flow: QR code + InputOTP verify
- Recovery codes display + regenerate
- Komponen existing: `TwoFactorSetupModal.vue`, `TwoFactorRecoveryCodes.vue`

### Komponen shadcn-vue
`Card`, `Avatar`, `Badge`, `Button`, `Input`, `Label`, `Tabs`, `Switch`, `Progress`, `Dialog`, `InputOTP`, `Separator`

### Tailwind Classes Kunci
```
Profile header:  bg-card rounded-xl border p-6 flex items-center gap-6
Avatar large:    w-20 h-20 rounded-full ring-4 ring-primary/10
Info grid:       grid grid-cols-1 sm:grid-cols-2 gap-4
Setting card:    bg-card rounded-xl border p-6 max-w-2xl
Setting layout:  flex gap-8 — sidebar (w-56 nav) + content
```

### Notes Implementor
- `Profile/Show.vue` sudah ada — redesign layout
- Settings pages sudah ada semua — update styling
- Foto upload: simpan via server ke storage, tampilkan via `Avatar`
- Chart kehadiran di profil siswa bisa pakai simple CSS bars, tidak perlu chart library berat

**Estimated effort:** Medium

---

## 14. Kalender

### Layout Description

File: `Guru/Kalender.vue`, `Siswa/Kalender.vue`

```
┌─────────────────────────────────────────────────────┐
│ Breadcrumb: Dashboard > Kalender                    │
│                                                     │
│ Kalender                                            │
│                                                     │
│ ┌─────────────────────────────────────────────────┐ │
│ │  [< ]    Maret 2026    [ >]     [Hari Ini]     │ │
│ │                                                 │ │
│ │  Sen  Sel  Rab  Kam  Jum  Sab  Min             │ │
│ │  ┌────┬────┬────┬────┬────┬────┬────┐          │ │
│ │  │    │    │    │  1 │  2 │  3 │  4 │          │ │
│ │  │    │    │    │ 🔴 │    │    │    │          │ │
│ │  ├────┼────┼────┼────┼────┼────┼────┤          │ │
│ │  │  5 │  6 │  7 │  8 │  9 │ 10 │ 11 │          │ │
│ │  │    │ 🟡 │    │    │ 🔵 │    │    │          │ │
│ │  ├────┼────┼────┼────┼────┼────┼────┤          │ │
│ │  │ ...                                          │ │
│ │  └────┴────┴────┴────┴────┴────┴────┘          │ │
│ │                                                 │ │
│ │  Legend: 🔴 Ujian  🟡 Deadline  🔵 Presensi    │ │
│ └─────────────────────────────────────────────────┘ │
│                                                     │
│ (Klik tanggal → Popover list events)                │
└─────────────────────────────────────────────────────┘
```

**Month Navigator:** `MonthNavigator.vue` (existing) — "< Bulan Tahun >" + "Hari Ini" button

**Calendar Grid:** `CalendarGrid.vue` (existing)
- 7 kolom (Sen-Min), header `text-xs font-semibold uppercase text-muted-foreground`
- Per hari (`CalendarDay.vue`): nomor, event dots (max 3 visible)
- Hari ini: `bg-primary/10 font-bold text-primary` ring
- Hover: `bg-slate-50` cursor pointer
- Hari di luar bulan: `text-muted-foreground/30`

**Event Dots Color Coding:**
- 🔴 Merah: Ujian (`bg-red-500`)
- 🟡 Kuning: Deadline tugas (`bg-amber-500`)
- 🔵 Biru: Presensi/event lain (`bg-blue-500`)
- 🟢 Hijau: Materi baru (optional)

**Klik Day → Popover:**
- `Popover` component, aligned to day cell
- List events: icon color dot + nama event + jam + badge tipe
- Link ke detail masing-masing

### Komponen shadcn-vue
`Card`, `Button`, `Popover`, `Badge`, `Separator`

Custom: `Calendar/CalendarGrid.vue`, `Calendar/CalendarDay.vue`, `Calendar/MonthNavigator.vue`

### Tailwind Classes Kunci
```
Calendar grid:    grid grid-cols-7 border-t border-l
Day cell:        border-b border-r p-2 min-h-[80px] sm:min-h-[100px] relative
Today:           bg-primary/5 font-bold
Event dot:       w-2 h-2 rounded-full inline-block
Popover:         w-64 max-h-60 overflow-y-auto p-3
```

### Notes Implementor
- Calendar components sudah ada semua — cukup redesign styling
- Data events di-pass sebagai prop dari server (eager loaded per bulan)
- Mobile: calendar bisa switch ke list view (optional)
- `Popover` perlu shadcn-vue install tambahan

**Estimated effort:** Medium

---

## 15. Notifications

### Layout Description

#### Bell Icon + Dropdown (di `AppHeader.vue`)
```
NotificationBell component:
  Button ghost, relative:
    Bell icon (w-5 h-5)
    Unread count badge: absolute -top-1 -right-1, w-5 h-5, bg-red-500, text-white text-xs, rounded-full
    Jika 0 unread: badge hidden

  Klik → DropdownMenu (w-80, max-h-96, overflow-y-auto):
    Header: "Notifikasi" + "Tandai semua dibaca" link
    Separator
    List items (max 10):
      Per item: icon type + judul bold + deskripsi singkat + timestamp relative
      Unread: bg-primary/5 border-l-2 border-primary
      Read: bg-white
      Hover: bg-slate-50
      Click → navigate ke detail (via Inertia Link)
    Footer: "Lihat Semua Notifikasi" link → Notifications/Index.vue
```

#### Full Notifications Page (`Notifications/Index.vue`)
```
Page header: "Notifikasi" + button "Tandai Semua Dibaca"
Card list: semua notifikasi, paginated
Per item: icon + judul + deskripsi + timestamp + unread indicator
Filter: Semua | Belum Dibaca
Mark individual as read on click
```

### Notification Types & Icons
| Type              | Icon           | Color   |
|-------------------|----------------|---------|
| Ujian dijadwalkan | `Calendar`     | Blue    |
| Nilai keluar      | `Award`        | Emerald |
| Tugas baru        | `ClipboardList`| Amber   |
| Pengumuman        | `Megaphone`    | Purple  |
| Forum reply       | `MessageCircle`| Blue    |
| Presensi          | `UserCheck`    | Emerald |
| System            | `Info`         | Slate   |

### Komponen shadcn-vue
`DropdownMenu`, `Button`, `Badge`, `Card`, `Separator`, `ScrollArea`

Custom: `NotificationBell.vue` (existing — redesign)

### Tailwind Classes Kunci
```
Bell button:        relative p-2 rounded-lg hover:bg-accent
Unread badge:       absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center
Notification item:  flex items-start gap-3 p-3 rounded-lg hover:bg-slate-50 cursor-pointer transition-colors
Unread item:        bg-primary/5 border-l-2 border-primary
```

### Notes Implementor
- `NotificationBell.vue` sudah ada — tambahkan dropdown functionality
- Unread count dari server prop (deferred prop)
- Mark as read: PATCH request on click
- Real-time notifikasi baru via Laravel Reverb (optional — polling fallback)

**Estimated effort:** Medium

---

## 16. Error Pages

### 404 — Halaman Tidak Ditemukan

```
Centered layout (min-h-screen, flex center):
  "404" — text-8xl font-extrabold text-primary/20
  Heading: "Halaman Tidak Ditemukan" — text-2xl font-bold
  Description: "Maaf, halaman yang Anda cari tidak ada atau telah dipindahkan." — text-muted-foreground
  Button: "Kembali ke Beranda" — primary, h-11
  Logo SMK Bina Mandiri kecil di bawah
```

### 403 — Akses Ditolak

```
Same layout:
  "403" — text-8xl
  "Akses Ditolak"
  "Anda tidak memiliki izin untuk mengakses halaman ini. Hubungi administrator jika ini adalah kesalahan."
  Button: "Kembali ke Dashboard"
```

### 500 — Kesalahan Server

```
Same layout:
  "500"
  "Terjadi Kesalahan"
  "Maaf, terjadi masalah pada server. Tim kami telah diberitahu. Silakan coba lagi nanti."
  Button: "Muat Ulang Halaman" (onClick: window.location.reload())
  Secondary: "Kembali ke Beranda"
```

### Komponen shadcn-vue
`Button`

### Tailwind Classes Kunci
```
Container:   min-h-screen flex flex-col items-center justify-center text-center px-4
Error code:  text-8xl font-extrabold text-primary/20
Heading:     text-2xl font-bold text-foreground mt-4
Description: text-base text-muted-foreground mt-2 max-w-md
Button:      mt-8
```

### Notes Implementor
- Buat 3 file baru: `resources/js/pages/Error/404.vue`, `403.vue`, `500.vue`
- Atau handle via Inertia error handling (app.blade.php / HandleInertiaRequests middleware)
- Semua teks Bahasa Indonesia
- Logo sekolah untuk branding

**Estimated effort:** Easy

---

## 17. Global Polish & Patterns

### 17.1 Page Transitions (Inertia Progress Bar)

```
Inertia progress bar (NProgress atau custom):
  Color: var(--primary) (#2563EB)
  Height: 3px
  Position: fixed top
  Z-index: 9999
  
Konfigurasi di app.ts:
  createInertiaApp({
    progress: {
      color: '#2563EB',
      showSpinner: false,
    },
  })
```

### 17.2 Loading Skeletons

Pattern untuk setiap page type:

```
Stats skeleton:    grid 4, per card: Skeleton h-24 rounded-xl
Table skeleton:    header + 5 rows, per row: Skeleton h-12 rounded
Card list:         3-5 Skeleton cards h-32 rounded-xl
Form:              Skeleton fields h-10 rounded-lg
```

Gunakan `Skeleton` component (sudah ada). Tampilkan saat deferred props loading.

### 17.3 Toast Notifications (Sonner)

```
Position: top-right (kanan atas)
Component: Sonner (sudah ada di ui/sonner)

Success: icon CheckCircle hijau + pesan
Error: icon XCircle merah + pesan
Info: icon Info biru + pesan
Warning: icon AlertTriangle amber + pesan

Duration: 5 detik
Action: optional undo button

Konfigurasi di AppLayout:
  <Sonner position="top-right" :toastOptions="{ duration: 5000 }" />
```

### 17.4 Responsive Breakpoints

| Breakpoint | Width     | Layout                         |
|------------|-----------|--------------------------------|
| Mobile     | < 640px   | 1 kolom, sidebar hidden, hamburger |
| Tablet     | 640-1023px| 2 kolom grid, sidebar collapsed   |
| Desktop    | ≥ 1024px  | Full layout, sidebar expanded     |
| Wide       | ≥ 1280px  | Extra padding, wider content      |

**Mobile-specific:**
- Sidebar → Sheet (slide from left)
- DataTable → card list view (atau horizontal scroll)
- Stats grid → 2 kolom atau stack
- Exam nav panel → bottom sheet
- Forms → full width

### 17.5 Empty State Pattern

Reusable `EmptyState` component:

```vue
<EmptyState
  icon="FileText"
  title="Belum ada materi"
  description="Buat materi pertama untuk kelas Anda"
  actionLabel="Buat Materi"
  :actionHref="route('guru.materi.create')"
/>
```

```
Layout:
  py-16 flex flex-col items-center justify-center text-center
  Icon: w-16 h-16 text-muted-foreground/30 mb-4
  Title: text-lg font-semibold text-foreground
  Description: text-sm text-muted-foreground mt-1 max-w-sm
  Action: Button primary mt-4 (optional)
```

### 17.6 Button Loading State

```
Semua submit buttons:
  Normal:  label text
  Loading: Spinner (w-4 h-4, animate-spin) + "Menyimpan..." — disabled
  
Pattern:
  <Button :disabled="form.processing">
    <Spinner v-if="form.processing" class="w-4 h-4 mr-2" />
    {{ form.processing ? 'Menyimpan...' : 'Simpan' }}
  </Button>
```

### 17.7 Confirm Dialog Pattern

Semua aksi destruktif (hapus, akhiri ujian, force submit) — pattern:

```
AlertDialog:
  Header:
    AlertDialogTitle: "Hapus {Resource}?"
    AlertDialogDescription: "Tindakan ini tidak dapat dibatalkan. {Resource} "{nama}" akan dihapus permanen."
  Footer:
    AlertDialogCancel: "Batal"
    AlertDialogAction (destructive): "Ya, Hapus"
```

Wording selalu Bahasa Indonesia, deskriptif, menyebut nama item.

### 17.8 Breadcrumb Pattern

Setiap halaman WAJIB punya breadcrumb. Pattern:

```
Dashboard > [Section] > [Sub-page] > [Item Name]

Contoh:
  Dashboard > Pengguna                          (index)
  Dashboard > Pengguna > Tambah Pengguna        (create)
  Dashboard > Pengguna > Ahmad Rizki > Edit     (edit)
  Dashboard > Bank Soal > Matematika X > Soal 5 (deep nested)
```

Implementasi: pass `breadcrumbs` prop dari controller ke page, render via `Breadcrumbs.vue` component.

### 17.9 Focus & Keyboard Navigation

```
Focus ring:        ring-2 ring-primary/50 ring-offset-2 (sudah di outline-ring/50 base)
Tab order:         logical, top-to-bottom, left-to-right
Skip to content:   hidden link top of page, visible on focus
Modal focus trap:  shadcn Dialog/AlertDialog sudah handle
Form:              Enter submit pada field terakhir
DataTable:         keyboard navigable rows (optional)
```

### 17.10 Font Loading

```html
<!-- resources/views/app.blade.php -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
```

Update `app.css`:
```css
--font-sans: 'Inter', ui-sans-serif, system-ui, sans-serif, ...;
```

### Notes Implementor
- Buat komponen reusable: `PageHeader`, `EmptyState`, `StatsCard`, `StatusBadge`
- Pattern ini harus di-document sebagai component library internal
- Sonner sudah installed — pastikan position config di layout
- Progress bar Inertia sudah built-in — hanya perlu set color

**Estimated effort:** Medium (banyak small polish items)

---

## 18. Implementasi Roadmap

### Fase 1: Foundation (HARUS PERTAMA)
| Task | Effort | File(s) |
|------|--------|---------|
| CSS Variables & Color System | Easy | `app.css` |
| Font change (Inter) | Easy | `app.blade.php`, `app.css` |
| Install missing shadcn components | Easy | CLI commands |
| Buat reusable: `PageHeader`, `EmptyState`, `StatsCard`, `StatusBadge` | Medium | `components/` |
| Button variants baru (success, warning) | Easy | `ui/button/` |
| Badge variants baru | Easy | `ui/badge/` |

### Fase 2: Layout & Navigation
| Task | Effort | File(s) |
|------|--------|---------|
| Sidebar menu restructure per role | Medium | `AppSidebar.vue`, `NavMain.vue` |
| AppHeader redesign + breadcrumb | Easy | `AppHeader.vue`, `Breadcrumbs.vue` |
| NotificationBell dropdown | Medium | `NotificationBell.vue` |
| Mobile responsive sidebar (Sheet) | Easy | Already built-in shadcn sidebar |

### Fase 3: Auth & Public
| Task | Effort | File(s) |
|------|--------|---------|
| Login page redesign | Easy | `auth/Login.vue`, `AuthSplitLayout.vue` |
| Welcome page redesign | Medium | `Welcome.vue` |
| Error pages (404, 403, 500) | Easy | New files |

### Fase 4: Dashboards
| Task | Effort | File(s) |
|------|--------|---------|
| Admin Dashboard redesign | Medium | `Admin/Dashboard.vue` |
| Guru Dashboard redesign | Medium | `Guru/Dashboard.vue` |
| Siswa Dashboard redesign | Medium | `Siswa/Dashboard.vue` |

### Fase 5: CBT Exam Interface (PRIORITAS TINGGI)
| Task | Effort | File(s) |
|------|--------|---------|
| Exam layout (header, footer, panels) | Hard | `ExamInterface.vue` |
| Question type renderers (semua 7 tipe) | Hard | `Exam/QuestionCard.vue`, `QuestionForm.vue` |
| Navigation panel redesign | Medium | `Exam/NavigationPanel.vue` |
| Timer visual states | Easy | `useExamTimer.ts` integration |
| Submit/Warning modals | Medium | `ExamInterface.vue` |
| Mobile exam responsive | Medium | All exam components |

### Fase 6: Admin CRUD Pages
| Task | Effort | File(s) |
|------|--------|---------|
| Apply DataTable pattern (semua index pages) | Medium | 10+ Index.vue files |
| Apply Form pattern (semua create/edit pages) | Medium | 15+ Create/Edit.vue files |
| Apply Detail pattern (semua show pages) | Easy | 5+ Show.vue files |
| Empty states everywhere | Easy | Reuse EmptyState component |

### Fase 7: Guru Pages
| Task | Effort | File(s) |
|------|--------|---------|
| Bank Soal pages (termasuk 7 tipe soal form) | Hard | 7 files |
| Ujian pages (termasuk Proctor) | Hard | 6 files |
| Penilaian pages (termasuk Manual Grading) | Hard | 5 files |
| Materi, Tugas, Pengumuman, Presensi | Medium | 15+ files |
| File Manager, Forum, Kalender | Medium | 4 files |

### Fase 8: Siswa Pages
| Task | Effort | File(s) |
|------|--------|---------|
| Ujian pages (index, token verify) | Medium | 3 files |
| Nilai, Materi, Tugas, Pengumuman | Easy | 8 files |
| Presensi, Forum, Kalender | Easy | 4 files |

### Fase 9: Remaining
| Task | Effort | File(s) |
|------|--------|---------|
| Forum pages redesign | Medium | 3 files |
| Profile & Settings | Medium | 5 files |
| Kalender redesign | Medium | 3 component files |
| Notifications page | Easy | 1 file |

### Fase 10: Global Polish
| Task | Effort | File(s) |
|------|--------|---------|
| Inertia progress bar color | Easy | `app.ts` |
| Sonner toast position | Easy | Layout |
| Loading skeletons everywhere | Medium | All pages with deferred props |
| Keyboard navigation audit | Easy | Global |
| Mobile responsive audit (semua pages) | Medium | All pages |
| WCAG contrast audit | Easy | All colors |
| Cross-browser testing | Easy | — |

---

## Ringkasan Effort

| Fase | Halaman / Item | Effort |
|------|---------------|--------|
| 1. Foundation | Design system, tokens, reusables | Medium |
| 2. Layout | Sidebar, header, notices | Medium |
| 3. Auth & Public | Login, Welcome, Error | Easy-Medium |
| 4. Dashboards | 3 dashboard pages | Medium |
| 5. CBT Exam | 1 complex page + 7 question types | **Hard** |
| 6. Admin CRUD | ~15 pages (pattern-based) | Medium |
| 7. Guru Pages | ~38 pages | **Hard** |
| 8. Siswa Pages | ~19 pages | Easy-Medium |
| 9. Remaining | Forum, Profile, Calendar, Notif | Medium |
| 10. Polish | Global patterns, responsive, a11y | Medium |

**Total pages affected: ~95 Vue files + 5-8 new reusable components**
**Critical path: Fase 1 → 2 → 5 (Foundation → Layout → CBT Exam)**

---

## Appendix: Komponen Reusable Baru yang Perlu Dibuat

| Component          | Description                                              | Location                   |
|--------------------|----------------------------------------------------------|----------------------------|
| `PageHeader`       | Title + breadcrumb + action button slot                  | `components/PageHeader.vue`|
| `EmptyState`       | Icon + title + description + CTA                         | `components/EmptyState.vue`|
| `StatsCard`        | Icon container + number + label + trend                  | `components/StatsCard.vue` |
| `StatusBadge`      | Semantic badge with predefined color variants            | `components/StatusBadge.vue`|
| `DataTableToolbar` | Search + filter slots + action buttons                   | `components/DataTable/DataTableToolbar.vue` |
| `ConfirmDialog`    | AlertDialog wrapper untuk delete/destructive actions     | `components/ConfirmDialog.vue` |
| `LoadingButton`    | Button with built-in spinner + disabled state            | `components/LoadingButton.vue` |
| `ExamTimer`        | Timer display with warning state visual transitions      | `components/Exam/ExamTimer.vue` |

---

*Dokumen ini adalah planning reference. Tidak ada implementasi yang dilakukan. Setiap section bisa dikerjakan secara independen setelah Fase 1 (Foundation) selesai.*
