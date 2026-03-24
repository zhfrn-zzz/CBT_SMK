import { ref, onUnmounted } from 'vue';
import axios from 'axios';
import type { SaveAnswersResponse } from '@/types';

interface AutoSaveOptions {
    examSessionId: number;
    getAnswers: () => Record<string, string>;
    getFlags: () => number[];
    onServerSync?: (response: SaveAnswersResponse) => void;
    onExpired?: () => void;
    intervalMs?: number;
}

export function useAutoSave(options: AutoSaveOptions) {
    const {
        examSessionId,
        getAnswers,
        getFlags,
        onServerSync,
        onExpired,
        intervalMs = 30000,
    } = options;

    const isSaving = ref(false);
    const lastSavedAt = ref<Date | null>(null);
    const saveError = ref<string | null>(null);
    let intervalId: ReturnType<typeof setInterval> | null = null;
    let retryTimeoutId: ReturnType<typeof setTimeout> | null = null;
    let retryCount = 0;
    const MAX_RETRIES = 3;

    async function save() {
        if (isSaving.value) return;

        // Clear any pending retry to prevent overlap with scheduled save
        if (retryTimeoutId) {
            clearTimeout(retryTimeoutId);
            retryTimeoutId = null;
        }

        const answers = getAnswers();
        const flags = getFlags();

        // Skip if no answers
        if (Object.keys(answers).length === 0) return;

        isSaving.value = true;
        saveError.value = null;

        try {
            const response = await axios.post<SaveAnswersResponse>(
                `/siswa/ujian/${examSessionId}/save-answers`,
                { answers, flags }
            );

            lastSavedAt.value = new Date();
            retryCount = 0;

            onServerSync?.(response.data);
        } catch (error: unknown) {
            if (axios.isAxiosError(error) && error.response?.status === 410) {
                // Exam expired
                onExpired?.();
                stop();
                return;
            }

            retryCount++;
            saveError.value = `Gagal menyimpan. Percobaan ${retryCount}/${MAX_RETRIES}`;

            if (retryCount < MAX_RETRIES) {
                // Retry in 10 seconds (cleared if scheduled save fires first)
                retryTimeoutId = setTimeout(() => save(), 10000);
            } else {
                saveError.value = 'Gagal menyimpan jawaban. Periksa koneksi internet Anda.';
            }
        } finally {
            isSaving.value = false;
        }
    }

    function start() {
        if (intervalId) return;

        // Immediate first save
        save();

        intervalId = setInterval(() => {
            save();
        }, intervalMs);
    }

    function stop() {
        if (intervalId) {
            clearInterval(intervalId);
            intervalId = null;
        }
        if (retryTimeoutId) {
            clearTimeout(retryTimeoutId);
            retryTimeoutId = null;
        }
    }

    /**
     * Force save (e.g., before submit).
     */
    async function forceSave() {
        await save();
    }

    onUnmounted(() => {
        stop();
    });

    return {
        isSaving,
        lastSavedAt,
        saveError,
        start,
        stop,
        forceSave,
    };
}
