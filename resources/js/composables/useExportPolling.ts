import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { onUnmounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import type { NotificationItem } from '@/types/notification';

/**
 * Composable to poll for export-ready notifications after a user triggers
 * an export job. Uses the lightweight `/notifications/unread-count` endpoint
 * to detect count increases, then fetches the latest notification for toast.
 *
 * Features:
 * - Polls every `intervalMs` (default 5s)
 * - Stops on first toast shown or after 5 minutes (timeout)
 * - Shows Sonner toast with Download action button
 */
export function useExportPolling(intervalMs = 5000) {
    const polling = ref(false);
    let timer: ReturnType<typeof setInterval> | null = null;
    let timeoutTimer: ReturnType<typeof setTimeout> | null = null;
    let previousCount = -1;

    async function checkForExport() {
        try {
            // 1. Lightweight count check
            const { data: countData } = await axios.get('/notifications/unread-count', {
                headers: { Accept: 'application/json' },
            });
            const currentCount: number = countData.unread_count ?? 0;

            // First poll: seed the baseline count
            if (previousCount === -1) {
                previousCount = currentCount;
                return;
            }

            // 2. Count increased — fetch latest notification
            if (currentCount > previousCount) {
                previousCount = currentCount;

                const { data } = await axios.get('/notifications', {
                    headers: { Accept: 'application/json' },
                });
                const all: NotificationItem[] = data.notifications?.data ?? [];
                const exportNotif = all.find(
                    (n) => n.data.type === 'export_ready' && !n.read_at,
                );

                if (exportNotif) {
                    toast.success(exportNotif.data.title, {
                        description: exportNotif.data.message,
                        duration: 15000,
                        action: {
                            label: 'Download',
                            onClick: () => {
                                axios.post(`/notifications/${exportNotif.id}/read`).catch(() => {});
                                window.location.href = exportNotif.data.action_url;
                            },
                        },
                    });

                    // Refresh shared props so bell badge updates
                    router.reload({ only: ['auth'] });
                    stopPolling();
                }
            }
        } catch {
            // Silently ignore poll failures
        }
    }

    function startPolling() {
        if (polling.value) return;
        previousCount = -1; // reset baseline
        polling.value = true;

        // Poll immediately, then every intervalMs
        checkForExport();
        timer = setInterval(checkForExport, intervalMs);

        // Auto-stop after 5 minutes
        timeoutTimer = setTimeout(() => {
            stopPolling();
        }, 5 * 60 * 1000);
    }

    function stopPolling() {
        polling.value = false;
        if (timer) {
            clearInterval(timer);
            timer = null;
        }
        if (timeoutTimer) {
            clearTimeout(timeoutTimer);
            timeoutTimer = null;
        }
    }

    onUnmounted(stopPolling);

    return { polling, startPolling, stopPolling };
}
