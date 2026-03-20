export type DifficultyLabel = 'mudah' | 'sedang' | 'sulit';
export type DiscriminationLabel = 'baik' | 'cukup' | 'buruk';

export type ChoiceDistribution = {
    label: string;
    count: number;
    percentage: number;
    is_correct: boolean;
};

export type ItemAnalysisResult = {
    question_id: number;
    order: number;
    content_preview: string;
    type: string;
    total_attempts: number;
    correct_count: number;
    difficulty_index: number;
    difficulty_label: DifficultyLabel;
    discrimination_index: number;
    discrimination_label: DiscriminationLabel;
    choice_distribution: ChoiceDistribution[] | null;
    competency_standards: { code: string; name: string }[];
    skipped: boolean;
};

export type ExamAnalysis = {
    exam_session_id: number;
    computed_at: string | null;
    computing: boolean;
    items: ItemAnalysisResult[];
    summary: {
        total_questions: number;
        easy_count: number;
        medium_count: number;
        hard_count: number;
        good_discrimination_count: number;
        fair_discrimination_count: number;
        poor_discrimination_count: number;
    };
    kd_breakdown: KdBreakdown[];
};

export type KdBreakdown = {
    code: string;
    name: string;
    question_count: number;
    avg_score: number;
    max_possible: number;
};

export type CompetencyStandard = {
    id: number;
    code: string;
    name: string;
    description: string | null;
    subject_id: number;
};

export type ClassScoreTrend = {
    month: number;
    month_label: string;
    avg_score: number;
    subject_id: number;
    subject_name: string;
};

export type ClassroomStat = {
    classroom_id: number;
    classroom_name: string;
    avg_score: number;
    max_score: number;
    min_score: number;
    student_count: number;
    exam_count: number;
};
