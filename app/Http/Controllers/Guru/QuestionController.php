<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guru;

use App\Enums\QuestionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Guru\QuestionRequest;
use App\Models\Question;
use App\Models\QuestionBank;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class QuestionController extends Controller
{
    public function create(QuestionBank $bankSoal): Response
    {
        $this->authorize('update', $bankSoal);

        return Inertia::render('Guru/BankSoal/Soal/Create', [
            'questionBank' => $bankSoal->only('id', 'name'),
        ]);
    }

    public function store(QuestionRequest $request, QuestionBank $bankSoal): RedirectResponse
    {
        $this->authorize('update', $bankSoal);

        DB::transaction(function () use ($request, $bankSoal) {
            $maxOrder = $bankSoal->questions()->max('order') ?? 0;

            $question = $bankSoal->questions()->create([
                'type' => $request->validated('type'),
                'content' => $request->validated('content'),
                'points' => $request->validated('points'),
                'explanation' => $request->validated('explanation'),
                'order' => $maxOrder + 1,
            ]);

            if ($request->hasFile('media')) {
                $path = $request->file('media')->store('questions', 'public');
                $question->update(['media_path' => $path]);
            }

            $this->saveOptions($question, $request->validated('options', []));
        });

        return redirect()->route('guru.bank-soal.show', $bankSoal)
            ->with('success', 'Soal berhasil ditambahkan.');
    }

    public function edit(QuestionBank $bankSoal, Question $soal): Response
    {
        $this->authorize('update', $bankSoal);

        $soal->load('options');

        return Inertia::render('Guru/BankSoal/Soal/Edit', [
            'questionBank' => $bankSoal->only('id', 'name'),
            'question' => $soal,
        ]);
    }

    public function update(QuestionRequest $request, QuestionBank $bankSoal, Question $soal): RedirectResponse
    {
        $this->authorize('update', $bankSoal);

        DB::transaction(function () use ($request, $soal) {
            $soal->update([
                'type' => $request->validated('type'),
                'content' => $request->validated('content'),
                'points' => $request->validated('points'),
                'explanation' => $request->validated('explanation'),
            ]);

            if ($request->hasFile('media')) {
                if ($soal->media_path) {
                    Storage::disk('public')->delete($soal->media_path);
                }
                $path = $request->file('media')->store('questions', 'public');
                $soal->update(['media_path' => $path]);
            }

            if ($request->boolean('remove_media') && $soal->media_path) {
                Storage::disk('public')->delete($soal->media_path);
                $soal->update(['media_path' => null]);
            }

            // Replace options
            $soal->options()->delete();
            $this->saveOptions($soal, $request->validated('options', []));
        });

        return redirect()->route('guru.bank-soal.show', $bankSoal)
            ->with('success', 'Soal berhasil diperbarui.');
    }

    public function destroy(QuestionBank $bankSoal, Question $soal): RedirectResponse
    {
        $this->authorize('update', $bankSoal);

        if ($soal->media_path) {
            Storage::disk('public')->delete($soal->media_path);
        }

        $soal->delete();

        return redirect()->route('guru.bank-soal.show', $bankSoal)
            ->with('success', 'Soal berhasil dihapus.');
    }

    public function uploadImage(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:2048'],
        ]);

        $path = $request->file('image')->store('questions/content', 'public');

        return response()->json([
            'url' => Storage::disk('public')->url($path),
        ]);
    }

    private function saveOptions(Question $question, array $options): void
    {
        foreach ($options as $index => $option) {
            $question->options()->create([
                'label' => $option['label'],
                'content' => $option['content'],
                'is_correct' => (bool) $option['is_correct'],
                'order' => $index,
            ]);
        }
    }
}
