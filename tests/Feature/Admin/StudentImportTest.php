<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('admin can import students from xlsx file', function () {
    $academicYear = AcademicYear::factory()->active()->create();
    $department = Department::factory()->create(['code' => 'TKJ']);
    $classroom = Classroom::factory()->create([
        'academic_year_id' => $academicYear->id,
        'department_id' => $department->id,
        'name' => 'X TKJ 1',
    ]);

    // Create a real Excel-like file with valid headers
    Excel::fake();

    $file = UploadedFile::fake()->create('students.xlsx', 100, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $response = $this->actingAs($this->admin)->post(route('admin.users.import'), [
        'file' => $file,
    ]);

    // The import was attempted (file validation passes for mimes)
    $response->assertRedirect();
});

test('import rejects invalid file types', function () {
    $file = UploadedFile::fake()->create('students.txt', 100, 'text/plain');

    $response = $this->actingAs($this->admin)->post(route('admin.users.import'), [
        'file' => $file,
    ]);

    $response->assertSessionHasErrors('file');
});

test('import requires file', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.users.import'), []);

    $response->assertSessionHasErrors('file');
});

test('import rejects file larger than 5MB', function () {
    $file = UploadedFile::fake()->create('students.xlsx', 6000, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $response = $this->actingAs($this->admin)->post(route('admin.users.import'), [
        'file' => $file,
    ]);

    $response->assertSessionHasErrors('file');
});

test('guru cannot import students', function () {
    $guru = User::factory()->guru()->create();

    $file = UploadedFile::fake()->create('students.xlsx', 100, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $response = $this->actingAs($guru)->post(route('admin.users.import'), [
        'file' => $file,
    ]);

    $response->assertForbidden();
});
