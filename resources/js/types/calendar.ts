// ─── Calendar Events ─────────────────────────────────────────────────────────

export type CalendarEventType = 'exam' | 'assignment' | 'attendance';

export interface CalendarEvent {
    id: number;
    title: string;
    date: string;
    type: CalendarEventType;
    subject?: string;
    classroom?: string;
}

export interface CalendarDay {
    date: string;
    day: number;
    isCurrentMonth: boolean;
    isToday: boolean;
    events: CalendarEvent[];
}
