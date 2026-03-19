export type NotificationType =
    | 'ujian_dijadwalkan'
    | 'deadline_tugas'
    | 'nilai_dipublikasi'
    | 'materi_baru'
    | 'pengumuman_baru';

export type NotificationData = {
    type: NotificationType;
    title: string;
    message: string;
    action_url: string;
};

export type NotificationItem = {
    id: string;
    type: string;
    data: NotificationData;
    read_at: string | null;
    created_at: string;
};
