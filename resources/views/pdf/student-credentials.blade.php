<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kredensial Siswa</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Times New Roman', serif; font-size: 11pt; color: #000; }
        .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 15px; }
        .header h1 { font-size: 14pt; font-weight: bold; text-transform: uppercase; }
        .header h2 { font-size: 12pt; margin-top: 4px; }
        .header .date { font-size: 10pt; margin-top: 4px; color: #555; }
        .logo { height: 50px; margin-bottom: 5px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #333; padding: 6px 10px; text-align: left; font-size: 10pt; }
        th { background-color: #f0f0f0; font-weight: bold; text-align: center; }
        td:first-child { text-align: center; width: 40px; }
        .warning { margin-top: 20px; padding: 10px; border: 2px solid #c00; background-color: #fff5f5; font-size: 10pt; }
        .warning strong { color: #c00; }
        @page { margin: 20mm 15mm; }
    </style>
</head>
<body>
    <div class="header">
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
        <h2>Daftar Kredensial Siswa</h2>
        <p class="date">Tanggal: {{ $date }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>NIS</th>
                <th>Nama</th>
                <th>Password</th>
            </tr>
        </thead>
        <tbody>
            @foreach($credentials as $index => $credential)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $credential['username'] }}</td>
                    <td>{{ $credential['name'] }}</td>
                    <td style="font-family: 'Courier New', monospace; font-weight: bold;">{{ $credential['password'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="warning">
        <strong>⚠ PERHATIAN:</strong> Dokumen ini bersifat <strong>RAHASIA</strong>.
        Berisi password akun siswa dalam bentuk teks biasa.
        Simpan dengan aman dan jangan disebarkan kepada pihak yang tidak berwenang.
        Pastikan setiap siswa menerima kredensialnya masing-masing dan segera mengganti password.
    </div>
</body>
</html>
