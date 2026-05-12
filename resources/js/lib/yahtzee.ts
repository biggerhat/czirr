import type { EventColor } from '@/types/calendar';

export type PlayerColorClasses = {
    text: string;
    dot: string;
    tint: string;
    softBg: string;
    softBorder: string;
    hoverBg: string;
    ring: string;
    chipBg: string;
};

export const PLAYER_COLORS: Record<EventColor, PlayerColorClasses> = {
    rose: {
        text: 'text-rose-700 dark:text-rose-300',
        dot: 'bg-rose-500',
        tint: 'bg-rose-50 dark:bg-rose-950/30',
        softBg: 'bg-rose-100 dark:bg-rose-900/30',
        softBorder: 'border-rose-400 dark:border-rose-600',
        hoverBg: 'hover:bg-rose-100 dark:hover:bg-rose-900/40',
        ring: 'ring-rose-400 dark:ring-rose-600',
        chipBg: 'bg-rose-100 dark:bg-rose-900/40',
    },
    orange: {
        text: 'text-orange-700 dark:text-orange-300',
        dot: 'bg-orange-500',
        tint: 'bg-orange-50 dark:bg-orange-950/30',
        softBg: 'bg-orange-100 dark:bg-orange-900/30',
        softBorder: 'border-orange-400 dark:border-orange-600',
        hoverBg: 'hover:bg-orange-100 dark:hover:bg-orange-900/40',
        ring: 'ring-orange-400 dark:ring-orange-600',
        chipBg: 'bg-orange-100 dark:bg-orange-900/40',
    },
    amber: {
        text: 'text-amber-700 dark:text-amber-300',
        dot: 'bg-amber-500',
        tint: 'bg-amber-50 dark:bg-amber-950/30',
        softBg: 'bg-amber-100 dark:bg-amber-900/30',
        softBorder: 'border-amber-400 dark:border-amber-600',
        hoverBg: 'hover:bg-amber-100 dark:hover:bg-amber-900/40',
        ring: 'ring-amber-400 dark:ring-amber-600',
        chipBg: 'bg-amber-100 dark:bg-amber-900/40',
    },
    emerald: {
        text: 'text-emerald-700 dark:text-emerald-300',
        dot: 'bg-emerald-500',
        tint: 'bg-emerald-50 dark:bg-emerald-950/30',
        softBg: 'bg-emerald-100 dark:bg-emerald-900/30',
        softBorder: 'border-emerald-400 dark:border-emerald-600',
        hoverBg: 'hover:bg-emerald-100 dark:hover:bg-emerald-900/40',
        ring: 'ring-emerald-400 dark:ring-emerald-600',
        chipBg: 'bg-emerald-100 dark:bg-emerald-900/40',
    },
    cyan: {
        text: 'text-cyan-700 dark:text-cyan-300',
        dot: 'bg-cyan-500',
        tint: 'bg-cyan-50 dark:bg-cyan-950/30',
        softBg: 'bg-cyan-100 dark:bg-cyan-900/30',
        softBorder: 'border-cyan-400 dark:border-cyan-600',
        hoverBg: 'hover:bg-cyan-100 dark:hover:bg-cyan-900/40',
        ring: 'ring-cyan-400 dark:ring-cyan-600',
        chipBg: 'bg-cyan-100 dark:bg-cyan-900/40',
    },
    blue: {
        text: 'text-blue-700 dark:text-blue-300',
        dot: 'bg-blue-500',
        tint: 'bg-blue-50 dark:bg-blue-950/30',
        softBg: 'bg-blue-100 dark:bg-blue-900/30',
        softBorder: 'border-blue-400 dark:border-blue-600',
        hoverBg: 'hover:bg-blue-100 dark:hover:bg-blue-900/40',
        ring: 'ring-blue-400 dark:ring-blue-600',
        chipBg: 'bg-blue-100 dark:bg-blue-900/40',
    },
    violet: {
        text: 'text-violet-700 dark:text-violet-300',
        dot: 'bg-violet-500',
        tint: 'bg-violet-50 dark:bg-violet-950/30',
        softBg: 'bg-violet-100 dark:bg-violet-900/30',
        softBorder: 'border-violet-400 dark:border-violet-600',
        hoverBg: 'hover:bg-violet-100 dark:hover:bg-violet-900/40',
        ring: 'ring-violet-400 dark:ring-violet-600',
        chipBg: 'bg-violet-100 dark:bg-violet-900/40',
    },
    pink: {
        text: 'text-pink-700 dark:text-pink-300',
        dot: 'bg-pink-500',
        tint: 'bg-pink-50 dark:bg-pink-950/30',
        softBg: 'bg-pink-100 dark:bg-pink-900/30',
        softBorder: 'border-pink-400 dark:border-pink-600',
        hoverBg: 'hover:bg-pink-100 dark:hover:bg-pink-900/40',
        ring: 'ring-pink-400 dark:ring-pink-600',
        chipBg: 'bg-pink-100 dark:bg-pink-900/40',
    },
};

export function playerColorClasses(color: EventColor | undefined): PlayerColorClasses {
    return PLAYER_COLORS[color ?? 'blue'] ?? PLAYER_COLORS.blue;
}
