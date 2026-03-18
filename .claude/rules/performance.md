# Performance Rules (CRITICAL — HDD + 16GB RAM)

Server: Intel Xeon 20 core, 16GB RAM, HDD (~5-10ms latency vs SSD ~0.1ms). 500+ concurrent CBT users.

## Hard Rules
1. **NEVER** query tanpa index pada tabel besar (`student_answers`, `exam_activity_logs`).
2. **ALWAYS** use Redis untuk session, cache, dan queue driver. Jangan pakai `file` atau `database` driver.
3. **ALWAYS** eager load relasi dengan `with()`. N+1 query DILARANG.
4. **ALWAYS** paginate DataTable/listing. Max 50 items per page.
5. **NEVER** simpan file upload di database sebagai blob. Gunakan `storage/app/materials/`.
6. **ALWAYS** queue operasi berat (grading batch, export, import). Jangan blocking request.

## Auto-Save Buffer Strategy
- Client-side: buffer jawaban, kirim batch setiap 30 detik.
- Server-side: simpan ke Redis dulu, persist ke MySQL via Queue job setiap 60 detik.
- Ini mengurangi disk I/O drastis di HDD.

## Database Indexing
- Setiap foreign key HARUS di-index.
- Kolom yang sering di-query HARUS di-index.
- Critical indexes: lihat `plan.md` section 5.5.

## Bulk Operations
- Gunakan `chunk()` atau `LazyCollection` untuk operasi bulk (import siswa, batch grading).
- Set MySQL `max_connections` sesuai jumlah PHP-FPM worker.

## Environment (HARUS Redis)
```env
SESSION_DRIVER=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis
```
