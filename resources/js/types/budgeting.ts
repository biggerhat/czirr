import type { EventColor } from './calendar';

export type BudgetCategory = {
    id: number;
    name: string;
    color: EventColor;
    sort_order: number;
};

export type Bill = {
    id: number;
    name: string;
    amount: number;
    start_date: string;
    frequency: 'once' | 'weekly' | 'biweekly' | 'monthly' | 'quarterly' | 'yearly';
    is_active: boolean;
    notes: string | null;
    event_id: number | null;
    budget_category_id: number;
    category: BudgetCategory;
    is_paid_this_month: boolean;
    occurrences_in_range?: number;
};

export type UpcomingBill = Bill & {
    next_due_date: string;
};

export type Expense = {
    id: number;
    name: string;
    amount: number;
    date: string;
    notes: string | null;
    bill_id: number | null;
    budget_category_id: number;
    category: BudgetCategory;
};

export type Income = {
    id: number;
    name: string;
    amount: number;
    start_date: string;
    frequency: 'once' | 'weekly' | 'biweekly' | 'monthly' | 'quarterly' | 'yearly';
    is_active: boolean;
    notes: string | null;
    event_id: number | null;
    occurrences_in_range?: number;
};
