# Coding Conventions

## PHP / Laravel
- `declare(strict_types=1)` di semua file PHP.
- Semua method harus punya return type declaration.
- Gunakan PHP Enum untuk values tetap (role, status, tipe soal).
- Business logic di `app/Services/`, controller hanya handle request/response.
- Semua validasi di Form Request class, bukan di controller.
- Gunakan Laravel Policy untuk authorization, bukan manual check.
- Selalu eager load dengan `with()`. N+1 DILARANG.
- Queue heavy tasks: grading batch, export, import.

## Vue / TypeScript / Frontend
- Composition API only — tidak menggunakan Options API.
- Selalu `<script setup lang="ts">`.
- Reusable logic → extract ke `composables/`.
- UI base: `components/ui/` (shadcn-vue). Jangan install UI library lain.
- DataTable: TanStack Table (`@tanstack/vue-table`), wrapper di `Components/DataTable/`.
- `defineProps<T>()` dan `defineEmits<T>()` dengan TypeScript interface.
- Semua data dari backend harus punya TypeScript interface di `types/`.

## Naming Conventions
| What | Convention | Example |
|------|-----------|---------|
| DB tables | snake_case, plural | `exam_sessions` |
| Models | PascalCase, singular | `ExamSession` |
| Controllers | PascalCase + Controller | `ExamSessionController` |
| Vue components | PascalCase | `QuestionCard.vue` |
| Composables | camelCase + `use` prefix | `useExamTimer.ts` |
| TS types | PascalCase | `ExamSession` |
| TS files | camelCase | `exam.ts` |
| Routes | kebab-case | `/guru/bank-soal` |
| Enum values | PascalCase | `UserRole::Guru` |

## Language Rules
- **Code**: English (variables, functions, classes, DB columns, TS interfaces)
- **UI text**: Bahasa Indonesia (labels, messages, notifications, enum display)
- **Comments**: Bahasa Indonesia OK untuk complex business logic, English untuk general
