import { router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { onUnmounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import type { NotificationItem } from '@/types/notification';

/**
 * Composable to poll for export-ready notifications.
 *
 * After submitting an export job, call `startPolling()`.
 * It will poll the server every `intervalMs` for new unread
 * export_ready notifications. When one is found, a Sonner
 * toast is shown with a download action.
 */
export function useExportPolling(intervalMs = 5000) {
    const polling = ref(false);
    let timer: ReturnType<typeof setInterval> | null = null;
    const knownIds = new Set<string>();

    /** Seed known IDs from current shared props so we only toast NEW ones. */
    function seedKnownIds() {
        const page = usePage();
        const auth = page.props.auth as Record<string, unknown>;
        const recent = (auth.recent_notifications ?? []) as NotificationItem[];
        for (const n of recent) {
            knownIds.add(n.id);
        }
    }

    async function checkForExport() {
        try {
            const { data } = await axios.get('/notifications', {
                headers: { Accept: 'application/json' },
            });
            const all: NotificationItem[] = data.notifications?.data ?? [];
            const newExports = all.filter(
                (n) => n.data.type === 'export_ready' && !n.read_at && !knownIds.has(n.id),
            );

            for (const notif of newExports) {
                knownIds.add(notif.id);
                toast.success(notif.data.title, {
                    description: notif.data.message,
                    duration: 15000,
                    action: {
                        label: 'Download',
                        onClick: () => {
                            axios.post(`/notifications/${notif.id}/read`).catch(() => {});
                            window.location.href = notif.data.action_url;
                        },
                    },
                });

                // Refresh Inertia page data so the bell badge updates
                router.reload({ only: ['auth'] });
                stopPolling();
            }
        } catch {
            // Silently ignore poll failures
        }
    }

    function startPolling() {
        if (polling.value) return;
        seedKnownIds();
        polling.value = true;
        timer = setInterval(checkForExport, intervalMs);
    }

    function stopPolling() {
        polling.value = false;
        if (timer) {
            clearInterval(timer);
            timer = null;
        }
    }

    onUnmounted(stopPolling);

    return { polling, startPolling, stopPolling };
}
