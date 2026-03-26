<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\ExamSession;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();

    $this->academicYear = AcademicYear::factory()->active()->create();
    $this->department = Department::factory()->create();
    $this->subject = Subject::factory()->create(['department_id' => $this->department->id]);

    $this->classroom = Classroom::factory()->create([
        'academic_year_id' => $this->academicYear->id,
        'department_id' => $this->department->id,
    ]);
});

describe('Guru Index', function () {
    it('displays guru list for admin', function () {
        $guru = User::factory()->guru()->create();

        $response = $this->actingAs($this->admin)->get('/admin/guru');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Guru/Index')
            ->has('gurus.data')
        );
    });

    it('filters guru by search', function () {
        User::factory()->guru()->create(['name' => 'Ahmad Fauzi']);
        User::factory()->guru()->create(['name' => 'Budi Santoso']);

        $response = $this->actingAs($this->admin)->get('/admin/guru?search=Ahmad');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('gurus.data', 1)
        );
    });

    it('filters guru by subject', function () {
        $guru = User::factory()->guru()->create();
        TeachingAssignment::create([
            'user_id' => $guru->id,
            'classroom_id' => $this->classroom->id,
            'subject_id' => $this->subject->id,
        ]);

        $response = $this->actingAs($this->admin)->get("/admin/guru?subject_id={$this->subject->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('gurus.data', 1)
        );
    });

    it('denies access to non-admin', function () {
        $guru = User::factory()->guru()->create();

        $response = $this->actingAs($guru)->get('/admin/guru');

        $response->assertForbidden();
    });
});

describe('Guru Create', function () {
    it('shows create form', function () {
        $response = $this->actingAs($this->admin)->get('/admin/guru/create');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Guru/Create')
            ->has('classrooms')
            ->has('subjects')
        );
    });

    it('stores a new guru with auto-generated password', function () {
        $response = $this->actingAs($this->admin)->post('/admin/guru', [
            'name' => 'Guru Baru',
            'username' => '199001011990',
            'email' => 'guru@sekolah.id',
            'phone' => '08123456789',
            'is_active' => true,
            'teachings' => [],
        ]);

        $response->assertRedirect('/admin/guru');
        $this->assertDatabaseHas('users', [
            'name' => 'Guru Baru',
            'username' => '199001011990',
            'role' => UserRole::Guru->value,
        ]);
    });

    it('stores guru with teaching assignments', function () {
        $response = $this->actingAs($this->admin)->post('/admin/guru', [
            'name' => 'Guru Mengajar',
            'username' => '199002022990',
            'is_active' => true,
            'teachings' => [
                ['classroom_id' => $this->classroom->id, 'subject_id' => $this->subject->id],
            ],
        ]);

        $response->assertRedirect('/admin/guru');

        $guru = User::where('username', '199002022990')->first();
        expect($guru)->not->toBeNull();
        expect($guru->teachingAssignments)->toHaveCount(1);
    });

    it('validates required fields', function () {
        $response = $this->actingAs($this->admin)->post('/admin/guru', []);

        $response->assertSessionHasErrors(['name', 'username']);
    });

    it('validates unique NIP', function () {
        User::factory()->guru()->create(['username' => '199001010001']);

        $response = $this->actingAs($this->admin)->post('/admin/guru', [
            'name' => 'Guru Duplikat',
            'username' => '199001010001',
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors(['username']);
    });
});

describe('Guru Edit', function () {
    it('shows edit form with teaching assignments', function () {
        $guru = User::factory()->guru()->create();
        TeachingAssignment::create([
            'user_id' => $guru->id,
            'classroom_id' => $this->classroom->id,
            'subject_id' => $this->subject->id,
        ]);

        $response = $this->actingAs($this->admin)->get("/admin/guru/{$guru->id}/edit");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Guru/Edit')
            ->has('guru')
            ->has('classrooms')
            ->has('subjects')
        );
    });

    it('updates guru data', function () {
        $guru = User::factory()->guru()->create(['name' => 'Old Name']);

        $response = $this->actingAs($this->admin)->put("/admin/guru/{$guru->id}", [
            'name' => 'New Name',
            'username' => $guru->username,
            'is_active' => true,
            'teachings' => [],
        ]);

        $response->assertRedirect('/admin/guru');
        $guru->refresh();
        expect($guru->name)->toBe('New Name');
    });

    it('syncs teaching assignments on update', function () {
        $guru = User::factory()->guru()->create();
        $subject2 = Subject::factory()->create();
        $classroom2 = Classroom::factory()->create([
            'academic_year_id' => $this->academicYear->id,
            'department_id' => $this->department->id,
        ]);

        // Initial teaching
        TeachingAssignment::create([
            'user_id' => $guru->id,
            'classroom_id' => $this->classroom->id,
            'subject_id' => $this->subject->id,
        ]);

        // Update with different teaching
        $response = $this->actingAs($this->admin)->put("/admin/guru/{$guru->id}", [
            'name' => $guru->name,
            'username' => $guru->username,
            'is_active' => true,
            'teachings' => [
                ['classroom_id' => $classroom2->id, 'subject_id' => $subject2->id],
            ],
        ]);

        $response->assertRedirect('/admin/guru');

        $guru->refresh();
        $guru->load('teachingAssignments');
        expect($guru->teachingAssignments)->toHaveCount(1);
        expect($guru->teachingAssignments->first()->classroom_id)->toBe($classroom2->id);
        expect($guru->teachingAssignments->first()->subject_id)->toBe($subject2->id);
    });

    it('returns 404 for non-guru user', function () {
        $siswa = User::factory()->siswa()->create();

        $response = $this->actingAs($this->admin)->get("/admin/guru/{$siswa->id}/edit");

        $response->assertNotFound();
    });
});

describe('Guru Delete', function () {
    it('deletes a guru', function () {
        $guru = User::factory()->guru()->create();

        $response = $this->actingAs($this->admin)->delete("/admin/guru/{$guru->id}");

        $response->assertRedirect('/admin/guru');
        $this->assertDatabaseMissing('users', ['id' => $guru->id]);
    });

    it('prevents deleting guru with active exams', function () {
        $guru = User::factory()->guru()->create();
        ExamSession::factory()->create([
            'user_id' => $guru->id,
            'subject_id' => $this->subject->id,
            'academic_year_id' => $this->academicYear->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin)->delete("/admin/guru/{$guru->id}");

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $guru->id]);
    });
});

describe('Guru Reset Password', function () {
    it('resets guru password', function () {
        $guru = User::factory()->guru()->create();

        $response = $this->actingAs($this->admin)
            ->postJson("/admin/guru/{$guru->id}/reset-password");

        $response->assertOk();
        $response->assertJsonStructure(['password', 'name', 'username']);
    });

    it('rejects reset for non-guru', function () {
        $siswa = User::factory()->siswa()->create();

        $response = $this->actingAs($this->admin)
            ->postJson("/admin/guru/{$siswa->id}/reset-password");

        $response->assertForbidden();
    });
});

describe('Guru Import', function () {
    it('returns import template download', function () {
        $response = $this->actingAs($this->admin)->get('/admin/guru/import/template');

        $response->assertOk();
        $response->assertDownload('template-import-guru.xlsx');
    });
});
