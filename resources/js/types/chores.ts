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
    points: number;
    assignments: ChoreAssignment[];
};

export type ChoreCompletion = {
    id: number;
    chore_assignment_id: number;
    family_member_id: number;
    completed_date: string;
    points_earned: number;
};

export type BonusObjective = {
    id: number;
    user_id: number;
    name: string;
    description: string | null;
    points: number;
    claimed_by: number | null;
    claimed_at: string | null;
    claimed_by_member?: FamilyMember | null;
};

export type MemberScore = {
    family_member_id: number;
    name: string;
    color: string;
    chore_points: number;
    bonus_points: number;
    streak_bonus: number;
    weekly_total: number;
    streak: number;
    rank: number;
};

export type OverallScore = {
    family_member_id: number;
    name: string;
    color: string;
    total: number;
};

export type StreakMilestone = {
    id: number;
    days_required: number;
    bonus_points: number;
};
