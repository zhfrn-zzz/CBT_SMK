export interface StorageCategoryBreakdown {
    category: string;
    label: string;
    size: number;
    count: number;
}

export interface StorageTopFile {
    name: string;
    path: string;
    size: number;
    category: string;
    created_at: string | null;
}

export interface OrphanedFile {
    path: string;
    size: number;
    last_modified: string;
}

export interface GuruFile {
    id: number;
    type: 'material' | 'assignment' | 'question';
    type_label: string;
    name: string;
    path: string;
    size: number;
    is_used: boolean;
    usage_info: string;
    created_at: string | null;
}

export interface FileManagerFilters {
    type: string | null;
    sort: string;
    direction: string;
}
