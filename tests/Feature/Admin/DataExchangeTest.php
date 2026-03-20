<?php

declare(strict_types=1);

use App\Jobs\ExportNilaiRaporJob;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->department = Department::factory()->create();
    $this->academicYear = AcademicYear::factory()->create();
    $this->classroom = Classroom::factory()->create([
        'academic_year_id' => $this->academicYear->id,
        'department_id' => $this->department->id,
    ]);
});

test('admin can access data exchange page', function () {
    $this->actingAs($this->admin)
        ->get('/admin/data-exchange/export-students')
        ->assertOk()
        ->assertInertia(fn ($p) => $p
            ->component('Admin/DataExchange/Index')
            ->has('classrooms')
            ->has('academicYears')
        );
});

test('admin can download student export', function () {
    User::factory()->siswa()->create();

    $response = $this->actingAs($this->admin)
        ->get('/admin/data-exchange/export-students/download');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

test('admin can download student export filtered by classroom', function () {
    $siswa = User::factory()->siswa()->create();
    $this->classroom->students()->attach($siswa->id);

    $response = $this->actingAs($this->admin)
        ->get("/admin/data-exchange/export-students/download?classroom_id={$this->classroom->id}");

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

test('admin can download import template', function () {
    $response = $this->actingAs($this->admin)
        ->get('/admin/data-exchange/template');

    $response->assertOk();
});

test('admin cannot access data exchange without auth', function () {
    $this->get('/admin/data-exchange/export-students')
        ->assertRedirect('/login');
});

test('guru cannot access admin data exchange', function () {
    $guru = User::factory()->guru()->create();

    $this->actingAs($guru)
        ->get('/admin/data-exchange/export-students')
        ->assertForbidden();
});

test('siswa cannot access admin data exchange', function () {
    $siswa = User::factory()->siswa()->create();

    $this->actingAs($siswa)
        ->get('/admin/data-exchange/export-students')
        ->assertForbidden();
});

test('admin export rapor dispatches job', function () {
    Queue::fake();

    $this->actingAs($this->admin)
        ->post('/admin/analytics/export-rapor', [
            'classroom_id' => $this->classroom->id,
            'academic_year_id' => $this->academicYear->id,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    Queue::assertPushed(ExportNilaiRaporJob::class, function ($job) {
        return $job->classroomId === $this->classroom->id
            && $job->academicYearId === $this->academicYear->id
            && $job->requestedByUserId === $this->admin->id;
    });
});

test('admin export rapor requires classroom and academic year', function () {
    $this->actingAs($this->admin)
        ->post('/admin/analytics/export-rapor', [])
        ->assertSessionHasErrors(['classroom_id', 'academic_year_id']);
});

test('download export returns 404 for missing file', function () {
    $this->actingAs($this->admin)
        ->get('/admin/analytics/download-export/nonexistent-file.xlsx')
        ->assertNotFound();
});
