<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\CompetencyStandard;
use App\Models\Question;
use App\Models\QuestionBank;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompetencyController extends Controller
{
    public function index(QuestionBank $bankSoal): Response
    {
        $this->authorize('view', $bankSoal);

        $bankSoal->load('subject');
        $competencies = CompetencyStandard::where('subject_id', $bankSoal->subject_id)
            ->orderBy('code')
            ->get();

        $questions = $bankSoal->questions()
            ->with('competencyStandards')
            ->orderBy('order')
            ->get()
            ->map(fn ($q) => [
                'id' => $q->id,
                'content_preview' => mb_substr(strip_tags($q->content), 0, 80),
                'type' => $q->type->value,
                'competency_standard_ids' => $q->competencyStandards->pluck('id')->toArray(),
            ]);

        return Inertia::render('Guru/BankSoal/Kompetensi', [
            'questionBank' => [
                'id' => $bankSoal->id,
                'name' => $bankSoal->name,
                'subject_id' => $bankSoal->subject_id,
                'subject_name' => $bankSoal->subject->name,
            ],
            'competencies' => $competencies,
            'questions' => $questions,
        ]);
    }

    public function store(Request $request, QuestionBank $bankSoal): RedirectResponse
    {
        $this->authorize('update', $bankSoal);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        CompetencyStandard::create([
            ...$validated,
            'subject_id' => $bankSoal->subject_id,
        ]);

        return back()->with('success', 'Kompetensi dasar berhasil ditambahkan.');
    }

    public function update(Request $request, QuestionBank $bankSoal, CompetencyStandard $competency): RedirectResponse
    {
        $this->authorize('update', $bankSoal);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $competency->update($validated);

        return back()->with('success', 'Kompetensi dasar berhasil diperbarui.');
    }

    public function destroy(QuestionBank $bankSoal, CompetencyStandard $competency): RedirectResponse
    {
        $this->authorize('update', $bankSoal);

        $competency->delete();

        return back()->with('success', 'Kompetensi dasar berhasil dihapus.');
    }

    public function tagQuestion(Request $request, QuestionBank $bankSoal, Question $soal): RedirectResponse
    {
        $this->authorize('update', $bankSoal);

        $validated = $request->validate([
            'competency_standard_ids' => ['present', 'array'],
            'competency_standard_ids.*' => ['integer', 'exists:competency_standards,id'],
        ]);

        $soal->competencyStandards()->sync($validated['competency_standard_ids']);

        return back()->with('success', 'Tag KD berhasil disimpan.');
    }
}
