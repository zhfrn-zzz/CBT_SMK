export type UserRole = 'admin' | 'guru' | 'siswa';

export type User = {
    id: number;
    name: string;
    username: string;
    email: string | null;
    avatar?: string;
    role: UserRole;
    is_active: boolean;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
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
