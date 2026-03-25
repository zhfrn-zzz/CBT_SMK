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
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import QuestionCard from '@/components/Exam/QuestionCard.vue';
import NavigationPanel from '@/components/Exam/NavigationPanel.vue';
import { useExamState } from '@/composables/useExamState';
import { useExamTimer } from '@/composables/useExamTimer';
import { useAutoSave } from '@/composables/useAutoSave';
import { useExamSecurity } from '@/composables/useExamSecurity';
import {
    ChevronLeft,
    ChevronRight,
    Clock,
    Flag,
    CheckCircle,
    AlertTriangle,
    Check,
    Loader2,
    X,
    ListChecks,
} from 'lucide-vue-next';
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

// Fullscreen exit warning
const showFullscreenWarning = ref(false);

// Time's up dialog
const showTimesUp = ref(false);

// Auto-save relative time
const lastSavedSecondsAgo = ref<number | null>(null);
let savedInterval: ReturnType<typeof setInterval> | null = null;

// Start timer and auto-save
onMounted(() => {
    if (props.remaining_seconds <= 0) {
        router.visit('/siswa/ujian', { replace: true });
        return;
    }

    timer.start();
    autoSave.start();

    timer.onExpire(() => {
        showTimesUp.value = true;
        handleAutoSubmit();
    });

    // Anti-cheat listeners
    document.addEventListener('visibilitychange', handleVisibilityChange);
    document.addEventListener('fullscreenchange', handleFullscreenChange);
    document.addEventListener('contextmenu', handleContextMenu);
    document.addEventListener('copy', handleCopy);
    document.addEventListener('keydown', handleKeyDown);

    // Enter fullscreen
    requestFullscreen();

    // Update "saved X seconds ago" display
    savedInterval = setInterval(() => {
        if (autoSave.lastSavedAt.value) {
            lastSavedSecondsAgo.value = Math.floor(
                (Date.now() - autoSave.lastSavedAt.value.getTime()) / 1000,
            );
        }
    }, 1000);
});

onUnmounted(() => {
    document.removeEventListener('visibilitychange', handleVisibilityChange);
    document.removeEventListener('fullscreenchange', handleFullscreenChange);
    document.removeEventListener('contextmenu', handleContextMenu);
    document.removeEventListener('copy', handleCopy);
    document.removeEventListener('keydown', handleKeyDown);
    if (savedInterval) clearInterval(savedInterval);
});

// Timer styling
const timerClass = computed(() => {
    if (timer.isDanger.value) return 'text-2xl font-bold text-red-600 bg-red-50 px-4 py-1 rounded-lg animate-pulse';
    if (timer.isWarning.value) return 'text-2xl font-bold text-amber-600 bg-amber-50 px-4 py-1 rounded-lg animate-pulse';
    return 'text-2xl font-bold text-foreground';
});

// Keyboard navigation
function handleKeyDown(e: KeyboardEvent) {
    // Don't intercept when typing in input/textarea
    const target = e.target as HTMLElement;
    if (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.isContentEditable) return;

    if (e.key === 'ArrowLeft') {
        e.preventDefault();
        prevQuestion();
    } else if (e.key === 'ArrowRight') {
        e.preventDefault();
        nextQuestion();
    }
}

// Anti-cheat handlers
function handleVisibilityChange() {
    if (document.hidden) {
        state.tabSwitchCount++;
        logActivity('tab_switch', `Tab switch #${state.tabSwitchCount}`);
    }
}

function handleFullscreenChange() {
    if (!document.fullscreenElement) {
        showFullscreenWarning.value = true;
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

        if (data.auto_submitted) {
            handleAutoSubmit();
            return;
        }

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

function reEnterFullscreen() {
    showFullscreenWarning.value = false;
    requestFullscreen();
}

// Submit handlers
const submitIdempotencyKey = ref('');

async function handleSubmit() {
    if (isSubmitting.value) return;
    isSubmitting.value = true;

    submitIdempotencyKey.value = crypto.randomUUID();

    await autoSave.forceSave();
    autoSave.stop();
    timer.stop();

    clearLocalStorage();

    router.post(`/siswa/ujian/${props.exam.id}/submit`, {}, {
        headers: { 'X-Idempotency-Key': submitIdempotencyKey.value },
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

const isFlagged = computed(() =>
    currentQuestion.value ? state.flaggedQuestions.has(currentQuestion.value.id) : false,
);
</script>

<template>
    <Head :title="`Ujian: ${exam.name}`" />

    <div class="flex min-h-screen flex-col bg-slate-50">
        <!-- Header Bar -->
        <header class="h-14 bg-white border-b shadow-sm sticky top-0 z-50 px-4 flex items-center">
            <!-- Left: Exam name -->
            <div class="flex items-center gap-2 flex-1 min-w-0">
                <h1 class="text-base font-semibold truncate max-w-xs">{{ exam.name }}</h1>
                <Badge variant="outline" class="hidden sm:inline-flex shrink-0">{{ exam.subject }}</Badge>
            </div>

            <!-- Center: Timer -->
            <div class="flex items-center justify-center flex-1">
                <div class="tabular-nums flex items-center gap-2" :class="timerClass">
                    <Clock class="size-5 shrink-0" />
                    {{ timer.displayTime.value }}
                </div>
            </div>

            <!-- Right: Actions -->
            <div class="flex items-center gap-2 flex-1 justify-end">
                <!-- Flag Button -->
                <Button
                    variant="outline"
                    size="sm"
                    :class="isFlagged ? 'border-amber-400 bg-amber-50 text-amber-700' : ''"
                    @click="handleToggleFlag"
                >
                    <Flag class="size-4" />
                    <span class="hidden sm:inline">{{ isFlagged ? 'Ditandai' : 'Tandai' }}</span>
                </Button>

                <!-- Submit Button -->
                <AlertDialog>
                    <AlertDialogTrigger as-child>
                        <Button size="sm" :disabled="isSubmitting">
                            <CheckCircle class="size-4" />
                            <span class="hidden sm:inline">Selesai</span>
                        </Button>
                    </AlertDialogTrigger>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>Selesaikan Ujian?</AlertDialogTitle>
                            <AlertDialogDescription as="div">
                                <p class="mb-4">
                                    Anda telah menjawab {{ answeredCount }} dari {{ state.totalQuestions }} soal.
                                    <template v-if="unansweredCount > 0">
                                        {{ unansweredCount }} soal belum dijawab.
                                    </template>
                                    <template v-if="flaggedCount > 0">
                                        {{ flaggedCount }} soal ditandai untuk ditinjau.
                                    </template>
                                </p>
                                <!-- Stats Badges -->
                                <div class="flex flex-wrap gap-2 mb-4">
                                    <Badge variant="default" class="gap-1">
                                        <Check class="size-3" />
                                        Dijawab {{ answeredCount }}
                                    </Badge>
                                    <Badge v-if="unansweredCount > 0" variant="destructive" class="gap-1">
                                        <X class="size-3" />
                                        Belum {{ unansweredCount }}
                                    </Badge>
                                    <Badge v-if="flaggedCount > 0" class="gap-1 bg-amber-400 text-white hover:bg-amber-500">
                                        <Flag class="size-3" />
                                        Ditandai {{ flaggedCount }}
                                    </Badge>
                                </div>
                                <p class="font-medium">
                                    Apakah Anda yakin ingin mengumpulkan ujian?
                                </p>
                            </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel>Kembali ke Ujian</AlertDialogCancel>
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
                <div class="max-w-3xl mx-auto">
                    <QuestionCard
                        v-if="currentQuestion"
                        :question="currentQuestion"
                        :answer="state.answers[String(currentQuestion.id)]"
                        :is-flagged="state.flaggedQuestions.has(currentQuestion.id)"
                        :question-number="state.currentQuestionIndex + 1"
                        :total-questions="state.totalQuestions"
                        @update:answer="handleAnswer"
                        @toggle-flag="handleToggleFlag"
                    />

                    <!-- Navigation Buttons -->
                    <div class="flex items-center justify-between mt-8">
                        <Button
                            variant="outline"
                            class="h-11"
                            :disabled="state.currentQuestionIndex === 0"
                            @click="prevQuestion"
                        >
                            <ChevronLeft class="size-4" />
                            Sebelumnya
                        </Button>

                        <Button
                            class="h-11"
                            :disabled="state.currentQuestionIndex === state.questions.length - 1"
                            @click="nextQuestion"
                        >
                            Berikutnya
                            <ChevronRight class="size-4" />
                        </Button>
                    </div>
                </div>
            </main>

            <!-- Sidebar: Navigation Panel (Desktop) -->
            <aside class="hidden lg:block w-72 bg-white border-l overflow-y-auto p-4 sticky top-14">
                <NavigationPanel
                    :questions="state.questions"
                    :current-index="state.currentQuestionIndex"
                    :get-status="getQuestionStatus"
                    :answered-count="answeredCount"
                    :flagged-count="flaggedCount"
                    :unanswered-count="unansweredCount"
                    :total-questions="state.totalQuestions"
                    @go-to="goToQuestion"
                />
            </aside>
        </div>

        <!-- Footer Bar -->
        <footer class="h-10 bg-white border-t px-4 flex items-center justify-between text-sm">
            <!-- Left: Auto-save indicator -->
            <div class="flex items-center gap-1.5">
                <template v-if="autoSave.isSaving.value">
                    <Loader2 class="size-3.5 animate-spin text-muted-foreground" />
                    <span class="text-muted-foreground">Menyimpan...</span>
                </template>
                <template v-else-if="autoSave.saveError.value">
                    <X class="size-3.5 text-red-500" />
                    <span class="text-red-500">Gagal menyimpan</span>
                </template>
                <template v-else-if="lastSavedSecondsAgo !== null">
                    <Check class="size-3.5 text-emerald-600" />
                    <span class="text-emerald-600">Tersimpan {{ lastSavedSecondsAgo }} detik lalu</span>
                </template>
                <template v-else>
                    <span class="text-muted-foreground">Belum disimpan</span>
                </template>
            </div>

            <!-- Right: Question counter + Mobile nav trigger -->
            <div class="flex items-center gap-3">
                <span class="text-muted-foreground">
                    Soal {{ state.currentQuestionIndex + 1 }} dari {{ state.totalQuestions }}
                </span>

                <!-- Mobile Nav Trigger -->
                <Sheet>
                    <SheetTrigger as-child>
                        <Button variant="outline" size="sm" class="lg:hidden gap-1">
                            <ListChecks class="size-4" />
                            {{ answeredCount }}/{{ state.totalQuestions }}
                        </Button>
                    </SheetTrigger>
                    <SheetContent side="bottom" class="max-h-[70vh] overflow-y-auto">
                        <SheetHeader>
                            <SheetTitle>Navigasi Soal</SheetTitle>
                        </SheetHeader>
                        <div class="mt-4">
                            <NavigationPanel
                                :questions="state.questions"
                                :current-index="state.currentQuestionIndex"
                                :get-status="getQuestionStatus"
                                :answered-count="answeredCount"
                                :flagged-count="flaggedCount"
                                :unanswered-count="unansweredCount"
                                :total-questions="state.totalQuestions"
                                @go-to="goToQuestion"
                            />
                        </div>
                    </SheetContent>
                </Sheet>
            </div>
        </footer>

        <!-- Tab Switch Warning Dialog -->
        <AlertDialog :open="showTabWarning" @update:open="showTabWarning = $event">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <div class="flex justify-center mb-4">
                        <AlertTriangle
                            class="size-12"
                            :class="tabWarningLevel === 'final' ? 'text-red-500' : 'text-amber-500'"
                        />
                    </div>
                    <AlertDialogTitle class="text-center">
                        {{ tabWarningLevel === 'final' ? 'Peringatan Terakhir!' : 'Peringatan!' }}
                    </AlertDialogTitle>
                    <AlertDialogDescription as="div" class="text-center">
                        <p v-if="tabWarningLevel === 'final'" class="font-semibold text-red-600 mb-2">
                            Ini adalah peringatan terakhir! Jika Anda pindah tab sekali lagi,
                            ujian akan otomatis dikumpulkan.
                        </p>
                        <p v-else class="mb-2">
                            Anda berpindah tab atau jendela. Aktivitas ini tercatat oleh sistem.
                        </p>
                        <p class="font-medium">
                            Pelanggaran ke-{{ tabWarningCount }} dari maksimum {{ tabWarningMax }}.
                        </p>
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter class="sm:justify-center">
                    <AlertDialogAction @click="showTabWarning = false">
                        Saya Mengerti
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>

        <!-- Fullscreen Exit Warning -->
        <AlertDialog :open="showFullscreenWarning" @update:open="showFullscreenWarning = $event">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Kembali ke Mode Layar Penuh</AlertDialogTitle>
                    <AlertDialogDescription>
                        Ujian harus dikerjakan dalam mode layar penuh.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogAction @click="reEnterFullscreen">
                        Aktifkan Layar Penuh
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>

        <!-- Time's Up Dialog (non-dismissible) -->
        <Dialog :open="showTimesUp">
            <DialogContent
                :show-close-button="false"
                @escape-key-down.prevent
                @pointer-down-outside.prevent
                @interact-outside.prevent
            >
                <DialogHeader>
                    <div class="flex justify-center mb-4">
                        <Clock class="size-12 text-red-500 animate-pulse" />
                    </div>
                    <DialogTitle class="text-center">Waktu Habis!</DialogTitle>
                    <DialogDescription class="text-center">
                        Jawaban Anda telah dikumpulkan secara otomatis.
                    </DialogDescription>
                </DialogHeader>
                <div class="flex justify-center mt-4">
                    <Button @click="router.visit('/siswa/ujian')">
                        Lihat Hasil
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
