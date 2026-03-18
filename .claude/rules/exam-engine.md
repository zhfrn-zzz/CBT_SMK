# CBT Exam Engine — Technical Design

## Auto-Save Flow

### Client Side (Vue)
1. Siswa klik jawaban → update reactive state (`useExamState` composable)
2. Immediately write to `localStorage`: `exam_${sessionId}_answers`
3. `useAutoSave` composable: setiap 30 detik, POST `/save-answers`
   - Body: `{ answers: Record<string, string>, timestamp: number }`
4. On success: update `last_saved_at` indicator
5. On failure: retry in 10s, max 3 retries, then show warning

### Server Side (Laravel)
1. Controller receives answers JSON
2. Validate: exam still active, user owns attempt, within time window
3. Write to Redis: `SET exam:{sessionId}:student:{userId}:answers JSON`
4. Write to Redis: `SET exam:{sessionId}:student:{userId}:last_save TIMESTAMP`
5. Return: `{ saved: true, server_time: number, remaining_seconds: number }`

### Queue Job (PersistAnswersJob)
1. Runs every 60 seconds via scheduler
2. SCAN Redis keys matching `exam:*:student:*:answers`
3. For each: upsert into `student_answers` table (batch)
4. DO NOT delete Redis key (keep for fast reads)
5. Delete Redis key only after exam is submitted

## Timer Synchronization
1. Start exam: server returns `{ started_at, duration_seconds, server_time }`
2. Client calculates: `remaining = duration_seconds - (server_time - started_at)`
3. Client runs local countdown (`setInterval` every 1 second)
4. Every auto-save response includes `remaining_seconds` from server
5. Client reconciles: if server differs > 3 seconds → use server value
6. At 0: client auto-submits
7. Server-side: scheduled command force-submits expired attempts

## Question Loading Strategy
Single request loads all data saat siswa mulai ujian:
```
POST /siswa/ujian/{id}/start → Inertia page with ExamStartPayload
```
Semua soal dimuat sekaligus ke client state. Navigasi antar soal = ZERO server requests.

```typescript
interface ExamStartPayload {
  exam: { name: string; duration: number; total_questions: number };
  questions: Array<{
    id: number; order: number; content: string;
    type: QuestionType; media_url: string | null;
    options: Array<{ id: number; label: string; content: string }> | null;
  }>;
  saved_answers: Record<string, string>;
  started_at: number; server_time: number; remaining_seconds: number;
}
```

## Redis Key Structure
```
Exam-specific:
├── exam:{examSessionId}:student:{userId}:answers      -- JSON jawaban
├── exam:{examSessionId}:student:{userId}:last_save     -- timestamp
├── exam:{examSessionId}:student:{userId}:flags         -- JSON flagged
├── exam:{examSessionId}:online_count                   -- atomic counter
└── exam:{examSessionId}:question_set:{userId}          -- cached randomized set

TTL: semua exam keys expire 24 jam setelah exam_session.ends_at
```
