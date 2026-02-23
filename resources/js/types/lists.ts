import type { FamilyMember } from './calendar';

export type ListType = 'grocery' | 'shopping' | 'todo' | 'wishlist' | 'custom';
export type ListVisibility = 'everyone' | 'parents' | 'children' | 'specific';

export type FamilyListItem = {
    id: number;
    family_list_id: number;
    name: string;
    is_completed: boolean;
    quantity: string | null;
    notes: string | null;
    position: number;
    created_at: string;
    updated_at: string;
};

export type FamilyList = {
    id: number;
    user_id: number;
    name: string;
    type: ListType;
    visibility: ListVisibility;
    items?: FamilyListItem[];
    items_count?: number;
    members?: FamilyMember[];
    created_at: string;
    updated_at: string;
};

export const LIST_TYPE_LABELS: Record<ListType, string> = {
    grocery: 'Grocery',
    shopping: 'Shopping',
    todo: 'To-Do',
    wishlist: 'Wishlist',
    custom: 'Custom',
};

export const LIST_VISIBILITY_LABELS: Record<ListVisibility, string> = {
    everyone: 'Everyone',
    parents: 'Parents Only',
    children: 'Children Only',
    specific: 'Specific Members',
};
