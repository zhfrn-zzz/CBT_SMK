// Grading & Results types

export type GradingExamSession = {
    id: number;
    name: string;
    subject?: { id: number; name: string };
    classrooms?: { id: number; name: string }[];
    status: string;
    kkm: number | null;
    is_results_published: boolean;
    total_attempts: number;
    graded_attempts: number;
    ungraded_essays: number;
    starts_at: string;
    created_at: string;
};

export type GradingAttempt = {
    id: number;
    user: {
        id: number;
        name: string;
        username: string;
    };
    started_at: string;
    submitted_at: string | null;
    score: number | null;
    is_fully_graded: boolean;
    is_force_submitted: boolean;
    status: string;
    pass_status: 'lulus' | 'remedial' | null;
};

export type GradingAnswer = {
    id: number;
    question_id: number;
    question: {
        id: number;
        type: string;
        type_label: string;
        content: string;
        points: number;
        explanation: string | null;
        media_url: string | null;
        options: {
            label: string;
            content: string;
            is_correct: boolean;
        }[];
    };
    answer: string | null;
    score: number | null;
    is_correct: boolean | null;
    feedback: string | null;
    needs_grading?: boolean;
};

export type GradingProgress = {
    total_attempts: number;
    fully_graded: number;
    ungraded_essays: number;
};

export type ExamStatistics = {
    average: number;
    highest: number;
    lowest: number;
    total_students: number;
    passed: number;
    failed: number;
};

export type OtherAttempt = {
    id: number;
    user_name: string;
    is_fully_graded: boolean;
};

// Siswa result types
export type SiswaResult = {
    id: number;
    exam_session_id: number;
    exam_name: string;
    subject: string;
    score: number | null;
    kkm: number | null;
    pass_status: 'lulus' | 'remedial' | null;
    is_fully_graded: boolean;
    submitted_at: string | null;
};

// Dashboard types
export type AdminDashboardStats = {
    total_users: number;
    total_guru: number;
    total_siswa: number;
    active_exams: number;
    exams_today: number;
};

export type GuruDashboardStats = {
    class_count: number;
    upcoming_exams: number;
    ungraded_essays: number;
    pending_submissions: number;
};

export type SiswaDashboardStats = {
    upcoming_exams: number;
    completed_exams: number;
    latest_score: number | null;
};

export type RecentExam = {
    id: number;
    name: string;
    subject: string;
    status: string;
    status_label: string;
    starts_at: string;
    guru?: string;
    total_attempts?: number;
};

export type RecentResult = {
    id: number;
    exam_name: string;
    subject: string;
    score: number | null;
    pass_status: 'lulus' | 'remedial' | null;
    submitted_at: string | null;
};

// ─── Dashboard Today Section Types ──────────────────────────────────────────

export interface DashboardAnnouncement {
    id: number;
    title: string;
    content: string;
    is_pinned: boolean;
    published_at: string;
    user?: { id: number; name: string } | null;
}

export interface AdminTodaySection {
    announcements: DashboardAnnouncement[];
    recentAuditLogs: {
        id: number;
        action: string;
        description: string | null;
        user: { id: number; name: string; role: string } | null;
        created_at: string;
    }[];
    activeExamAlert: {
        count: number;
        names: string[];
    };
}

export interface GuruActiveExam {
    id: number;
    name: string;
    subject: string;
    in_progress_count: number;
}

export interface GuruPendingGrading {
    subject: string;
    count: number;
}

export interface GuruTodayAttendance {
    id: number;
    classroom: string;
    subject: string;
    meeting_number: number;
    is_open: boolean;
}

export interface GuruTodaySection {
    announcements: DashboardAnnouncement[];
    active_exams: GuruActiveExam[];
    pending_grading: GuruPendingGrading[];
    today_attendance: GuruTodayAttendance[];
}

export interface SiswaUpcomingExam {
    id: number;
    name: string;
    subject: string;
    starts_at: string;
    ends_at: string | null;
    status: string;
    status_label: string;
}

export interface SiswaDeadlineAssignment {
    id: number;
    title: string;
    subject: string;
    classroom: string;
    deadline_at: string;
}

export interface SiswaNewMaterial {
    id: number;
    title: string;
    subject: string;
    classroom: string;
    type: string;
    created_at: string;
}

export interface SiswaTodaySection {
    announcements: DashboardAnnouncement[];
    upcoming_exams: SiswaUpcomingExam[];
    deadline_assignments: SiswaDeadlineAssignment[];
    new_materials: SiswaNewMaterial[];
}
