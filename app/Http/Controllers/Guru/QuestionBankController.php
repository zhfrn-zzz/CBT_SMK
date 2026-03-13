<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Http\Requests\Guru\QuestionBankRequest;
use App\Models\QuestionBank;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class QuestionBankController extends Controller
{
    public function index(Request $request): Response
    {
        $query = QuestionBank::where('user_id', $request->user()->id)
            ->with('subject')
            ->withCount('questions');

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where('name', 'like', "%{$search}%");
        }

        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->integer('subject_id'));
        }

        $questionBanks = $query->latest()->paginate(15)->withQueryString();

        return Inertia::render('Guru/BankSoal/Index', [
            'questionBanks' => $questionBanks,
            'subjects' => $this->getSubjectsForGuru($request->user()),
            'filters' => $request->only(['search', 'subject_id']),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Guru/BankSoal/Create', [
            'subjects' => $this->getSubjectsForGuru($request->user()),
        ]);
    }

    public function store(QuestionBankRequest $request): RedirectResponse
    {
        QuestionBank::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return redirect()->route('guru.bank-soal.index')
            ->with('success', 'Bank soal berhasil dibuat.');
    }

    public function show(QuestionBank $bankSoal): Response
    {
        $this->authorize('view', $bankSoal);

        $bankSoal->load(['subject', 'questions.options']);

        return Inertia::render('Guru/BankSoal/Show', [
            'questionBank' => $bankSoal,
        ]);
    }

    public function edit(Request $request, QuestionBank $bankSoal): Response
    {
        $this->authorize('update', $bankSoal);

        return Inertia::render('Guru/BankSoal/Edit', [
            'questionBank' => $bankSoal,
            'subjects' => $this->getSubjectsForGuru($request->user()),
        ]);
    }

    public function update(QuestionBankRequest $request, QuestionBank $bankSoal): RedirectResponse
    {
        $this->authorize('update', $bankSoal);

        $bankSoal->update($request->validated());

        return redirect()->route('guru.bank-soal.index')
            ->with('success', 'Bank soal berhasil diperbarui.');
    }

    public function destroy(QuestionBank $bankSoal): RedirectResponse
    {
        $this->authorize('delete', $bankSoal);

        $bankSoal->delete();

        return redirect()->route('guru.bank-soal.index')
            ->with('success', 'Bank soal berhasil dihapus.');
    }

    /**
     * Ambil daftar mata pelajaran untuk guru.
     * Prioritas: mapel yang di-assign via classroom_subject_teacher.
     * Fallback: semua mapel (untuk guru yang belum di-assign ke kelas).
     */
    private function getSubjectsForGuru(User $user): Collection
    {
        $subjects = Subject::whereHas('teachers', function ($q) use ($user) {
            $q->where('users.id', $user->id);
        })->select('id', 'name', 'code')->orderBy('name')->get();

        if ($subjects->isEmpty()) {
            $subjects = Subject::select('id', 'name', 'code')->orderBy('name')->get();
        }

        return $subjects;
    }
}
