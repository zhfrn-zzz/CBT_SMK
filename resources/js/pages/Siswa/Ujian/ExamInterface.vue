<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import QuestionCard from '@/components/Exam/QuestionCard.vue';
import NavigationPanel from '@/components/Exam/NavigationPanel.vue';
import { useExamState } from '@/composables/useExamState';
import { useExamTimer } from '@/composables/useExamTimer';
import { useAutoSave } from '@/composables/useAutoSave';
import { useExamSecurity } from '@/composables/useExamSecurity';
import { ChevronLeft, ChevronRight, Clock, Save, Send, AlertTriangle } from 'lucide-vue-next';
import type { ExamPayload } from '@/types';
import axios from 'axios';

const props = defineProps<ExamPayload>();

// State
const {
    state,
    currentQuestion,
    answeredCount,
    flaggedCount,
    unansweredCount,
    setAnswer,
    toggleFlag,
    goToQuestion,
    nextQuestion,
    prevQuestion,
    getQuestionStatus,
    clearLocalStorage,
    getAnswersPayload,
    getFlagsPayload,
} = useExamState(props);

// Timer
const timer = useExamTimer(props.remaining_seconds);

// Auto-save
const autoSave = useAutoSave({
    examSessionId: props.exam.id,
    getAnswers: getAnswersPayload,
    getFlags: getFlagsPayload,
    onServerSync: (response) => {
        timer.syncWithServer(response.remaining_seconds);
    },
    onExpired: () => {
        handleAutoSubmit();
    },
});

// Security hardening
useExamSecurity({
    attemptId: props.attempt_id,
    enabled: props.security_hardening ?? true,
});

const isSubmitting = ref(false);

// Tab switch warning state
const showTabWarning = ref(false);
const tabWarningLevel = ref<'standard' | 'final'>('standard');
const tabWarningCount = ref(0);
const tabWarningMax = ref<number | null>(props.exam.max_tab_switches);

// Start timer and auto-save
onMounted(() => {
    timer.start();
    autoSave.start();

    timer.onExpire(() => {
        handleAutoSubmit();
    });

    // Anti-cheat listeners
    document.addEventListener('visibilitychange', handleVisibilityChange);
    document.addEventListener('fullscreenchange', handleFullscreenChange);
    document.addEventListener('contextmenu', handleContextMenu);
    document.addEventListener('copy', handleCopy);

    // Enter fullscreen
    requestFullscreen();
});

onUnmounted(() => {
    document.removeEventListener('visibilitychange', handleVisibilityChange);
    document.removeEventListener('fullscreenchange', handleFullscreenChange);
    document.removeEventListener('contextmenu', handleContextMenu);
    document.removeEventListener('copy', handleCopy);
});

// Timer color
const timerClass = computed(() => {
    if (timer.isDanger.value) return 'bg-red-600 text-white animate-pulse';
    if (timer.isWarning.value) return 'bg-yellow-500 text-yellow-900';
    return 'bg-muted';
});

// Anti-cheat handlers
function handleVisibilityChange() {
    if (document.hidden) {
        state.tabSwitchCount++;
        logActivity('tab_switch', `Tab switch #${state.tabSwitchCount}`);
    }
}

function handleFullscreenChange() {
    if (!document.fullscreenElement) {
        logActivity('fullscreen_exit', 'Keluar dari fullscreen');
    }
}

function handleContextMenu(e: Event) {
    e.preventDefault();
    logActivity('right_click', 'Right click detected');
}

function handleCopy(e: Event) {
    e.preventDefault();
    logActivity('copy_attempt', 'Copy attempt detected');
}

function logActivity(eventType: string, description: string) {
    axios.post('/api/exam/log-activity', {
        attempt_id: state.attemptId,
        event_type: eventType,
        description,
    }).then((response) => {
        const data = response.data;

        // Handle auto-submitted by server (tab switch limit reached)
        if (data.auto_submitted) {
            handleAutoSubmit();
            return;
        }

        // Handle tab switch warnings
        if (eventType === 'tab_switch' && data.max_tab_switches !== undefined) {
            tabWarningCount.value = data.tab_switch_count;
            tabWarningMax.value = data.max_tab_switches;
            state.tabSwitchCount = data.tab_switch_count;

            if (data.warning_level === 'final') {
                tabWarningLevel.value = 'final';
                showTabWarning.value = true;
            } else if (data.warning_level === 'standard') {
                tabWarningLevel.value = 'standard';
                showTabWarning.value = true;
            }
        }
    }).catch(() => {
        // fire and forget
    });
}

function requestFullscreen() {
    try {
        document.documentElement.requestFullscreen?.();
    } catch {
        // Not all browsers support this
    }
}

// Submit handlers
async function handleSubmit() {
    isSubmitting.value = true;

    // Force save before submit
    await autoSave.forceSave();
    autoSave.stop();
    timer.stop();

    clearLocalStorage();

    router.post(`/siswa/ujian/${props.exam.id}/submit`, {}, {
        onFinish: () => { isSubmitting.value = false; },
    });
}

function handleAutoSubmit() {
    if (isSubmitting.value) return;
    isSubmitting.value = true;

    autoSave.forceSave().then(() => {
        autoSave.stop();
        timer.stop();
        clearLocalStorage();

        router.post(`/siswa/ujian/${props.exam.id}/submit`, {}, {
            onFinish: () => { isSubmitting.value = false; },
        });
    });
}

function handleAnswer(answer: string) {
    if (currentQuestion.value) {
        setAnswer(currentQuestion.value.id, answer);
    }
}

function handleToggleFlag() {
    if (currentQuestion.value) {
        toggleFlag(currentQuestion.value.id);
    }
}
</script>

<template>
    <Head :title="`Ujian: ${exam.name}`" />

    <div class="flex h-screen flex-col bg-background">
        <!-- Header -->
        <header class="flex items-center justify-between border-b px-4 py-2">
            <div class="flex items-center gap-3">
                <h1 class="text-sm font-semibold sm:text-base">{{ exam.name }}</h1>
                <Badge variant="outline" class="hidden sm:inline-flex">{{ exam.subject }}</Badge>
            </div>

            <div class="flex items-center gap-3">
                <!-- Save Status -->
                <div class="hidden items-center gap-1 text-xs text-muted-foreground sm:flex">
                    <Save class="size-3" />
                    <span v-if="autoSave.isSaving.value">Menyimpan...</span>
                    <span v-else-if="autoSave.lastSavedAt.value">
                        Tersimpan {{ autoSave.lastSavedAt.value.toLocaleTimeString('id-ID') }}
                    </span>
                    <span v-else>Belum disimpan</span>
                </div>

                <!-- Timer -->
                <div
                    class="flex items-center gap-1.5 rounded-md px-3 py-1.5 font-mono text-sm font-bold"
                    :class="timerClass"
                >
                    <Clock class="size-4" />
                    {{ timer.displayTime.value }}
                </div>

                <!-- Submit Button -->
                <AlertDialog>
                    <AlertDialogTrigger as-child>
                        <Button size="sm" :disabled="isSubmitting">
                            <Send class="size-4" />
                            <span class="hidden sm:inline">Kumpulkan</span>
                        </Button>
                    </AlertDialogTrigger>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>Kumpulkan Jawaban</AlertDialogTitle>
                            <AlertDialogDescription>
                                <div class="space-y-2 text-sm">
                                    <p>Ringkasan pengerjaan:</p>
                                    <ul class="list-inside list-disc space-y-1">
                                        <li>
                                            Dijawab: <strong>{{ answeredCount }}</strong> dari {{ state.totalQuestions }} soal
                                        </li>
                                        <li v-if="unansweredCount > 0" class="text-destructive">
                                            Belum dijawab: <strong>{{ unansweredCount }}</strong> soal
                                        </li>
                                        <li v-if="flaggedCount > 0" class="text-yellow-600">
                                            Ditandai (ragu): <strong>{{ flaggedCount }}</strong> soal
                                        </li>
                                    </ul>
                                    <p class="pt-2 font-medium">
                                        Apakah Anda yakin ingin mengumpulkan jawaban? Tindakan ini tidak dapat dibatalkan.
                                    </p>
                                </div>
                            </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel>Kembali</AlertDialogCancel>
                            <AlertDialogAction :disabled="isSubmitting" @click="handleSubmit">
                                {{ isSubmitting ? 'Mengirim...' : 'Ya, Kumpulkan' }}
                            </AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>
            </div>
        </header>

        <!-- Main Content -->
        <div class="flex flex-1 overflow-hidden">
            <!-- Question Area -->
            <main class="flex-1 overflow-y-auto p-4 sm:p-6">
                <div class="mx-auto max-w-3xl">
                    <QuestionCard
                        v-if="currentQuestion"
                        :question="currentQuestion"
                        :answer="state.answers[String(currentQuestion.id)]"
                        :is-flagged="state.flaggedQuestions.has(currentQuestion.id)"
                        @update:answer="handleAnswer"
                        @toggle-flag="handleToggleFlag"
                    />

                    <!-- Navigation buttons -->
                    <div class="mt-6 flex items-center justify-between">
                        <Button
                            variant="outline"
                            :disabled="state.currentQuestionIndex === 0"
                            @click="prevQuestion"
                        >
                            <ChevronLeft class="size-4" />
                            Sebelumnya
                        </Button>

                        <span class="text-sm text-muted-foreground">
                            {{ state.currentQuestionIndex + 1 }} / {{ state.totalQuestions }}
                        </span>

                        <Button
                            variant="outline"
                            :disabled="state.currentQuestionIndex === state.questions.length - 1"
                            @click="nextQuestion"
                        >
                            Selanjutnya
                            <ChevronRight class="size-4" />
                        </Button>
                    </div>
                </div>
            </main>

            <!-- Sidebar: Navigation Panel -->
            <aside class="hidden w-64 shrink-0 border-l p-4 lg:block">
                <NavigationPanel
                    :questions="state.questions"
                    :current-index="state.currentQuestionIndex"
                    :get-status="getQuestionStatus"
                    @go-to="goToQuestion"
                />

                <!-- Stats -->
                <div class="mt-4 space-y-1 rounded-md bg-muted p-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Dijawab</span>
                        <span class="font-medium">{{ answeredCount }}/{{ state.totalQuestions }}</span>
                    </div>
                    <div v-if="flaggedCount > 0" class="flex justify-between">
                        <span class="text-yellow-600">Ditandai</span>
                        <span class="font-medium text-yellow-600">{{ flaggedCount }}</span>
                    </div>
                    <div v-if="state.tabSwitchCount > 0" class="flex justify-between">
                        <span class="text-destructive">Pindah Tab</span>
                        <span class="font-medium text-destructive">
                            {{ state.tabSwitchCount }}
                            <template v-if="state.maxTabSwitches !== null">
                                /{{ state.maxTabSwitches }}
                            </template>
                        </span>
                    </div>
                </div>
            </aside>
        </div>

        <!-- Mobile Navigation (bottom) -->
        <div class="border-t p-2 lg:hidden">
            <div class="flex gap-1 overflow-x-auto pb-1">
                <button
                    v-for="(q, i) in state.questions"
                    :key="q.id"
                    type="button"
                    class="flex size-8 shrink-0 items-center justify-center rounded text-xs font-medium"
                    :class="{
                        'bg-primary text-primary-foreground': i === state.currentQuestionIndex,
                        'bg-green-500 text-white': i !== state.currentQuestionIndex && getQuestionStatus(q.id) === 'answered',
                        'bg-yellow-400 text-yellow-900': i !== state.currentQuestionIndex && getQuestionStatus(q.id) === 'flagged',
                        'bg-muted': i !== state.currentQuestionIndex && getQuestionStatus(q.id) === 'unanswered',
                    }"
                    @click="goToQuestion(i)"
                >
                    {{ q.order }}
                </button>
            </div>
        </div>

        <!-- Save Error Toast -->
        <div
            v-if="autoSave.saveError.value"
            class="fixed bottom-4 right-4 z-50 rounded-lg border border-destructive bg-destructive/10 p-3 text-sm text-destructive shadow-lg"
        >
            {{ autoSave.saveError.value }}
        </div>

        <!-- Tab Switch Warning Dialog -->
        <AlertDialog :open="showTabWarning" @update:open="showTabWarning = $event">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle class="flex items-center gap-2">
                        <AlertTriangle
                            class="size-5"
                            :class="tabWarningLevel === 'final' ? 'text-red-600' : 'text-yellow-600'"
                        />
                        <span :class="tabWarningLevel === 'final' ? 'text-red-600' : 'text-yellow-600'">
                            {{ tabWarningLevel === 'final' ? 'Peringatan Terakhir!' : 'Peringatan Pindah Tab' }}
                        </span>
                    </AlertDialogTitle>
                    <AlertDialogDescription>
                        <div class="space-y-3 text-sm">
                            <p v-if="tabWarningLevel === 'final'" class="font-semibold text-red-600">
                                Ini adalah peringatan terakhir! Jika Anda pindah tab sekali lagi,
                                ujian akan otomatis dikumpulkan.
                            </p>
                            <p v-else>
                                Anda terdeteksi pindah tab/aplikasi. Hal ini tercatat sebagai pelanggaran.
                            </p>
                            <p class="font-medium">
                                Pelanggaran: {{ tabWarningCount }} / {{ tabWarningMax }}
                            </p>
                            <p class="text-muted-foreground">
                                Tetap pada halaman ujian untuk menghindari pengumpulan otomatis.
                            </p>
                        </div>
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogAction @click="showTabWarning = false">
                        Saya Mengerti
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </div>
</template>
