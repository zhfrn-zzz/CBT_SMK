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
