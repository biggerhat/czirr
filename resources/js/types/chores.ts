import type { FamilyMember } from './calendar';

export type DayOfWeek = 0 | 1 | 2 | 3 | 4 | 5 | 6;

export const DAY_LABELS: Record<DayOfWeek, string> = {
    0: 'Sun',
    1: 'Mon',
    2: 'Tue',
    3: 'Wed',
    4: 'Thu',
    5: 'Fri',
    6: 'Sat',
};

export type ChoreAssignment = {
    id: number;
    chore_id: number;
    family_member_id: number;
    day_of_week: DayOfWeek;
    family_member: FamilyMember;
    chore?: Chore;
};

export type Chore = {
    id: number;
    name: string;
    description: string | null;
    is_active: boolean;
    assignments: ChoreAssignment[];
};
