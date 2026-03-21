import type { User } from '@/types/auth';

// ─── Forum Category ─────────────────────────────────────────────────────────

export interface ForumCategory {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    color: string | null;
    order: number;
    is_active: boolean;
    created_at: string;
    updated_at: string;
    threads_count?: number;
}

// ─── Forum Thread ────────────────────────────────────────────────────────────

export interface ForumThread {
    id: number;
    forum_category_id: number | null;
    user_id: number;
    title: string;
    content: string;
    is_pinned: boolean;
    is_locked: boolean;
    last_reply_at: string | null;
    reply_count: number;
    replies_count?: number;
    view_count: number;
    created_at: string;
    updated_at: string;
    user?: User;
    category?: ForumCategory | null;
    replies?: ForumReply[];
    latest_reply?: ForumReply | null;
}

// ─── Forum Reply ─────────────────────────────────────────────────────────────

export interface ForumReply {
    id: number;
    forum_thread_id: number;
    user_id: number;
    content: string;
    created_at: string;
    updated_at: string;
    user?: User;
}

// ─── Forum Permissions ───────────────────────────────────────────────────────

export interface ForumPermissions {
    reply: boolean;
    pin: boolean;
    lock: boolean;
    delete: boolean;
}
