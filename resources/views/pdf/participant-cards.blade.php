<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kartu Peserta - {{ $examSession->name }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Times New Roman', serif; font-size: 10pt; color: #000; }
        @page { margin: 10mm; }

        .page-header {
            text-align: center;
            margin-bottom: 10px;
        }
        .page-header .logo { height: 40px; margin-bottom: 3px; }
        .page-header h1 { font-size: 13pt; font-weight: bold; text-transform: uppercase; margin: 0; }
        .page-header h2 { font-size: 11pt; font-weight: normal; margin: 2px 0 0 0; }

        .cards-table {
            width: 100%;
            border-collapse: collapse;
        }
        .cards-table td {
            width: 50%;
            vertical-align: top;
            padding: 4px;
        }

        .card {
            border: 1px dashed #999;
            padding: 8px;
            height: 230px;
            position: relative;
        }
        .card-header {
            text-align: center;
            border-bottom: 1px solid #ccc;
            padding-bottom: 4px;
            margin-bottom: 6px;
        }
        .card-header .card-logo { height: 25px; margin-bottom: 2px; }
        .card-header .school-name { font-size: 9pt; font-weight: bold; text-transform: uppercase; }
        .card-header .exam-title { font-size: 8pt; color: #333; margin-top: 1px; }

        .card-body {
            width: 100%;
        }
        .card-body td {
            border: none;
            padding: 1px 4px;
            font-size: 9pt;
            vertical-align: top;
        }
        .card-body .label {
            width: 70px;
            font-weight: bold;
            white-space: nowrap;
        }
        .card-body .separator {
            width: 10px;
            text-align: center;
        }

        .photo-cell {
            width: 60px;
            vertical-align: top;
            text-align: center;
        }
        .photo-placeholder {
            width: 55px;
            height: 70px;
            border: 1px solid #ccc;
            background: #f5f5f5;
            text-align: center;
            line-height: 70px;
            font-size: 18pt;
            font-weight: bold;
            color: #999;
        }
        .photo-placeholder img {
            width: 55px;
            height: 70px;
            object-fit: cover;
        }

        .token-box {
            text-align: center;
            margin-top: 6px;
            padding: 3px;
            border: 1px solid #333;
            background: #f9f9f9;
        }
        .token-label { font-size: 7pt; text-transform: uppercase; color: #666; }
        .token-value { font-size: 14pt; font-weight: bold; font-family: 'Courier New', monospace; letter-spacing: 3px; }
    </style>
</head>
<body>

@php
    $chunks = $students->chunk(6);
@endphp

@foreach($chunks as $chunkIndex => $chunk)
    @if($chunkIndex > 0)
        <div style="page-break-before: always;"></div>
    @endif

    <div class="page-header">
        @if($logoPath)
            @php
                $fullLogoPath = public_path('storage/' . $logoPath);
                if (!file_exists($fullLogoPath)) {
                    $fullLogoPath = public_path($logoPath);
                }
            @endphp
            @if(file_exists($fullLogoPath))
                <img src="{{ $fullLogoPath }}" class="logo" alt="Logo">
            @endif
        @endif
        <h1>{{ $schoolName }}</h1>
        <h2>Kartu Peserta Ujian: {{ $examSession->name }}</h2>
    </div>

    <table class="cards-table">
        @foreach($chunk->chunk(2) as $row)
            <tr>
                @foreach($row as $student)
                    <td>
                        <div class="card">
                            <div class="card-header">
                                <div class="school-name">{{ $schoolName }}</div>
                                <div class="exam-title">{{ $examSession->name }} — {{ $examSession->subject->name ?? '' }}</div>
                            </div>

                            <table class="card-body" cellspacing="0">
                                <tr>
                                    <td colspan="3" style="padding: 0;">
                                        <table class="card-body" cellspacing="0" style="width: 100%;">
                                            <tr>
                                                <td style="vertical-align: top; padding: 0;">
                                                    <table class="card-body" cellspacing="0">
                                                        <tr>
                                                            <td class="label">Nama</td>
                                                            <td class="separator">:</td>
                                                            <td>{{ $student['name'] }}</td>
                                                        </tr>
                                                        <tr>
                                                            <td class="label">NIS</td>
                                                            <td class="separator">:</td>
                                                            <td>{{ $student['nis'] }}</td>
                                                        </tr>
                                                        <tr>
                                                            <td class="label">Kelas</td>
                                                            <td class="separator">:</td>
                                                            <td>{{ $student['classroom'] }}</td>
                                                        </tr>
                                                        <tr>
                                                            <td class="label">Jurusan</td>
                                                            <td class="separator">:</td>
                                                            <td>{{ $student['department'] ?? '-' }}</td>
                                                        </tr>
                                                        <tr>
                                                            <td class="label">Tanggal</td>
                                                            <td class="separator">:</td>
                                                            <td>{{ $examDate }}</td>
                                                        </tr>
                                                    </table>
                                                </td>
                                                <td class="photo-cell">
                                                    <div class="photo-placeholder">
                                                        @if(!empty($student['photo_path']))
                                                            @php
                                                                $photoPath = public_path('storage/' . $student['photo_path']);
                                                            @endphp
                                                            @if(file_exists($photoPath))
                                                                <img src="{{ $photoPath }}" alt="Foto">
                                                            @else
                                                                {{ $student['initials'] }}
                                                            @endif
                                                        @else
                                                            {{ $student['initials'] }}
                                                        @endif
                                                    </div>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <div class="token-box">
                                <div class="token-label">Token Ujian</div>
                                <div class="token-value">{{ $examSession->token }}</div>
                            </div>
                        </div>
                    </td>
                @endforeach
                @if($row->count() === 1)
                    <td></td>
                @endif
            </tr>
        @endforeach
    </table>
@endforeach

</body>
</html>
