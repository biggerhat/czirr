export type CalendarView = 'month' | 'week' | 'day' | 'agenda';

export type EventType = {
    id: number;
    name: string;
};

export type EventColor = 'rose' | 'orange' | 'amber' | 'emerald' | 'cyan' | 'blue' | 'violet' | 'pink';

export type EventAttendee = {
    id: number;
    name: string;
    email: string;
    pivot?: {
        status: 'pending' | 'accepted' | 'declined';
    };
};

export type FamilyMember = {
    id: number;
    name: string;
    nickname: string | null;
    role: 'parent' | 'child';
    color: EventColor;
    linked_user_id: number | null;
    linked_user?: {
        id: number;
        name: string;
        email: string;
    } | null;
    spatie_role?: string | null;
};

export type CalendarEvent = {
    id: number | string;
    user_id: number;
    title: string;
    description: string | null;
    starts_at: string;
    ends_at: string;
    is_all_day: boolean;
    rrule?: string | null;
    source?: 'bill' | 'income' | null;
    event_type_id?: number | null;
    event_type?: EventType | null;
    is_occurrence?: boolean;
    is_exception?: boolean;
    master_event_id?: number;
    occurrence_start?: string;
    created_at: string;
    updated_at: string;
    owner: {
        id: number;
        name: string;
        email: string;
    };
    attendees: EventAttendee[];
    family_members: FamilyMember[];
};

export type EditMode = 'single' | 'future' | 'all';
export type DeleteMode = 'single' | 'future' | 'all';
