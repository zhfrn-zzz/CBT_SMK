<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\GradeLevel;
use App\Enums\Semester;
use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ===== 1. Admin =====
        User::create([
            'name' => 'Administrator',
            'username' => 'admin',
            'email' => 'admin@smklms.test',
            'password' => Hash::make('password'),
            'role' => UserRole::Admin,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        // ===== 2. Guru (5) =====
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

        // ===== 4. Jurusan =====
        $tkj = Department::create(['name' => 'Teknik Komputer dan Jaringan', 'code' => 'TKJ']);
        $rpl = Department::create(['name' => 'Rekayasa Perangkat Lunak', 'code' => 'RPL']);
        $mm = Department::create(['name' => 'Multimedia', 'code' => 'MM']);

        // ===== 5. Mata Pelajaran =====
        // Mapel umum
        $matematika = Subject::create(['name' => 'Matematika', 'code' => 'MTK', 'department_id' => null]);
        $bIndonesia = Subject::create(['name' => 'Bahasa Indonesia', 'code' => 'BIN', 'department_id' => null]);
        $bInggris = Subject::create(['name' => 'Bahasa Inggris', 'code' => 'BIG', 'department_id' => null]);

        // Mapel jurusan TKJ
        $asj = Subject::create(['name' => 'Administrasi Sistem Jaringan', 'code' => 'ASJ', 'department_id' => $tkj->id]);
        $aij = Subject::create(['name' => 'Administrasi Infrastruktur Jaringan', 'code' => 'AIJ', 'department_id' => $tkj->id]);

        // Mapel jurusan RPL
        $pbo = Subject::create(['name' => 'Pemrograman Berorientasi Objek', 'code' => 'PBO', 'department_id' => $rpl->id]);
        $bdd = Subject::create(['name' => 'Basis Data', 'code' => 'BDD', 'department_id' => $rpl->id]);

        // Mapel jurusan MM
        $dg = Subject::create(['name' => 'Desain Grafis', 'code' => 'DGR', 'department_id' => $mm->id]);

        // ===== 6. Kelas =====
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

        // ===== 7. Siswa (50) + assign ke kelas =====
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

        // Distribute students across classrooms (roughly 5-6 per class)
        $studentChunks = array_chunk($students, (int) ceil(count($students) / count($classrooms)));
        foreach ($classrooms as $idx => $classroom) {
            if (isset($studentChunks[$idx])) {
                foreach ($studentChunks[$idx] as $student) {
                    $classroom->students()->attach($student->id);
                }
            }
        }

        // ===== 8. Assign guru ke mapel + kelas =====
        // Budi Santoso → ASJ di semua kelas TKJ
        // Siti Rahayu → Matematika di semua kelas
        // Ahmad Firmansyah → PBO di semua kelas RPL
        // Dewi Lestari → Bahasa Indonesia di semua kelas
        // Eko Prasetyo → Desain Grafis di semua kelas MM

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
    }
}
