import { useEcho } from '@laravel/echo-vue';
import type { Ref } from 'vue';
import type {
    AnswerProgressEvent,
    ProctorStudent,
    StudentStartedEvent,
    StudentSubmittedEvent,
    TabSwitchEvent,
} from '@/types';

export function useProctorChannel(
    examSessionId: number,
    students: Ref<ProctorStudent[]>,
) {
    const channelName = `exam.${examSessionId}`;

    const { leave: leaveStarted } = useEcho<StudentStartedEvent>(
        channelName,
        '.StudentStartedExam',
        (event) => {
            const student = students.value.find((s) => s.id === event.user_id);
            if (student) {
                student.status = 'in_progress';
                student.status_label = 'Sedang Mengerjakan';
                student.attempt_id = event.attempt_id;
                student.started_at = event.started_at;
            }
        },
    );

    const { leave: leaveSubmitted } = useEcho<StudentSubmittedEvent>(
        channelName,
        '.StudentSubmittedExam',
        (event) => {
            const student = students.value.find((s) => s.id === event.user_id);
            if (student) {
                student.status = 'submitted';
                student.status_label = 'Sudah Dikumpulkan';
                student.submitted_at = event.submitted_at;
                student.is_force_submitted = event.is_force_submitted;
                student.score = event.score;
                student.remaining_seconds = 0;
            }
        },
    );

    const { leave: leaveProgress } = useEcho<AnswerProgressEvent>(
        channelName,
        '.AnswerProgressUpdated',
        (event) => {
            const student = students.value.find((s) => s.id === event.user_id);
            if (student) {
                student.answered_count = event.answered_count;
                student.total_questions = event.total_questions;
            }
        },
    );

    const { leave: leaveTabSwitch } = useEcho<TabSwitchEvent>(
        channelName,
        '.TabSwitchDetected',
        (event) => {
            const student = students.value.find((s) => s.id === event.user_id);
            if (student) {
                student.violation_count = event.total_violations;
            }
        },
    );

    function leaveAll() {
        leaveStarted();
        leaveSubmitted();
        leaveProgress();
        leaveTabSwitch();
    }

    return { leaveAll };
}
