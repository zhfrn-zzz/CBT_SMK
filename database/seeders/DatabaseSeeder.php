<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\GradeLevel;
use App\Enums\QuestionType;
use App\Enums\Semester;
use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptQuestion;
use App\Models\ExamSession;
use App\Models\QuestionBank;
use App\Models\StudentAnswer;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ===== 1. Admin (1) =====
        User::create([
            'name' => 'Administrator',
            'username' => 'admin',
            'email' => 'admin@smklms.test',
            'password' => Hash::make('password'),
            'role' => UserRole::Admin,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        // ===== 2. Guru (5 + 1 shortcut) =====
        // Shortcut guru: login dengan guru / password
        $shortcutGuru = User::create([
            'name' => 'Guru Demo',
            'username' => 'guru',
            'email' => 'guru@smklms.test',
            'password' => Hash::make('password'),
            'role' => UserRole::Guru,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $guruData = [
            ['name' => 'Budi Santoso', 'username' => '198501012010011001'],
            ['name' => 'Siti Rahayu', 'username' => '198703152011012002'],
            ['name' => 'Ahmad Firmansyah', 'username' => '199001202012011003'],
            ['name' => 'Dewi Lestari', 'username' => '198812302013012004'],
            ['name' => 'Eko Prasetyo', 'username' => '199205102014011005'],
        ];

        $teachers = [];
        foreach ($guruData as $guru) {
            $teachers[] = User::create([
                ...$guru,
                'email' => strtolower(str_replace(' ', '.', $guru['name'])).'@smklms.test',
                'password' => Hash::make('password'),
                'role' => UserRole::Guru,
                'is_active' => true,
                'email_verified_at' => now(),
            ]);
        }

        // ===== 3. Tahun Ajaran =====
        $academicYear = AcademicYear::create([
            'name' => '2025/2026',
            'semester' => Semester::Ganjil,
            'is_active' => true,
            'starts_at' => '2025-07-14',
            'ends_at' => '2025-12-20',
        ]);

        AcademicYear::create([
            'name' => '2025/2026',
            'semester' => Semester::Genap,
            'is_active' => false,
            'starts_at' => '2026-01-05',
            'ends_at' => '2026-06-20',
        ]);

        // ===== 4. Jurusan (3) =====
        $tkj = Department::create(['name' => 'Teknik Komputer dan Jaringan', 'code' => 'TKJ']);
        $rpl = Department::create(['name' => 'Rekayasa Perangkat Lunak', 'code' => 'RPL']);
        $mm = Department::create(['name' => 'Multimedia', 'code' => 'MM']);

        // ===== 5. Mata Pelajaran (8) =====
        // Mapel umum (3)
        $matematika = Subject::create(['name' => 'Matematika', 'code' => 'MTK', 'department_id' => null]);
        $bIndonesia = Subject::create(['name' => 'Bahasa Indonesia', 'code' => 'BIN', 'department_id' => null]);
        $bInggris = Subject::create(['name' => 'Bahasa Inggris', 'code' => 'BIG', 'department_id' => null]);

        // Mapel jurusan TKJ (2)
        $asj = Subject::create(['name' => 'Administrasi Sistem Jaringan', 'code' => 'ASJ', 'department_id' => $tkj->id]);

        // Mapel jurusan RPL (1)
        $pbo = Subject::create(['name' => 'Pemrograman Berorientasi Objek', 'code' => 'PBO', 'department_id' => $rpl->id]);
        $bdd = Subject::create(['name' => 'Basis Data', 'code' => 'BDD', 'department_id' => $rpl->id]);

        // Mapel jurusan MM (1)
        $dg = Subject::create(['name' => 'Desain Grafis', 'code' => 'DGR', 'department_id' => $mm->id]);
        $animasi = Subject::create(['name' => 'Animasi 2D dan 3D', 'code' => 'ANM', 'department_id' => $mm->id]);

        // ===== 6. Kelas (9) — 3 jurusan × 3 tingkat =====
        $classrooms = [];
        $deptClassConfig = [
            [$tkj, 'TKJ'],
            [$rpl, 'RPL'],
            [$mm, 'MM'],
        ];

        foreach ($deptClassConfig as [$dept, $code]) {
            foreach ([GradeLevel::X, GradeLevel::XI, GradeLevel::XII] as $grade) {
                $gradeLabel = match ($grade) {
                    GradeLevel::X => 'X',
                    GradeLevel::XI => 'XI',
                    GradeLevel::XII => 'XII',
                };
                $classrooms[] = Classroom::create([
                    'name' => "{$gradeLabel} {$code} 1",
                    'academic_year_id' => $academicYear->id,
                    'department_id' => $dept->id,
                    'grade_level' => $grade,
                ]);
            }
        }

        // ===== 7. Siswa (100) + assign ke kelas =====
        $siswaNames = [
            'Andi Prasetya', 'Bima Sakti', 'Cahya Putra', 'Dian Permata', 'Eka Putri',
            'Farhan Rizki', 'Gita Nirmala', 'Hasan Basri', 'Indah Sari', 'Joko Widodo',
            'Kartika Sari', 'Lukman Hakim', 'Maya Angelina', 'Naufal Ahsan', 'Olivia Putri',
            'Putra Pratama', 'Qori Aisyah', 'Rendi Maulana', 'Sinta Dewi', 'Taufik Hidayat',
            'Ulfa Rahmani', 'Vino Bastian', 'Wulan Dari', 'Xena Putri', 'Yusuf Maulana',
            'Zahra Aulia', 'Arif Rahman', 'Bella Safira', 'Candra Wijaya', 'Dina Mariana',
            'Elang Satria', 'Fira Andini', 'Galih Permana', 'Hesti Wulandari', 'Irfan Mahendra',
            'Jasmine Putri', 'Kresna Deva', 'Layla Azzahra', 'Mulia Pratama', 'Nabila Husna',
            'Omar Fadhil', 'Putri Ayu', 'Rafli Adiputra', 'Sari Melati', 'Tessa Novita',
            'Umar Harun', 'Vira Yunita', 'Wahyu Saputra', 'Yanti Surya', 'Zaki Firmansyah',
            'Alya Permata', 'Bagas Aditya', 'Citra Lestari', 'Dafa Maulana', 'Erna Wati',
            'Fajar Nugroho', 'Gilang Ramadhan', 'Hana Pertiwi', 'Imam Syafii', 'Julia Rahma',
            'Kurnia Aji', 'Lina Marlina', 'Mahesa Putra', 'Nisa Amelia', 'Oscar Pratama',
            'Puspita Sari', 'Ridwan Hakim', 'Selvi Oktavia', 'Tri Wahyudi', 'Umi Kalsum',
            'Virga Aulia', 'Wawan Setiawan', 'Yolanda Putri', 'Zulfikar Ahmad', 'Anisa Dewi',
            'Bagus Hermawan', 'Desi Ratnasari', 'Erwin Saputra', 'Fitri Handayani', 'Gunawan Wibisono',
            'Hendra Gunawan', 'Intan Permatasari', 'Joni Iskandar', 'Kartini Wulandari', 'Lutfi Hakim',
            'Mega Safitri', 'Nugroho Adi', 'Oktaviani Putri', 'Prasetyo Budi', 'Ratna Kumala',
            'Surya Darma', 'Tiara Anandita', 'Udin Sedunia', 'Vera Anggraini', 'Wisnu Wardana',
            'Yulia Citra', 'Zainab Husna', 'Ade Irawan', 'Bunga Citra', 'Darmawan Putra',
        ];

        $students = [];
        foreach ($siswaNames as $i => $name) {
            $nis = (string) (10001 + $i);
            $students[] = User::create([
                'name' => $name,
                'username' => $nis,
                'email' => null,
                'password' => Hash::make('password'),
                'role' => UserRole::Siswa,
                'is_active' => true,
                'email_verified_at' => now(),
            ]);
        }

        // Distribute 100 students across 9 classrooms (~11 per class)
        $studentChunks = array_chunk($students, (int) ceil(count($students) / count($classrooms)));
        foreach ($classrooms as $idx => $classroom) {
            if (isset($studentChunks[$idx])) {
                foreach ($studentChunks[$idx] as $student) {
                    $classroom->students()->attach($student->id);
                }
            }
        }

        // ===== 8. Assign guru ke mapel + kelas =====
        $assignments = [
            [$teachers[0], $asj, fn ($c) => $c->department_id === $tkj->id],
            [$teachers[1], $matematika, fn () => true],
            [$teachers[2], $pbo, fn ($c) => $c->department_id === $rpl->id],
            [$teachers[3], $bIndonesia, fn () => true],
            [$teachers[4], $dg, fn ($c) => $c->department_id === $mm->id],
        ];

        foreach ($assignments as [$teacher, $subject, $filter]) {
            foreach ($classrooms as $classroom) {
                if ($filter($classroom)) {
                    DB::table('classroom_subject_teacher')->insert([
                        'classroom_id' => $classroom->id,
                        'subject_id' => $subject->id,
                        'user_id' => $teacher->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        // ===== 9. Bank Soal (3) — masing-masing 20 soal =====

        // --- Bank 1: ASJ (Budi Santoso) ---
        $bankAsj = QuestionBank::create([
            'name' => 'UTS ASJ Kelas XI Semester 1',
            'subject_id' => $asj->id,
            'user_id' => $teachers[0]->id,
            'description' => 'Bank soal untuk Ujian Tengah Semester ASJ kelas XI',
        ]);
        $this->seedQuestionsAsj($bankAsj);

        // --- Bank 2: Matematika (Siti Rahayu) ---
        $bankMtk = QuestionBank::create([
            'name' => 'UTS Matematika Kelas X Semester 1',
            'subject_id' => $matematika->id,
            'user_id' => $teachers[1]->id,
            'description' => 'Bank soal UTS Matematika bab Logika dan Bilangan',
        ]);
        $this->seedQuestionsMtk($bankMtk);

        // --- Bank 3: PBO (Ahmad Firmansyah) ---
        $bankPbo = QuestionBank::create([
            'name' => 'UTS PBO Kelas XI Semester 1',
            'subject_id' => $pbo->id,
            'user_id' => $teachers[2]->id,
            'description' => 'Bank soal UTS Pemrograman Berorientasi Objek',
        ]);
        $this->seedQuestionsPbo($bankPbo);

        // ===== 10. Sesi Ujian (2) =====

        // --- Sesi 1: Active (ASJ) — sedang berlangsung ---
        $activeSession = ExamSession::create([
            'name' => 'UTS ASJ XI TKJ 2025',
            'subject_id' => $asj->id,
            'user_id' => $teachers[0]->id,
            'academic_year_id' => $academicYear->id,
            'question_bank_id' => $bankAsj->id,
            'token' => 'ASJUTS',
            'duration_minutes' => 90,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHours(2),
            'is_randomize_questions' => true,
            'is_randomize_options' => true,
            'is_published' => true,
            'pool_count' => null,
            'kkm' => 75.00,
            'max_tab_switches' => 3,
            'status' => ExamStatus::Active,
        ]);

        // Attach kelas TKJ saja (X TKJ 1, XI TKJ 1, XII TKJ 1)
        $tkjClassrooms = collect($classrooms)->filter(fn ($c) => $c->department_id === $tkj->id);
        $activeSession->classrooms()->attach($tkjClassrooms->pluck('id'));

        // --- Sesi 2: Completed (Matematika) — sudah selesai ---
        $completedSession = ExamSession::create([
            'name' => 'UTS Matematika X 2025',
            'subject_id' => $matematika->id,
            'user_id' => $teachers[1]->id,
            'academic_year_id' => $academicYear->id,
            'question_bank_id' => $bankMtk->id,
            'token' => 'MTKUTS',
            'duration_minutes' => 60,
            'starts_at' => now()->subDays(7)->setHour(8),
            'ends_at' => now()->subDays(7)->setHour(10),
            'is_randomize_questions' => false,
            'is_randomize_options' => false,
            'is_published' => true,
            'pool_count' => null,
            'kkm' => 70.00,
            'max_tab_switches' => 5,
            'status' => ExamStatus::Completed,
        ]);

        // Attach semua kelas (Matematika = mapel umum)
        $completedSession->classrooms()->attach(collect($classrooms)->pluck('id'));

        // ===== 11. Simulasi jawaban siswa untuk sesi completed =====
        $this->seedCompletedExamAttempts($completedSession, $bankMtk, $students);
    }

    /**
     * 20 Soal ASJ: 14 PG, 3 B/S, 3 Esai.
     */
    private function seedQuestionsAsj(QuestionBank $bank): void
    {
        $order = 0;

        // --- 14 Pilihan Ganda ---
        $pgQuestions = [
            ['content' => '<p>Apa kepanjangan dari <strong>DNS</strong>?</p>', 'explanation' => 'DNS = Domain Name System', 'options' => [
                ['A', 'Domain Name System', true], ['B', 'Digital Network Service', false], ['C', 'Data Network System', false], ['D', 'Domain Network Service', false],
            ]],
            ['content' => '<p>Port default dari layanan HTTP adalah...</p>', 'explanation' => 'HTTP menggunakan port 80', 'options' => [
                ['A', '21', false], ['B', '22', false], ['C', '80', true], ['D', '443', false],
            ]],
            ['content' => '<p>Perintah untuk melihat tabel routing di Linux adalah...</p>', 'explanation' => 'route -n atau ip route show', 'options' => [
                ['A', 'route -n', true], ['B', 'ipconfig', false], ['C', 'netstat -a', false], ['D', 'ping -t', false],
            ]],
            ['content' => '<p>Alamat IP 192.168.1.0/24 termasuk kelas...</p>', 'explanation' => 'IP 192.168.x.x termasuk kelas C', 'options' => [
                ['A', 'Kelas A', false], ['B', 'Kelas B', false], ['C', 'Kelas C', true], ['D', 'Kelas D', false],
            ]],
            ['content' => '<p>Protokol yang digunakan untuk mengirim email adalah...</p>', 'explanation' => 'SMTP = Simple Mail Transfer Protocol', 'options' => [
                ['A', 'FTP', false], ['B', 'SMTP', true], ['C', 'HTTP', false], ['D', 'SNMP', false],
            ]],
            ['content' => '<p>Berapa jumlah bit dalam satu alamat IPv4?</p>', 'explanation' => 'IPv4 terdiri dari 32 bit', 'options' => [
                ['A', '16 bit', false], ['B', '32 bit', true], ['C', '64 bit', false], ['D', '128 bit', false],
            ]],
            ['content' => '<p>Layer ke-3 dalam model OSI adalah...</p>', 'explanation' => 'Layer 3 = Network layer', 'options' => [
                ['A', 'Data Link', false], ['B', 'Network', true], ['C', 'Transport', false], ['D', 'Session', false],
            ]],
            ['content' => '<p>Perangkat yang bekerja di layer 2 OSI adalah...</p>', 'explanation' => 'Switch bekerja di layer 2 (Data Link)', 'options' => [
                ['A', 'Router', false], ['B', 'Hub', false], ['C', 'Switch', true], ['D', 'Repeater', false],
            ]],
            ['content' => '<p>Fungsi utama dari DHCP server adalah...</p>', 'explanation' => 'DHCP mendistribusikan IP address secara otomatis', 'options' => [
                ['A', 'Menerjemahkan nama domain', false], ['B', 'Mendistribusikan IP address otomatis', true], ['C', 'Menyaring paket data', false], ['D', 'Mengenkripsi data', false],
            ]],
            ['content' => '<p>Subnet mask default untuk kelas B adalah...</p>', 'explanation' => 'Kelas B = 255.255.0.0', 'options' => [
                ['A', '255.0.0.0', false], ['B', '255.255.0.0', true], ['C', '255.255.255.0', false], ['D', '255.255.255.255', false],
            ]],
            ['content' => '<p>Topologi jaringan dimana semua node terhubung ke satu titik pusat disebut...</p>', 'explanation' => 'Topologi Star menggunakan satu titik pusat (hub/switch)', 'options' => [
                ['A', 'Ring', false], ['B', 'Bus', false], ['C', 'Star', true], ['D', 'Mesh', false],
            ]],
            ['content' => '<p>Protokol yang menggunakan port 443 adalah...</p>', 'explanation' => 'HTTPS menggunakan port 443', 'options' => [
                ['A', 'HTTP', false], ['B', 'HTTPS', true], ['C', 'FTP', false], ['D', 'SSH', false],
            ]],
            ['content' => '<p>Perintah <code>nslookup</code> digunakan untuk...</p>', 'explanation' => 'nslookup untuk query DNS', 'options' => [
                ['A', 'Mengecek koneksi jaringan', false], ['B', 'Melihat routing table', false], ['C', 'Query DNS record', true], ['D', 'Konfigurasi IP address', false],
            ]],
            ['content' => '<p>VPN adalah singkatan dari...</p>', 'explanation' => 'VPN = Virtual Private Network', 'options' => [
                ['A', 'Virtual Public Network', false], ['B', 'Virtual Private Network', true], ['C', 'Very Private Network', false], ['D', 'Visual Private Network', false],
            ]],
        ];

        foreach ($pgQuestions as $pg) {
            $order++;
            $q = $bank->questions()->create([
                'type' => QuestionType::PilihanGanda,
                'content' => $pg['content'],
                'points' => 2,
                'explanation' => $pg['explanation'],
                'order' => $order,
            ]);
            $q->options()->createMany(array_map(fn ($o) => [
                'label' => $o[0], 'content' => $o[1], 'is_correct' => $o[2], 'order' => ord($o[0]) - ord('A'),
            ], $pg['options']));
        }

        // --- 3 Benar/Salah ---
        $bsQuestions = [
            ['content' => '<p>DHCP digunakan untuk memberikan IP address secara otomatis kepada client.</p>', 'explanation' => 'Benar. DHCP = Dynamic Host Configuration Protocol.', 'answer' => true],
            ['content' => '<p>Router bekerja di layer 2 (Data Link) dalam model OSI.</p>', 'explanation' => 'Salah. Router bekerja di layer 3 (Network).', 'answer' => false],
            ['content' => '<p>Alamat IP 127.0.0.1 digunakan untuk loopback testing.</p>', 'explanation' => 'Benar. 127.0.0.1 adalah localhost/loopback address.', 'answer' => true],
        ];

        foreach ($bsQuestions as $bs) {
            $order++;
            $q = $bank->questions()->create([
                'type' => QuestionType::BenarSalah,
                'content' => $bs['content'],
                'points' => 2,
                'explanation' => $bs['explanation'],
                'order' => $order,
            ]);
            $q->options()->createMany([
                ['label' => 'A', 'content' => 'Benar', 'is_correct' => $bs['answer'], 'order' => 0],
                ['label' => 'B', 'content' => 'Salah', 'is_correct' => ! $bs['answer'], 'order' => 1],
            ]);
        }

        // --- 3 Esai ---
        $esaiQuestions = [
            ['content' => '<p>Jelaskan perbedaan antara <em>static routing</em> dan <em>dynamic routing</em>! Berikan masing-masing satu contoh protokol yang digunakan.</p>', 'explanation' => 'Static routing dikonfigurasi manual, contoh: route add. Dynamic routing otomatis, contoh: OSPF, RIP.'],
            ['content' => '<p>Jelaskan langkah-langkah konfigurasi DHCP server pada Linux menggunakan <code>isc-dhcp-server</code>!</p>', 'explanation' => 'Install isc-dhcp-server, edit /etc/dhcp/dhcpd.conf, tentukan subnet, range, gateway, DNS, restart service.'],
            ['content' => '<p>Apa yang dimaksud dengan <em>subnetting</em>? Berikan contoh pembagian network 192.168.1.0/24 menjadi 4 subnet!</p>', 'explanation' => 'Subnetting = membagi network menjadi sub-network lebih kecil. Contoh: /26 → 4 subnet masing-masing 62 host.'],
        ];

        foreach ($esaiQuestions as $esai) {
            $order++;
            $bank->questions()->create([
                'type' => QuestionType::Esai,
                'content' => $esai['content'],
                'points' => 10,
                'explanation' => $esai['explanation'],
                'order' => $order,
            ]);
        }
    }

    /**
     * 20 Soal Matematika: 14 PG, 3 B/S, 3 Esai.
     */
    private function seedQuestionsMtk(QuestionBank $bank): void
    {
        $order = 0;

        // --- 14 Pilihan Ganda ---
        $pgQuestions = [
            ['content' => '<p>Negasi dari pernyataan "Semua siswa rajin belajar" adalah...</p>', 'explanation' => 'Negasi dari "semua" adalah "ada yang tidak".', 'options' => [
                ['A', 'Semua siswa tidak rajin belajar', false], ['B', 'Ada siswa yang tidak rajin belajar', true], ['C', 'Tidak ada siswa yang rajin belajar', false], ['D', 'Beberapa siswa rajin belajar', false],
            ]],
            ['content' => '<p>Nilai dari log<sub>2</sub> 32 adalah...</p>', 'explanation' => '2^5 = 32, jadi log₂ 32 = 5.', 'options' => [
                ['A', '4', false], ['B', '5', true], ['C', '6', false], ['D', '3', false],
            ]],
            ['content' => '<p>Jika f(x) = 2x + 3, maka f(4) = ...</p>', 'explanation' => 'f(4) = 2(4) + 3 = 11.', 'options' => [
                ['A', '8', false], ['B', '10', false], ['C', '11', true], ['D', '14', false],
            ]],
            ['content' => '<p>Hasil dari 3! × 2! adalah...</p>', 'explanation' => '3! = 6, 2! = 2, 6 × 2 = 12.', 'options' => [
                ['A', '6', false], ['B', '8', false], ['C', '10', false], ['D', '12', true],
            ]],
            ['content' => '<p>Disjungsi dari p ∨ q bernilai salah jika...</p>', 'explanation' => 'Disjungsi salah hanya jika kedua operan salah.', 'options' => [
                ['A', 'p benar dan q salah', false], ['B', 'p salah dan q benar', false], ['C', 'p benar dan q benar', false], ['D', 'p salah dan q salah', true],
            ]],
            ['content' => '<p>Himpunan penyelesaian dari |x - 3| = 5 adalah...</p>', 'explanation' => 'x - 3 = 5 → x = 8 atau x - 3 = -5 → x = -2.', 'options' => [
                ['A', '{-2, 8}', true], ['B', '{2, 8}', false], ['C', '{-8, 2}', false], ['D', '{3, 5}', false],
            ]],
            ['content' => '<p>Persamaan garis yang melalui titik (1, 2) dengan gradien 3 adalah...</p>', 'explanation' => 'y - 2 = 3(x - 1) → y = 3x - 1.', 'options' => [
                ['A', 'y = 3x + 1', false], ['B', 'y = 3x - 1', true], ['C', 'y = x + 3', false], ['D', 'y = 2x + 1', false],
            ]],
            ['content' => '<p>Nilai dari sin 30° adalah...</p>', 'explanation' => 'sin 30° = 1/2.', 'options' => [
                ['A', '1/2', true], ['B', '√2/2', false], ['C', '√3/2', false], ['D', '1', false],
            ]],
            ['content' => '<p>Jika matriks A = [[1,2],[3,4]], maka determinan A adalah...</p>', 'explanation' => 'det = (1)(4) - (2)(3) = 4 - 6 = -2.', 'options' => [
                ['A', '2', false], ['B', '-2', true], ['C', '10', false], ['D', '-10', false],
            ]],
            ['content' => '<p>Jumlah sudut dalam segitiga adalah...</p>', 'explanation' => 'Jumlah sudut dalam segitiga = 180°.', 'options' => [
                ['A', '90°', false], ['B', '180°', true], ['C', '270°', false], ['D', '360°', false],
            ]],
            ['content' => '<p>Rata-rata dari data 4, 6, 8, 10, 12 adalah...</p>', 'explanation' => '(4+6+8+10+12)/5 = 40/5 = 8.', 'options' => [
                ['A', '7', false], ['B', '8', true], ['C', '9', false], ['D', '10', false],
            ]],
            ['content' => '<p>Turunan pertama dari f(x) = x³ + 2x adalah...</p>', 'explanation' => "f'(x) = 3x² + 2.", 'options' => [
                ['A', '3x² + 2', true], ['B', '3x²', false], ['C', 'x² + 2', false], ['D', '3x + 2', false],
            ]],
            ['content' => '<p>Integral dari ∫ 2x dx adalah...</p>', 'explanation' => '∫ 2x dx = x² + C.', 'options' => [
                ['A', 'x² + C', true], ['B', '2x² + C', false], ['C', 'x + C', false], ['D', '2 + C', false],
            ]],
            ['content' => '<p>Median dari data 3, 7, 5, 9, 1 adalah...</p>', 'explanation' => 'Diurutkan: 1,3,5,7,9. Median = 5.', 'options' => [
                ['A', '3', false], ['B', '5', true], ['C', '7', false], ['D', '9', false],
            ]],
        ];

        foreach ($pgQuestions as $pg) {
            $order++;
            $q = $bank->questions()->create([
                'type' => QuestionType::PilihanGanda,
                'content' => $pg['content'],
                'points' => 2,
                'explanation' => $pg['explanation'],
                'order' => $order,
            ]);
            $q->options()->createMany(array_map(fn ($o) => [
                'label' => $o[0], 'content' => $o[1], 'is_correct' => $o[2], 'order' => ord($o[0]) - ord('A'),
            ], $pg['options']));
        }

        // --- 3 Benar/Salah ---
        $bsQuestions = [
            ['content' => '<p>Bilangan prima adalah bilangan yang hanya memiliki dua faktor: 1 dan dirinya sendiri.</p>', 'explanation' => 'Benar, definisi bilangan prima.', 'answer' => true],
            ['content' => '<p>Nilai dari 0! (nol faktorial) adalah 0.</p>', 'explanation' => 'Salah. 0! = 1 (definisi).', 'answer' => false],
            ['content' => '<p>Dua garis sejajar memiliki gradien yang sama.</p>', 'explanation' => 'Benar. Garis sejajar memiliki gradien yang sama.', 'answer' => true],
        ];

        foreach ($bsQuestions as $bs) {
            $order++;
            $q = $bank->questions()->create([
                'type' => QuestionType::BenarSalah,
                'content' => $bs['content'],
                'points' => 2,
                'explanation' => $bs['explanation'],
                'order' => $order,
            ]);
            $q->options()->createMany([
                ['label' => 'A', 'content' => 'Benar', 'is_correct' => $bs['answer'], 'order' => 0],
                ['label' => 'B', 'content' => 'Salah', 'is_correct' => ! $bs['answer'], 'order' => 1],
            ]);
        }

        // --- 3 Esai ---
        $esaiQuestions = [
            ['content' => '<p>Buktikan bahwa jumlah n suku pertama deret aritmatika adalah S<sub>n</sub> = n/2 × (2a + (n-1)d)!</p>', 'explanation' => 'Tulis deret maju dan mundur, jumlahkan, bagi 2.'],
            ['content' => '<p>Tentukan persamaan garis singgung lingkaran x² + y² = 25 di titik (3, 4)!</p>', 'explanation' => 'Persamaan: 3x + 4y = 25.'],
            ['content' => '<p>Sebuah dadu dilempar 2 kali. Tentukan peluang munculnya jumlah mata dadu = 7!</p>', 'explanation' => 'Kemungkinan: (1,6),(2,5),(3,4),(4,3),(5,2),(6,1) = 6/36 = 1/6.'],
        ];

        foreach ($esaiQuestions as $esai) {
            $order++;
            $bank->questions()->create([
                'type' => QuestionType::Esai,
                'content' => $esai['content'],
                'points' => 10,
                'explanation' => $esai['explanation'],
                'order' => $order,
            ]);
        }
    }

    /**
     * 20 Soal PBO: 14 PG, 3 B/S, 3 Esai.
     */
    private function seedQuestionsPbo(QuestionBank $bank): void
    {
        $order = 0;

        // --- 14 Pilihan Ganda ---
        $pgQuestions = [
            ['content' => '<p>Konsep dasar OOP yang menyembunyikan detail implementasi disebut...</p>', 'explanation' => 'Encapsulation menyembunyikan detail implementasi.', 'options' => [
                ['A', 'Encapsulation', true], ['B', 'Inheritance', false], ['C', 'Polymorphism', false], ['D', 'Abstraction', false],
            ]],
            ['content' => '<p>Keyword di Java untuk mewarisi sebuah class adalah...</p>', 'explanation' => 'Java menggunakan keyword extends.', 'options' => [
                ['A', 'implements', false], ['B', 'extends', true], ['C', 'inherits', false], ['D', 'derives', false],
            ]],
            ['content' => '<p>Access modifier yang hanya bisa diakses di class yang sama adalah...</p>', 'explanation' => 'private hanya bisa diakses di class yang sama.', 'options' => [
                ['A', 'public', false], ['B', 'protected', false], ['C', 'private', true], ['D', 'default', false],
            ]],
            ['content' => '<p>Method yang memiliki nama sama tapi parameter berbeda disebut...</p>', 'explanation' => 'Method overloading = nama sama, parameter beda.', 'options' => [
                ['A', 'Overriding', false], ['B', 'Overloading', true], ['C', 'Overwriting', false], ['D', 'Overlapping', false],
            ]],
            ['content' => '<p>Class yang tidak bisa diinstansiasi disebut...</p>', 'explanation' => 'Abstract class tidak bisa diinstansiasi.', 'options' => [
                ['A', 'Static class', false], ['B', 'Final class', false], ['C', 'Abstract class', true], ['D', 'Interface', false],
            ]],
            ['content' => '<p>Keyword untuk membuat objek baru di Java adalah...</p>', 'explanation' => 'Keyword new untuk membuat objek.', 'options' => [
                ['A', 'create', false], ['B', 'new', true], ['C', 'make', false], ['D', 'init', false],
            ]],
            ['content' => '<p>Interface di Java hanya boleh memiliki method yang bersifat...</p>', 'explanation' => 'Method dalam interface bersifat abstract (sebelum Java 8).', 'options' => [
                ['A', 'static', false], ['B', 'final', false], ['C', 'abstract', true], ['D', 'private', false],
            ]],
            ['content' => '<p>Konsep dimana satu method bisa berperilaku berbeda pada class yang berbeda disebut...</p>', 'explanation' => 'Polymorphism = satu interface, banyak implementasi.', 'options' => [
                ['A', 'Encapsulation', false], ['B', 'Inheritance', false], ['C', 'Polymorphism', true], ['D', 'Composition', false],
            ]],
            ['content' => '<p>Constructor adalah method yang dipanggil saat...</p>', 'explanation' => 'Constructor dipanggil saat objek dibuat.', 'options' => [
                ['A', 'Class diimpor', false], ['B', 'Objek dibuat', true], ['C', 'Method dipanggil', false], ['D', 'Class dihapus', false],
            ]],
            ['content' => '<p>Keyword untuk mencegah class di-inherit adalah...</p>', 'explanation' => 'final class tidak bisa di-inherit.', 'options' => [
                ['A', 'static', false], ['B', 'abstract', false], ['C', 'sealed', false], ['D', 'final', true],
            ]],
            ['content' => '<p>Prinsip OOP yang memungkinkan penggunaan ulang kode melalui pewarisan disebut...</p>', 'explanation' => 'Inheritance memungkinkan penggunaan ulang kode.', 'options' => [
                ['A', 'Encapsulation', false], ['B', 'Inheritance', true], ['C', 'Polymorphism', false], ['D', 'Abstraction', false],
            ]],
            ['content' => '<p>Tipe data <code>String</code> di Java termasuk...</p>', 'explanation' => 'String adalah reference type (class).', 'options' => [
                ['A', 'Primitive type', false], ['B', 'Reference type', true], ['C', 'Value type', false], ['D', 'Generic type', false],
            ]],
            ['content' => '<p>Keyword <code>this</code> dalam Java merujuk pada...</p>', 'explanation' => 'this merujuk pada objek saat ini.', 'options' => [
                ['A', 'Class saat ini', false], ['B', 'Objek saat ini', true], ['C', 'Method saat ini', false], ['D', 'Package saat ini', false],
            ]],
            ['content' => '<p>Design pattern yang memastikan hanya ada satu instance dari sebuah class disebut...</p>', 'explanation' => 'Singleton pattern = satu instance.', 'options' => [
                ['A', 'Factory', false], ['B', 'Observer', false], ['C', 'Singleton', true], ['D', 'Builder', false],
            ]],
        ];

        foreach ($pgQuestions as $pg) {
            $order++;
            $q = $bank->questions()->create([
                'type' => QuestionType::PilihanGanda,
                'content' => $pg['content'],
                'points' => 2,
                'explanation' => $pg['explanation'],
                'order' => $order,
            ]);
            $q->options()->createMany(array_map(fn ($o) => [
                'label' => $o[0], 'content' => $o[1], 'is_correct' => $o[2], 'order' => ord($o[0]) - ord('A'),
            ], $pg['options']));
        }

        // --- 3 Benar/Salah ---
        $bsQuestions = [
            ['content' => '<p>Java mendukung multiple inheritance melalui class.</p>', 'explanation' => 'Salah. Java tidak mendukung multiple inheritance via class, hanya via interface.', 'answer' => false],
            ['content' => '<p>Sebuah class bisa mengimplementasi lebih dari satu interface di Java.</p>', 'explanation' => 'Benar. Java mendukung multiple interface implementation.', 'answer' => true],
            ['content' => '<p>Method overriding terjadi antara parent class dan child class.</p>', 'explanation' => 'Benar. Overriding = child class menimpa method parent.', 'answer' => true],
        ];

        foreach ($bsQuestions as $bs) {
            $order++;
            $q = $bank->questions()->create([
                'type' => QuestionType::BenarSalah,
                'content' => $bs['content'],
                'points' => 2,
                'explanation' => $bs['explanation'],
                'order' => $order,
            ]);
            $q->options()->createMany([
                ['label' => 'A', 'content' => 'Benar', 'is_correct' => $bs['answer'], 'order' => 0],
                ['label' => 'B', 'content' => 'Salah', 'is_correct' => ! $bs['answer'], 'order' => 1],
            ]);
        }

        // --- 3 Esai ---
        $esaiQuestions = [
            ['content' => '<p>Jelaskan 4 pilar utama OOP beserta contohnya masing-masing!</p>', 'explanation' => 'Encapsulation, Inheritance, Polymorphism, Abstraction — dengan contoh code.'],
            ['content' => '<p>Buatlah class <code>BangunDatar</code> sebagai abstract class dengan method <code>hitungLuas()</code> dan <code>hitungKeliling()</code>. Kemudian buat class <code>Persegi</code> yang mewarisinya!</p>', 'explanation' => 'Abstract class BangunDatar dengan abstract methods, Persegi extends dan implement.'],
            ['content' => '<p>Jelaskan perbedaan antara <code>abstract class</code> dan <code>interface</code> di Java! Kapan sebaiknya menggunakan masing-masing?</p>', 'explanation' => 'Abstract class bisa punya method concrete, interface hanya abstract. Use abstract class untuk "is-a", interface untuk "can-do".'],
        ];

        foreach ($esaiQuestions as $esai) {
            $order++;
            $bank->questions()->create([
                'type' => QuestionType::Esai,
                'content' => $esai['content'],
                'points' => 10,
                'explanation' => $esai['explanation'],
                'order' => $order,
            ]);
        }
    }

    /**
     * Seed completed exam attempts with answers and scores.
     *
     * @param  array<User>  $students
     */
    private function seedCompletedExamAttempts(ExamSession $session, QuestionBank $bank, array $students): void
    {
        $questions = $bank->questions()->with('options')->orderBy('order')->get();

        // Ambil 30 siswa pertama sebagai peserta completed exam
        $participants = array_slice($students, 0, 30);

        foreach ($participants as $index => $student) {
            $attempt = ExamAttempt::create([
                'exam_session_id' => $session->id,
                'user_id' => $student->id,
                'started_at' => $session->starts_at->copy()->addMinutes(rand(0, 5)),
                'submitted_at' => $session->starts_at->copy()->addMinutes(rand(30, 55)),
                'is_force_submitted' => $index % 10 === 0, // 10% force submitted
                'ip_address' => '192.168.1.' . ($index + 10),
                'status' => ExamAttemptStatus::Submitted,
            ]);

            // Create attempt questions (sequential order, no randomization)
            foreach ($questions as $qIndex => $question) {
                ExamAttemptQuestion::create([
                    'exam_attempt_id' => $attempt->id,
                    'question_id' => $question->id,
                    'order' => $qIndex + 1,
                    'option_order' => null,
                ]);
            }

            // Generate answers
            $totalScore = 0;
            $maxScore = 0;
            $allAutoGraded = true;

            foreach ($questions as $question) {
                $maxScore += (float) $question->points;

                if ($question->type === QuestionType::PilihanGanda || $question->type === QuestionType::BenarSalah) {
                    $correctOption = $question->options->firstWhere('is_correct', true);
                    // Variasi jawaban: siswa dengan index kecil lebih pintar
                    $correctChance = max(30, 90 - ($index * 2));
                    $isCorrect = rand(1, 100) <= $correctChance;

                    if ($isCorrect) {
                        $answerLabel = $correctOption->label;
                    } else {
                        $wrongOptions = $question->options->where('is_correct', false);
                        $answerLabel = $wrongOptions->random()->label;
                    }

                    $score = $isCorrect ? (float) $question->points : 0;
                    $totalScore += $score;

                    StudentAnswer::create([
                        'exam_attempt_id' => $attempt->id,
                        'question_id' => $question->id,
                        'answer' => $answerLabel,
                        'is_correct' => $isCorrect,
                        'score' => $score,
                        'answered_at' => $attempt->started_at->copy()->addMinutes(rand(1, 50)),
                    ]);
                } elseif ($question->type === QuestionType::Esai) {
                    $allAutoGraded = false;
                    $esaiAnswers = [
                        'Static routing dikonfigurasi secara manual oleh administrator jaringan. Dynamic routing menggunakan protokol untuk pertukaran informasi routing secara otomatis.',
                        'Langkah konfigurasi: install paket, edit file konfigurasi, tentukan parameter subnet dan range, restart service.',
                        'Subnetting adalah teknik membagi jaringan besar menjadi beberapa jaringan kecil untuk efisiensi penggunaan IP address.',
                    ];

                    StudentAnswer::create([
                        'exam_attempt_id' => $attempt->id,
                        'question_id' => $question->id,
                        'answer' => $esaiAnswers[array_rand($esaiAnswers)],
                        'is_correct' => null,
                        'score' => null,
                        'answered_at' => $attempt->started_at->copy()->addMinutes(rand(20, 50)),
                    ]);
                }
            }

            // Calculate score percentage (only for auto-graded questions)
            if ($allAutoGraded && $maxScore > 0) {
                $attempt->update([
                    'score' => round(($totalScore / $maxScore) * 100, 2),
                    'is_fully_graded' => true,
                ]);
            } else {
                // Partial score — only auto-gradable portion
                $autoGradeMax = $questions->whereIn('type', [QuestionType::PilihanGanda, QuestionType::BenarSalah])->sum('points');
                if ($autoGradeMax > 0) {
                    $attempt->update([
                        'score' => round(($totalScore / (float) $autoGradeMax) * 100, 2),
                        'is_fully_graded' => false,
                    ]);
                }
            }
        }
    }
}
