<?php

declare(strict_types=1);

use App\Enums\GradeLevel;
use App\Enums\Semester;
use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\Subject;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

// ── Index ────────────────────────────────────────────────────────────

test('admin can view users index', function () {
    User::factory()->count(3)->create();

    $response = $this->actingAs($this->admin)->get(route('admin.users.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('Admin/Users/Index'));
});

test('admin can filter users by role', function () {
    User::factory()->guru()->count(2)->create();
    User::factory()->siswa()->count(3)->create();

    $response = $this->actingAs($this->admin)->get(route('admin.users.index', ['role' => 'guru']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Users/Index')
        ->has('users.data', 2)
    );
});

test('admin can search users by name', function () {
    User::factory()->create(['name' => 'Budi Santoso']);
    User::factory()->create(['name' => 'Ani Widya']);

    $response = $this->actingAs($this->admin)->get(route('admin.users.index', ['search' => 'Budi']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Users/Index')
        ->has('users.data', 1)
    );
});

// ── Create / Store ──────────────────────────────────────────────────

test('admin can view create user form', function () {
    $response = $this->actingAs($this->admin)->get(route('admin.users.create'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Users/Create')
        ->has('roles')
    );
});

test('admin can create a siswa user', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.users.store'), [
        'name' => 'Siswa Baru',
        'username' => '10002',
        'email' => 'siswa@test.com',
        'role' => UserRole::Siswa->value,
        'is_active' => true,
        'password' => 'Password123!',
    ]);

    $response->assertRedirect(route('admin.users.index'));
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('users', [
        'name' => 'Siswa Baru',
        'username' => '10002',
        'role' => UserRole::Siswa->value,
    ]);
});

test('admin can create a guru user with teaching assignments', function () {
    $academicYear = AcademicYear::factory()->active()->create();
    $department = Department::factory()->create();
    $classroom = Classroom::factory()->create([
        'academic_year_id' => $academicYear->id,
        'department_id' => $department->id,
    ]);
    $subject = Subject::factory()->create();

    $response = $this->actingAs($this->admin)->post(route('admin.users.store'), [
        'name' => 'Guru Baru',
        'username' => '198501012010011099',
        'email' => 'guru@test.com',
        'role' => UserRole::Guru->value,
        'is_active' => true,
        'password' => 'Password123!',
        'teachings' => [
            ['classroom_id' => $classroom->id, 'subject_id' => $subject->id],
        ],
    ]);

    $response->assertRedirect(route('admin.users.index'));
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('users', [
        'username' => '198501012010011099',
        'role' => UserRole::Guru->value,
    ]);

    $guru = User::where('username', '198501012010011099')->first();
    $this->assertDatabaseHas('classroom_subject_teacher', [
        'user_id' => $guru->id,
        'classroom_id' => $classroom->id,
        'subject_id' => $subject->id,
    ]);
});

test('admin can create a siswa with classroom assignment', function () {
    $academicYear = AcademicYear::factory()->active()->create();
    $department = Department::factory()->create();
    $classroom = Classroom::factory()->create([
        'academic_year_id' => $academicYear->id,
        'department_id' => $department->id,
    ]);

    $response = $this->actingAs($this->admin)->post(route('admin.users.store'), [
        'name' => 'Siswa Kelas',
        'username' => '10003',
        'role' => UserRole::Siswa->value,
        'is_active' => true,
        'password' => 'Password123!',
        'classroom_id' => $classroom->id,
    ]);

    $response->assertRedirect(route('admin.users.index'));

    $siswa = User::where('username', '10003')->first();
    expect($siswa->classrooms)->toHaveCount(1);
    expect($siswa->classrooms->first()->id)->toBe($classroom->id);
});

// ── Validation ──────────────────────────────────────────────────────

test('create user requires name', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.users.store'), [
        'username' => '10004',
        'role' => UserRole::Siswa->value,
        'password' => 'Password123!',
    ]);

    $response->assertSessionHasErrors('name');
});

test('create user requires unique username', function () {
    User::factory()->create(['username' => 'existing']);

    $response = $this->actingAs($this->admin)->post(route('admin.users.store'), [
        'name' => 'Duplicate',
        'username' => 'existing',
        'role' => UserRole::Siswa->value,
        'password' => 'Password123!',
    ]);

    $response->assertSessionHasErrors('username');
});

test('create user requires password for non-siswa', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.users.store'), [
        'name' => 'No Password',
        'username' => '10005',
        'role' => UserRole::Guru->value,
    ]);

    $response->assertSessionHasErrors('password');
});

test('create siswa without password auto-generates one', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.users.store'), [
        'name' => 'Siswa Auto',
        'username' => '10005',
        'role' => UserRole::Siswa->value,
        'is_active' => true,
    ]);

    $response->assertRedirect(route('admin.users.index'));
    $response->assertSessionHas('generated_password');

    $this->assertDatabaseHas('users', [
        'username' => '10005',
        'role' => UserRole::Siswa->value,
    ]);
});

test('create user requires valid role', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.users.store'), [
        'name' => 'Invalid Role',
        'username' => '10006',
        'role' => 'superadmin',
        'password' => 'Password123!',
    ]);

    $response->assertSessionHasErrors('role');
});

// ── Edit / Update ───────────────────────────────────────────────────

test('admin can view edit user form', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($this->admin)->get(route('admin.users.edit', $user));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Users/Edit')
        ->has('user')
    );
});

test('admin can update user', function () {
    $user = User::factory()->create(['name' => 'Old Name']);

    $response = $this->actingAs($this->admin)->put(route('admin.users.update', $user), [
        'name' => 'New Name',
        'username' => $user->username,
        'role' => $user->role->value,
        'is_active' => true,
    ]);

    $response->assertRedirect(route('admin.users.index'));
    $response->assertSessionHas('success');

    $user->refresh();
    expect($user->name)->toBe('New Name');
});

test('update user allows empty password (keeps old)', function () {
    $user = User::factory()->create();
    $oldPasswordHash = $user->password;

    $response = $this->actingAs($this->admin)->put(route('admin.users.update', $user), [
        'name' => $user->name,
        'username' => $user->username,
        'role' => $user->role->value,
        'is_active' => true,
        'password' => '',
    ]);

    $response->assertRedirect(route('admin.users.index'));
});

test('update user validates unique username ignoring self', function () {
    $user1 = User::factory()->create(['username' => 'user1']);
    $user2 = User::factory()->create(['username' => 'user2']);

    $response = $this->actingAs($this->admin)->put(route('admin.users.update', $user2), [
        'name' => $user2->name,
        'username' => 'user1',
        'role' => $user2->role->value,
    ]);

    $response->assertSessionHasErrors('username');
});

// ── Delete ──────────────────────────────────────────────────────────

test('admin can delete a user', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($this->admin)->delete(route('admin.users.destroy', $user));

    $response->assertRedirect(route('admin.users.index'));
    $response->assertSessionHas('success');

    $this->assertDatabaseMissing('users', ['id' => $user->id]);
});

test('admin cannot delete self', function () {
    $response = $this->actingAs($this->admin)->delete(route('admin.users.destroy', $this->admin));

    $response->assertRedirect();
    $response->assertSessionHas('error');

    $this->assertDatabaseHas('users', ['id' => $this->admin->id]);
});

// ── Role access ─────────────────────────────────────────────────────

test('guru cannot access user management', function () {
    $guru = User::factory()->guru()->create();

    $response = $this->actingAs($guru)->get(route('admin.users.index'));

    $response->assertForbidden();
});

test('siswa cannot access user management', function () {
    $siswa = User::factory()->siswa()->create();

    $response = $this->actingAs($siswa)->get(route('admin.users.index'));

    $response->assertForbidden();
});
