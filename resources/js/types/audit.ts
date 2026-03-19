export type AuditLogEntry = {
    id: number;
    user: { id: number; name: string; role: string } | null;
    action: string;
    auditable_type: string | null;
    auditable_type_label: string | null;
    auditable_id: number | null;
    old_values: Record<string, unknown> | null;
    new_values: Record<string, unknown> | null;
    ip_address: string | null;
    user_agent: string | null;
    description: string | null;
    created_at: string;
};

export type AuditFilters = {
    user_id: number | null;
    action: string | null;
    auditable_type: string | null;
    date_from: string | null;
    date_to: string | null;
};
