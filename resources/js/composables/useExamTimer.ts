import { ref, computed, onUnmounted } from 'vue';

export function useExamTimer(initialRemainingSeconds: number) {
    const remainingSeconds = ref(initialRemainingSeconds);
    const isExpired = ref(false);
    let intervalId: ReturnType<typeof setInterval> | null = null;
    let onExpireCallback: (() => void) | null = null;

    const hours = computed(() => Math.floor(remainingSeconds.value / 3600));
    const minutes = computed(() => Math.floor((remainingSeconds.value % 3600) / 60));
    const seconds = computed(() => remainingSeconds.value % 60);

    const displayTime = computed(() => {
        const h = hours.value;
        const m = String(minutes.value).padStart(2, '0');
        const s = String(seconds.value).padStart(2, '0');
        return h > 0 ? `${h}:${m}:${s}` : `${m}:${s}`;
    });

    const isWarning = computed(() => remainingSeconds.value <= 300 && remainingSeconds.value > 60);
    const isDanger = computed(() => remainingSeconds.value <= 60);

    function start() {
        if (intervalId) return;

        intervalId = setInterval(() => {
            if (remainingSeconds.value <= 0) {
                stop();
                isExpired.value = true;

                // Stagger auto-submit: random 0-30s delay to prevent thundering herd
                const jitter = Math.floor(Math.random() * 30000);
                setTimeout(() => {
                    onExpireCallback?.();
                }, jitter);
                return;
            }
            remainingSeconds.value--;
        }, 1000);
    }

    function stop() {
        if (intervalId) {
            clearInterval(intervalId);
            intervalId = null;
        }
    }

    /**
     * Sync with server time. If difference > 3 seconds, use server value.
     */
    function syncWithServer(serverRemainingSeconds: number) {
        const diff = Math.abs(remainingSeconds.value - serverRemainingSeconds);
        if (diff > 3) {
            remainingSeconds.value = serverRemainingSeconds;
        }
    }

    function onExpire(callback: () => void) {
        onExpireCallback = callback;
    }

    onUnmounted(() => {
        stop();
    });

    return {
        remainingSeconds,
        isExpired,
        hours,
        minutes,
        seconds,
        displayTime,
        isWarning,
        isDanger,
        start,
        stop,
        syncWithServer,
        onExpire,
    };
}
