import { reactive, computed, watch } from 'vue';
import type { ExamPayload, ExamQuestion } from '@/types';

export interface ExamState {
    attemptId: number;
    examId: number;
    examName: string;
    subject: string;
    durationMinutes: number;
    totalQuestions: number;
    maxTabSwitches: number | null;
    questions: ExamQuestion[];
    answers: Record<string, string>;
    flaggedQuestions: Set<number>;
    currentQuestionIndex: number;
    tabSwitchCount: number;
}

export function useExamState(payload: ExamPayload) {
    const STORAGE_KEY = `exam_${payload.exam.id}_answers`;
    const FLAGS_KEY = `exam_${payload.exam.id}_flags`;

    // Try to load from localStorage first, then merge with server data
    let storedAnswers: Record<string, string> = {};
    let storedFlags: number[] = [];

    try {
        const stored = localStorage.getItem(STORAGE_KEY);
        if (stored) storedAnswers = JSON.parse(stored);

        const flagsStored = localStorage.getItem(FLAGS_KEY);
        if (flagsStored) storedFlags = JSON.parse(flagsStored);
    } catch {
        // ignore parse errors
    }

    // Merge: server answers take precedence, then localStorage
    const mergedAnswers: Record<string, string> = {
        ...storedAnswers,
        ...(payload.saved_answers as Record<string, string>),
    };

    const mergedFlags = new Set<number>([
        ...storedFlags,
        ...payload.flagged_questions,
    ]);

    const state = reactive<ExamState>({
        attemptId: payload.attempt_id,
        examId: payload.exam.id,
        examName: payload.exam.name,
        subject: payload.exam.subject,
        durationMinutes: payload.exam.duration_minutes,
        totalQuestions: payload.exam.total_questions,
        maxTabSwitches: payload.exam.max_tab_switches,
        questions: payload.questions,
        answers: mergedAnswers,
        flaggedQuestions: mergedFlags,
        currentQuestionIndex: 0,
        tabSwitchCount: 0,
    });

    // Persist to localStorage on every answer change
    watch(
        () => ({ ...state.answers }),
        (answers) => {
            try {
                localStorage.setItem(STORAGE_KEY, JSON.stringify(answers));
            } catch {
                // localStorage full or unavailable
            }
        },
        { deep: true }
    );

    watch(
        () => [...state.flaggedQuestions],
        (flags) => {
            try {
                localStorage.setItem(FLAGS_KEY, JSON.stringify(flags));
            } catch {
                // ignore
            }
        },
        { deep: true }
    );

    const currentQuestion = computed<ExamQuestion | undefined>(
        () => state.questions[state.currentQuestionIndex]
    );

    const answeredCount = computed(
        () => Object.values(state.answers).filter(a => a !== null && a !== '').length
    );

    const flaggedCount = computed(() => state.flaggedQuestions.size);

    const unansweredCount = computed(
        () => state.totalQuestions - answeredCount.value
    );

    function setAnswer(questionId: number, answer: string) {
        state.answers[String(questionId)] = answer;
    }

    function toggleFlag(questionId: number) {
        if (state.flaggedQuestions.has(questionId)) {
            state.flaggedQuestions.delete(questionId);
        } else {
            state.flaggedQuestions.add(questionId);
        }
    }

    function goToQuestion(index: number) {
        if (index >= 0 && index < state.questions.length) {
            state.currentQuestionIndex = index;
        }
    }

    function nextQuestion() {
        goToQuestion(state.currentQuestionIndex + 1);
    }

    function prevQuestion() {
        goToQuestion(state.currentQuestionIndex - 1);
    }

    function getQuestionStatus(questionId: number): 'answered' | 'flagged' | 'unanswered' | 'current' {
        const idx = state.questions.findIndex(q => q.id === questionId);
        if (idx === state.currentQuestionIndex) return 'current';
        if (state.flaggedQuestions.has(questionId)) return 'flagged';
        const answer = state.answers[String(questionId)];
        if (answer !== undefined && answer !== null && answer !== '') return 'answered';
        return 'unanswered';
    }

    function clearLocalStorage() {
        try {
            localStorage.removeItem(STORAGE_KEY);
            localStorage.removeItem(FLAGS_KEY);
        } catch {
            // ignore
        }
    }

    function getAnswersPayload(): Record<string, string> {
        return { ...state.answers };
    }

    function getFlagsPayload(): number[] {
        return [...state.flaggedQuestions];
    }

    return {
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
    };
}
