import type { User } from '@/types/auth';
import type { Classroom, Subject } from '@/types/academic';

// ─── Material ───────────────────────────────────────────────────────────────

export type MaterialType = 'file' | 'video_link' | 'text';

export interface Material {
    id: number;
    subject_id: number;
    classroom_id: number;
    user_id: number;
    title: string;
    description: string | null;
    type: MaterialType;
    file_path: string | null;
    file_original_name: string | null;
    file_size: number | null;
    video_url: string | null;
    text_content: string | null;
    topic: string | null;
    order: number;
    is_published: boolean;
    created_at: string;
    updated_at: string;
    // Relations
    subject?: Subject;
    classroom?: Classroom;
    user?: User;
    progress?: MaterialProgress[];
    // Computed
    formatted_file_size?: string;
    completion_count?: number;
    total_students?: number;
}

export interface MaterialProgress {
    id: number;
    material_id: number;
    user_id: number;
    is_completed: boolean;
    completed_at: string | null;
}

export interface MaterialForm {
    title: string;
    description: string | null;
    subject_id: number | null;
    classroom_id: number | null;
    type: MaterialType;
    file: File | null;
    video_url: string | null;
    text_content: string | null;
    topic: string | null;
    order: number;
    is_published: boolean;
}

// ─── Assignment ──────────────────────────────────────────────────────────────

export type SubmissionType = 'file' | 'text' | 'file_or_text';
export type SubmissionStatus = 'not_submitted' | 'submitted' | 'late' | 'graded';

export interface Assignment {
    id: number;
    subject_id: number;
    classroom_id: number;
    user_id: number;
    title: string;
    description: string;
    file_path: string | null;
    file_original_name: string | null;
    deadline_at: string;
    max_score: number;
    allow_late_submission: boolean;
    late_penalty_percent: number;
    submission_type: SubmissionType;
    is_published: boolean;
    created_at: string;
    updated_at: string;
    // Relations
    subject?: Subject;
    classroom?: Classroom;
    user?: User;
    submissions?: AssignmentSubmission[];
    my_submission?: AssignmentSubmission | null;
    // Computed
    is_overdue?: boolean;
    submission_count?: number;
    graded_count?: number;
    total_students?: number;
}

export interface AssignmentSubmission {
    id: number;
    assignment_id: number;
    user_id: number;
    content: string | null;
    file_path: string | null;
    file_original_name: string | null;
    submitted_at: string;
    is_late: boolean;
    score: number | null;
    feedback: string | null;
    graded_at: string | null;
    graded_by: number | null;
    status: SubmissionStatus;
    // Relations
    user?: User;
    assignment?: Assignment;
}

// ─── Discussion ──────────────────────────────────────────────────────────────

export interface DiscussionThread {
    id: number;
    subject_id: number;
    classroom_id: number;
    user_id: number;
    title: string;
    content: string;
    is_pinned: boolean;
    is_locked: boolean;
    last_reply_at: string | null;
    reply_count: number;
    created_at: string;
    updated_at: string;
    user?: User;
    subject?: Subject;
    classroom?: Classroom;
    latest_reply?: DiscussionReply;
    replies?: DiscussionReply[];
}

export interface DiscussionReply {
    id: number;
    discussion_thread_id: number;
    user_id: number;
    content: string;
    created_at: string;
    updated_at: string;
    user?: User;
}

// ─── Announcement ────────────────────────────────────────────────────────────

export interface Announcement {
    id: number;
    user_id: number;
    classroom_id: number | null;
    subject_id: number | null;
    title: string;
    content: string;
    is_pinned: boolean;
    published_at: string;
    created_at: string;
    updated_at: string;
    user?: User;
    classroom?: Classroom;
    subject?: Subject;
}

// ─── Attendance ──────────────────────────────────────────────────────────────

export type AttendanceStatus = 'hadir' | 'izin' | 'sakit' | 'alfa';

export interface Attendance {
    id: number;
    classroom_id: number;
    subject_id: number;
    user_id: number;
    meeting_date: string;
    meeting_number: number;
    access_code: string | null;
    code_expires_at: string | null;
    is_open: boolean;
    note: string | null;
    created_at: string;
    updated_at: string;
    // Relations
    classroom?: Classroom;
    subject?: Subject;
    user?: User;
    records?: AttendanceRecord[];
    // Computed
    is_code_expired?: boolean;
    present_count?: number;
    absent_count?: number;
    total_students?: number;
}

export interface AttendanceRecord {
    id: number;
    attendance_id: number;
    user_id: number;
    status: AttendanceStatus;
    checked_in_at: string | null;
    note: string | null;
    user?: User;
}

export interface AttendanceRecap {
    user: User;
    total_meetings: number;
    hadir: number;
    izin: number;
    sakit: number;
    alfa: number;
    percentage: number;
}

// ─── Dashboard ───────────────────────────────────────────────────────────────

export interface GuruDashboardLmsStats {
    pending_submissions: number;
    today_attendance_sessions: Attendance[];
    recent_materials: Material[];
    recent_announcements: Announcement[];
}

export interface SiswaDashboardLmsStats {
    upcoming_assignments: Assignment[];
    recent_materials: Material[];
    recent_announcements: Announcement[];
    today_attendance: { has_session: boolean; is_checked_in: boolean; attendance_id?: number } | null;
}
