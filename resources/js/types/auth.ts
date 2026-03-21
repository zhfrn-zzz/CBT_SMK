export type UserRole = 'admin' | 'guru' | 'siswa';

export type TeachingInfo = {
    classroom_name: string;
    subject_name: string;
};

export type User = {
    id: number;
    name: string;
    username: string;
    email: string | null;
    avatar?: string;
    photo_url?: string | null;
    role: UserRole;
    is_active: boolean;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    classroom_name?: string | null;
    department_name?: string | null;
    teachings?: TeachingInfo[];
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};

export type ProfileUser = {
    id: number;
    name: string;
    username: string;
    email: string | null;
    role: UserRole;
    role_label: string;
    photo_url: string | null;
    phone: string | null;
    bio: string | null;
    created_at: string;
};

export type SiswaProfile = {
    classrooms: { id: number; name: string; department: string | null }[];
    exam_results: {
        exam_name: string;
        subject: string;
        score: number | null;
        kkm: number;
        pass_status: string | null;
        submitted_at: string | null;
    }[];
    attendance: {
        total: number;
        hadir: number;
        izin: number;
        sakit: number;
        alfa: number;
        percentage: number;
    };
};

export type GuruProfile = {
    subjects: { id: number; name: string }[];
    classrooms: { id: number; name: string; department: string | null }[];
};
