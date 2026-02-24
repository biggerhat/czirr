import type { CalendarEvent, EventColor } from '@/types/calendar';

const formatterCache = new Map<string, Intl.DateTimeFormat>();

function getCachedFormatter(locale: string | undefined, timezone: string, options: Intl.DateTimeFormatOptions): Intl.DateTimeFormat {
    const key = (locale ?? '') + '|' + timezone + '|' + JSON.stringify(options);
    let fmt = formatterCache.get(key);
    if (!fmt) {
        fmt = new Intl.DateTimeFormat(locale, { timeZone: timezone, ...options });
        formatterCache.set(key, fmt);
    }
    return fmt;
}

export type ColorClasses = {
    bg: string;
    text: string;
    border: string;
    dot: string;
};

export const EVENT_COLORS: Record<EventColor, ColorClasses> = {
    rose: { bg: 'bg-rose-100 dark:bg-rose-900/30', text: 'text-rose-700 dark:text-rose-300', border: 'border-rose-300 dark:border-rose-700', dot: 'bg-rose-500' },
    orange: { bg: 'bg-orange-100 dark:bg-orange-900/30', text: 'text-orange-700 dark:text-orange-300', border: 'border-orange-300 dark:border-orange-700', dot: 'bg-orange-500' },
    amber: { bg: 'bg-amber-100 dark:bg-amber-900/30', text: 'text-amber-700 dark:text-amber-300', border: 'border-amber-300 dark:border-amber-700', dot: 'bg-amber-500' },
    emerald: { bg: 'bg-emerald-100 dark:bg-emerald-900/30', text: 'text-emerald-700 dark:text-emerald-300', border: 'border-emerald-300 dark:border-emerald-700', dot: 'bg-emerald-500' },
    cyan: { bg: 'bg-cyan-100 dark:bg-cyan-900/30', text: 'text-cyan-700 dark:text-cyan-300', border: 'border-cyan-300 dark:border-cyan-700', dot: 'bg-cyan-500' },
    blue: { bg: 'bg-blue-100 dark:bg-blue-900/30', text: 'text-blue-700 dark:text-blue-300', border: 'border-blue-300 dark:border-blue-700', dot: 'bg-blue-500' },
    violet: { bg: 'bg-violet-100 dark:bg-violet-900/30', text: 'text-violet-700 dark:text-violet-300', border: 'border-violet-300 dark:border-violet-700', dot: 'bg-violet-500' },
    pink: { bg: 'bg-pink-100 dark:bg-pink-900/30', text: 'text-pink-700 dark:text-pink-300', border: 'border-pink-300 dark:border-pink-700', dot: 'bg-pink-500' },
};

export function getEventColor(event: CalendarEvent): EventColor {
    if (event.source === 'bill') return 'rose';
    if (event.source === 'income') return 'emerald';
    return event.family_members?.length > 0
        ? event.family_members[0].color
        : 'blue';
}

export function formatEventTime(dateStr: string, timezone: string): string {
    const opts: Intl.DateTimeFormatOptions = { hour: 'numeric', minute: '2-digit' };
    return getCachedFormatter(undefined, timezone, opts).format(new Date(dateStr));
}

export function formatEventDate(dateStr: string, timezone: string): string {
    const opts: Intl.DateTimeFormatOptions = { weekday: 'short', month: 'short', day: 'numeric' };
    return getCachedFormatter(undefined, timezone, opts).format(new Date(dateStr));
}

export function formatEventDateFull(dateStr: string, timezone: string): string {
    const opts: Intl.DateTimeFormatOptions = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    return getCachedFormatter(undefined, timezone, opts).format(new Date(dateStr));
}

export function toLocalDateString(date: Date, timezone: string): string {
    const opts: Intl.DateTimeFormatOptions = { year: 'numeric', month: '2-digit', day: '2-digit' };
    const parts = getCachedFormatter('en-CA', timezone, opts).formatToParts(date);
    const year = parts.find(p => p.type === 'year')!.value;
    const month = parts.find(p => p.type === 'month')!.value;
    const day = parts.find(p => p.type === 'day')!.value;
    return `${year}-${month}-${day}`;
}

export function isSameDay(a: Date, b: Date, timezone: string): boolean {
    return toLocalDateString(a, timezone) === toLocalDateString(b, timezone);
}

export function getLocalHour(date: Date, timezone: string): number {
    return parseInt(getCachedFormatter('en-US', timezone, { hour: 'numeric', hour12: false }).format(date));
}

export function getLocalMinute(date: Date, timezone: string): number {
    return parseInt(getCachedFormatter('en-US', timezone, { minute: 'numeric' }).format(date));
}
