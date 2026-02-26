import type { Weekday } from 'rrule';
import { RRule } from 'rrule';

export type RecurrenceFrequency = 'none' | 'daily' | 'weekday' | 'weekly' | 'biweekly' | 'monthly' | 'yearly';
export type RecurrenceEndType = 'never' | 'until' | 'count';

export type RecurrenceConfig = {
    frequency: RecurrenceFrequency;
    interval: number;
    byWeekday: number[]; // 0=Mon, 1=Tue, ..., 6=Sun (rrule.js convention)
    endType: RecurrenceEndType;
    untilDate: string | null; // YYYY-MM-DD
    count: number | null;
};

export function defaultRecurrenceConfig(): RecurrenceConfig {
    return {
        frequency: 'none',
        interval: 1,
        byWeekday: [],
        endType: 'never',
        untilDate: null,
        count: null,
    };
}

const FREQ_MAP: Record<string, number> = {
    daily: RRule.DAILY,
    weekly: RRule.WEEKLY,
    monthly: RRule.MONTHLY,
    yearly: RRule.YEARLY,
};

const FREQ_REVERSE: Record<number, RecurrenceFrequency> = {
    [RRule.DAILY]: 'daily',
    [RRule.WEEKLY]: 'weekly',
    [RRule.MONTHLY]: 'monthly',
    [RRule.YEARLY]: 'yearly',
};

const WEEKDAY_OBJECTS: Weekday[] = [
    RRule.MO, RRule.TU, RRule.WE, RRule.TH, RRule.FR, RRule.SA, RRule.SU,
];

export function buildRRuleString(config: RecurrenceConfig): string | null {
    if (config.frequency === 'none') return null;

    const isWeekday = config.frequency === 'weekday';
    const isBiweekly = config.frequency === 'biweekly';

    const options: Partial<ConstructorParameters<typeof RRule>[0]> = {
        freq: isWeekday || isBiweekly ? RRule.WEEKLY : FREQ_MAP[config.frequency],
        interval: isWeekday ? 1 : isBiweekly ? 2 : config.interval,
    };

    if (isWeekday) {
        options.byweekday = [RRule.MO, RRule.TU, RRule.WE, RRule.TH, RRule.FR];
    } else if ((config.frequency === 'weekly' || isBiweekly) && config.byWeekday.length > 0) {
        options.byweekday = config.byWeekday.map(d => WEEKDAY_OBJECTS[d]);
    }

    if (config.endType === 'until' && config.untilDate) {
        const [y, m, d] = config.untilDate.split('-').map(Number);
        options.until = new Date(Date.UTC(y, m - 1, d, 23, 59, 59));
    } else if (config.endType === 'count' && config.count) {
        options.count = config.count;
    }

    const rule = new RRule(options);
    // RRule.toString() returns "RRULE:FREQ=..." — we only want the part after "RRULE:"
    const str = rule.toString();
    return str.startsWith('RRULE:') ? str.slice(6) : str;
}

export function parseRRuleString(rrule: string | null | undefined): RecurrenceConfig {
    if (!rrule) return defaultRecurrenceConfig();

    try {
        const fullStr = rrule.startsWith('RRULE:') ? rrule : `RRULE:${rrule}`;
        const rule = RRule.fromString(fullStr);
        const opts = rule.origOptions;

        const config: RecurrenceConfig = {
            frequency: FREQ_REVERSE[opts.freq ?? -1] ?? 'none',
            interval: opts.interval ?? 1,
            byWeekday: [],
            endType: 'never',
            untilDate: null,
            count: null,
        };

        if (opts.byweekday) {
            const days = Array.isArray(opts.byweekday) ? opts.byweekday : [opts.byweekday];
            config.byWeekday = days.map(d => {
                if (typeof d === 'number') return d;
                if (typeof d === 'string') {
                    const map: Record<string, number> = { MO: 0, TU: 1, WE: 2, TH: 3, FR: 4, SA: 5, SU: 6 };
                    return map[d] ?? 0;
                }
                return (d as Weekday).weekday;
            });

            // Detect "every weekday" pattern: weekly, interval 1, exactly Mon-Fri
            const sorted = [...config.byWeekday].sort((a, b) => a - b);
            if (
                config.frequency === 'weekly' &&
                config.interval === 1 &&
                sorted.length === 5 &&
                sorted[0] === 0 && sorted[1] === 1 && sorted[2] === 2 && sorted[3] === 3 && sorted[4] === 4
            ) {
                config.frequency = 'weekday';
                config.byWeekday = [];
            }
        }

        // Detect bi-weekly pattern: weekly with interval 2
        if (config.frequency === 'weekly' && config.interval === 2) {
            config.frequency = 'biweekly';
            config.interval = 1;
        }

        if (opts.until) {
            config.endType = 'until';
            const dt = opts.until;
            const y = dt.getUTCFullYear();
            const m = String(dt.getUTCMonth() + 1).padStart(2, '0');
            const d = String(dt.getUTCDate()).padStart(2, '0');
            config.untilDate = `${y}-${m}-${d}`;
        } else if (opts.count) {
            config.endType = 'count';
            config.count = opts.count;
        }

        return config;
    } catch {
        return defaultRecurrenceConfig();
    }
}

const DAY_NAMES = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
const FREQ_LABELS: Record<string, string> = {
    daily: 'day',
    weekly: 'week',
    monthly: 'month',
    yearly: 'year',
};

export function humanReadableRRule(rrule: string | null | undefined): string {
    if (!rrule) return '';

    const config = parseRRuleString(rrule);
    if (config.frequency === 'none') return '';

    if (config.frequency === 'weekday') {
        let text = 'Every weekday';
        if (config.endType === 'until' && config.untilDate) {
            text += ` until ${config.untilDate}`;
        } else if (config.endType === 'count' && config.count) {
            text += `, ${config.count} times`;
        }
        return text;
    }

    if (config.frequency === 'biweekly') {
        let text = 'Every 2 weeks';
        if (config.byWeekday.length > 0) {
            const dayNames = config.byWeekday
                .sort((a, b) => a - b)
                .map(d => DAY_NAMES[d]);
            text += ` on ${dayNames.join(', ')}`;
        }
        if (config.endType === 'until' && config.untilDate) {
            text += ` until ${config.untilDate}`;
        } else if (config.endType === 'count' && config.count) {
            text += `, ${config.count} times`;
        }
        return text;
    }

    const unit = FREQ_LABELS[config.frequency] ?? config.frequency;
    let text = config.interval === 1
        ? `Every ${unit}`
        : `Every ${config.interval} ${unit}s`;

    if (config.frequency === 'weekly' && config.byWeekday.length > 0) {
        const dayNames = config.byWeekday
            .sort((a, b) => a - b)
            .map(d => DAY_NAMES[d]);
        text += ` on ${dayNames.join(', ')}`;
    }

    if (config.endType === 'until' && config.untilDate) {
        text += ` until ${config.untilDate}`;
    } else if (config.endType === 'count' && config.count) {
        text += `, ${config.count} times`;
    }

    return text;
}
