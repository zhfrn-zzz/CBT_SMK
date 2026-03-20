<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $examSession->name }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Times New Roman', serif; font-size: 12pt; color: #000; }
        .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 15px; }
        .header h1 { font-size: 14pt; font-weight: bold; text-transform: uppercase; }
        .header h2 { font-size: 12pt; margin-top: 4px; }
        .info-table { width: 100%; margin-bottom: 15px; font-size: 11pt; }
        .info-table td { padding: 2px 8px; }
        .info-table td:first-child { width: 150px; font-weight: bold; }
        .instructions { border: 1px solid #000; padding: 10px; margin-bottom: 20px; font-size: 11pt; }
        .instructions h3 { font-weight: bold; margin-bottom: 5px; }
        .question { margin-bottom: 18px; }
        .question-number { font-weight: bold; }
        .question-content { margin-left: 25px; margin-top: 4px; }
        .question-media { margin-left: 25px; margin-top: 6px; }
        .question-media img { max-width: 200px; max-height: 150px; }
        .options { margin-left: 40px; margin-top: 6px; }
        .option { margin-bottom: 3px; }
        .matching-table { margin-left: 40px; margin-top: 6px; border-collapse: collapse; }
        .matching-table td, .matching-table th { padding: 3px 10px; border: 1px solid #666; }
        .essay-space { margin-left: 25px; margin-top: 6px; }
        .essay-line { border-bottom: 1px solid #999; height: 25px; margin-bottom: 5px; }
        .answer-sheet { margin-top: 40px; border-top: 2px solid #000; padding-top: 15px; }
        .answer-sheet h3 { font-size: 13pt; font-weight: bold; text-align: center; margin-bottom: 10px; }
        .section-title { font-size: 12pt; font-weight: bold; margin: 15px 0 10px 0; border-bottom: 1px solid #ccc; padding-bottom: 3px; }
        .answer-table { width: 100%; border-collapse: collapse; font-size: 10pt; }
        .answer-table th, .answer-table td { border: 1px solid #666; padding: 4px; text-align: center; }
        .answer-table th { background: #f0f0f0; font-weight: bold; }
        @page { margin: 2cm; }
    </style>
</head>
<body>

<div class="header">
    <h1>Lembar Soal Ujian</h1>
    <h2>{{ $examSession->name }}</h2>
</div>

<table class="info-table">
    <tr>
        <td>Mata Pelajaran</td>
        <td>: {{ $examSession->subject->name }}</td>
        <td>Durasi</td>
        <td>: {{ $examSession->duration_minutes }} menit</td>
    </tr>
    <tr>
        <td>Kelas</td>
        <td>: {{ $examSession->classrooms->pluck('name')->join(', ') ?: '-' }}</td>
        <td>Tanggal</td>
        <td>: {{ \Carbon\Carbon::parse($examSession->starts_at)->format('d F Y') }}</td>
    </tr>
    <tr>
        <td>Jumlah Soal</td>
        <td>: {{ $questions->count() }} soal</td>
        <td>Token</td>
        <td>: {{ $examSession->token }}</td>
    </tr>
</table>

<div class="instructions">
    <h3>Petunjuk Pengerjaan:</h3>
    <ol style="margin-left: 15px; margin-top: 5px;">
        <li>Baca setiap soal dengan teliti sebelum menjawab.</li>
        <li>Kerjakan soal yang mudah terlebih dahulu.</li>
        <li>Periksa kembali jawaban Anda sebelum dikumpulkan.</li>
    </ol>
</div>

@php
    $pgQuestions = $questions->filter(fn($q) => $q->type->value === 'pilihan_ganda');
    $bsQuestions = $questions->filter(fn($q) => $q->type->value === 'benar_salah');
    $essayQuestions = $questions->filter(fn($q) => in_array($q->type->value, ['esai', 'isian_singkat']));
    $matchingQuestions = $questions->filter(fn($q) => $q->type->value === 'menjodohkan');
    $no = 1;
@endphp

@if($pgQuestions->count() > 0)
<p class="section-title">A. PILIHAN GANDA</p>
<p style="font-size:11pt; margin-bottom:10px;">Pilihlah jawaban yang paling tepat!</p>
@foreach($pgQuestions as $question)
<div class="question">
    <span class="question-number">{{ $no++ }}.</span>
    <span class="question-content">{!! strip_tags($question->content) !!}</span>
    @if($question->media_path)
    <div class="question-media">
        <img src="{{ storage_path('app/public/'.$question->media_path) }}" alt="Gambar soal" />
    </div>
    @endif
    <div class="options">
        @foreach($question->options as $option)
        <div class="option">{{ $option->label }}. {!! strip_tags($option->content) !!}</div>
        @endforeach
    </div>
</div>
@endforeach
@endif

@if($bsQuestions->count() > 0)
<p class="section-title">B. BENAR / SALAH</p>
<p style="font-size:11pt; margin-bottom:10px;">Tuliskan B jika pernyataan benar, atau S jika pernyataan salah!</p>
@foreach($bsQuestions as $question)
<div class="question">
    <span class="question-number">{{ $no++ }}.</span>
    <span class="question-content">{!! strip_tags($question->content) !!}</span>
    <div class="options">
        <div class="option">&nbsp;&nbsp;&nbsp;(B) Benar &nbsp;&nbsp;&nbsp; (S) Salah</div>
    </div>
</div>
@endforeach
@endif

@if($matchingQuestions->count() > 0)
<p class="section-title">C. MENJODOHKAN</p>
<p style="font-size:11pt; margin-bottom:10px;">Jodohkan pernyataan di kolom kiri dengan jawaban di kolom kanan!</p>
@foreach($matchingQuestions as $question)
<div class="question">
    <span class="question-number">{{ $no++ }}.</span>
    <span class="question-content">{!! strip_tags($question->content) !!}</span>
    <table class="matching-table">
        <tr>
            <th style="background:#eee;">Pernyataan</th>
            <th style="background:#eee;">Jawaban</th>
        </tr>
        @foreach($question->matchingPairs as $pair)
        <tr>
            <td>{{ $pair->left_item }}</td>
            <td>{{ $pair->right_item }}</td>
        </tr>
        @endforeach
    </table>
</div>
@endforeach
@endif

@if($essayQuestions->count() > 0)
<p class="section-title">D. ESAI / ISIAN</p>
<p style="font-size:11pt; margin-bottom:10px;">Jawablah pertanyaan berikut dengan benar dan lengkap!</p>
@foreach($essayQuestions as $question)
<div class="question">
    <span class="question-number">{{ $no++ }}.</span>
    <span class="question-content">{!! strip_tags($question->content) !!}</span>
    @if($question->media_path)
    <div class="question-media">
        <img src="{{ storage_path('app/public/'.$question->media_path) }}" alt="Gambar soal" />
    </div>
    @endif
    <div class="essay-space">
        @if($question->type->value === 'isian_singkat')
        <div class="essay-line"></div>
        @else
        <div class="essay-line"></div>
        <div class="essay-line"></div>
        <div class="essay-line"></div>
        <div class="essay-line"></div>
        @endif
    </div>
</div>
@endforeach
@endif

@if($pgQuestions->count() > 0)
<div class="answer-sheet">
    <h3>Lembar Jawaban Pilihan Ganda</h3>
    <table class="answer-table">
        <tr>
            <th>No</th>
            <th>A</th>
            <th>B</th>
            <th>C</th>
            <th>D</th>
        </tr>
        @foreach($pgQuestions->values() as $idx => $q)
        <tr>
            <td>{{ $idx + 1 }}</td>
            <td style="width:50px;">( )</td>
            <td style="width:50px;">( )</td>
            <td style="width:50px;">( )</td>
            <td style="width:50px;">( )</td>
        </tr>
        @endforeach
    </table>
</div>
@endif

</body>
</html>
