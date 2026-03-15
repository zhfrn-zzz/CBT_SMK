import type { Subject } from './academic';
import type { User } from './auth';

export type QuestionType =
    | 'pilihan_ganda'
    | 'benar_salah'
    | 'esai'
    | 'isian_singkat'
    | 'menjodohkan'
    | 'ordering'
    | 'multiple_answer';

export const questionTypeLabels: Record<QuestionType, string> = {
    pilihan_ganda: 'Pilihan Ganda',
    benar_salah: 'Benar/Salah',
    esai: 'Esai',
    isian_singkat: 'Isian Singkat',
    menjodohkan: 'Menjodohkan',
    ordering: 'Pengurutan',
    multiple_answer: 'Pilihan Ganda Kompleks',
};

export type QuestionOption = {
    id: number;
    question_id: number;
    label: string;
    content: string;
    media_path: string | null;
    is_correct: boolean;
    order: number;
    created_at: string;
    updated_at: string;
};

export type Question = {
    id: number;
    question_bank_id: number;
    type: QuestionType;
    content: string;
    media_path: string | null;
    points: number;
    explanation: string | null;
    order: number;
    metadata: Record<string, unknown> | null;
    created_at: string;
    updated_at: string;
    options?: QuestionOption[];
    media_url?: string | null;
};

export type QuestionBank = {
    id: number;
    name: string;
    subject_id: number;
    user_id: number;
    description: string | null;
    created_at: string;
    updated_at: string;
    subject?: Subject;
    user?: User;
    questions_count?: number;
    questions?: Question[];
};

// Form data types for creating/editing
export type QuestionOptionForm = {
    label: string;
    content: string;
    is_correct: boolean;
};

export type QuestionForm = {
    type: QuestionType;
    content: string;
    points: number;
    explanation: string;
    options: QuestionOptionForm[];
};

export type QuestionImportPreview = {
    row: number;
    content: string;
    type: QuestionType;
    options: string[];
    correct_answer: string;
    points: number;
    explanation: string;
    errors: string[];
};

// Exam Session types
export type ExamStatus = 'draft' | 'scheduled' | 'active' | 'completed' | 'archived';
export type ExamAttemptStatus = 'in_progress' | 'submitted' | 'graded';

export const examStatusLabels: Record<ExamStatus, string> = {
    draft: 'Draf',
    scheduled: 'Dijadwalkan',
    active: 'Berlangsung',
    completed: 'Selesai',
    archived: 'Diarsipkan',
};

export const examStatusColors: Record<ExamStatus, string> = {
    draft: 'secondary',
    scheduled: 'outline',
    active: 'default',
    completed: 'secondary',
    archived: 'destructive',
};

export type ExamSession = {
    id: number;
    name: string;
    subject_id: number;
    user_id: number;
    academic_year_id: number;
    question_bank_id: number;
    token: string;
    duration_minutes: number;
    starts_at: string;
    ends_at: string;
    is_randomize_questions: boolean;
    is_randomize_options: boolean;
    is_published: boolean;
    is_results_published: boolean;
    pool_count: number | null;
    kkm: number | null;
    max_tab_switches: number | null;
    status: ExamStatus;
    created_at: string;
    updated_at: string;
    subject?: Subject;
    question_bank?: QuestionBank;
    academic_year?: import('./academic').AcademicYear;
    classrooms?: import('./academic').Classroom[];
    attempts_count?: number;
    attempts?: ExamAttempt[];
};

export type ExamSessionForm = {
    name: string;
    subject_id: string;
    academic_year_id: string;
    question_bank_id: string;
    duration_minutes: number;
    starts_at: string;
    ends_at: string;
    is_randomize_questions: boolean;
    is_randomize_options: boolean;
    pool_count: number | string;
    kkm: number | string;
    max_tab_switches: number | string;
    classroom_ids: number[];
};

export type ExamAttempt = {
    id: number;
    exam_session_id: number;
    user_id: number;
    started_at: string;
    submitted_at: string | null;
    remaining_seconds: number | null;
    is_force_submitted: boolean;
    score: number | null;
    is_fully_graded: boolean;
    status: ExamAttemptStatus;
    user?: import('./auth').User;
};

export type StudentAnswer = {
    id: number;
    exam_attempt_id: number;
    question_id: number;
    answer: string | null;
    is_flagged: boolean;
    is_correct: boolean | null;
    score: number | null;
    feedback: string | null;
    answered_at: string | null;
};

// Exam interface payload (from server to client when starting exam)
export type ExamQuestion = {
    id: number;
    order: number;
    content: string;
    type: QuestionType;
    media_url: string | null;
    points: number;
    options: ExamQuestionOption[] | null;
};

export type ExamQuestionOption = {
    id: number;
    label: string;
    content: string;
    media_url: string | null;
};

export type ExamPayload = {
    attempt_id: number;
    exam: {
        id: number;
        name: string;
        subject: string;
        duration_minutes: number;
        total_questions: number;
        max_tab_switches: number | null;
    };
    questions: ExamQuestion[];
    saved_answers: Record<string, string>;
    flagged_questions: number[];
    started_at: number;
    server_time: number;
    remaining_seconds: number;
};

export type SaveAnswersResponse = {
    saved: boolean;
    server_time: number;
    remaining_seconds: number;
};

// Siswa exam list item
export type SiswaExamListItem = {
    id: number;
    name: string;
    subject: string;
    duration_minutes: number;
    starts_at: string;
    ends_at: string;
    status: ExamStatus;
    status_label: string;
    attempt_status: ExamAttemptStatus | null;
    attempt_status_label: string | null;
    score: number | null;
    is_published: boolean;
};
