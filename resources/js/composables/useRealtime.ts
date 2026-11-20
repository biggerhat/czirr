import { router, usePage } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, watch } from 'vue';
import { disconnectEcho, getEcho, realtimeConnected } from '@/lib/echo';

/** Which Inertia pages care about which kind of change. */
const PAGES: Record<string, string[]> = {
    lists: ['lists/', 'Dashboard'],
    chores: ['chores/', 'Dashboard', 'my-day/', 'Scoreboard'],
    family: [
        'family/',
        'chores/',
        'Dashboard',
        'Scoreboard',
        'savings/',
        'my-day/',
    ],
    rewards: ['rewards/', 'my-day/', 'Dashboard'],
    board: ['board/', 'Dashboard'],
    pantry: ['pantry/'],
    savings: ['savings/', 'Dashboard'],
    meals: ['meal-plans/', 'Dashboard'],
    calendar: ['calendar/', 'Dashboard'],
    budget: ['budgeting/', 'Dashboard'],
    contacts: ['contacts/'],
    recipes: ['recipes/'],
    games: ['games/', 'yahtzee/'],
};

/**
 * Subscribes to the family's private channel and refreshes the current Inertia
 * page (a partial reload that keeps scroll and local state) when relevant data
 * changes elsewhere. No-ops when realtime isn't configured on the server.
 */
export function useRealtime() {
    const page = usePage();
    let timer: ReturnType<typeof setTimeout> | null = null;
    let channelName: string | null = null;
    let stop: (() => void) | null = null;

    const refresh = () => {
        if (timer) clearTimeout(timer);
        timer = setTimeout(() => {
            if (document.hidden) return;
            router.reload();
        }, 350);
    };

    const handler = (e: { resource: string }) => {
        const component = page.component;
        const targets = PAGES[e.resource] ?? [];
        if (targets.some((t) => component === t || component.startsWith(t))) {
            refresh();
        }
    };

    const connect = () => {
        const familyId = (page.props.realtime as { familyId: number } | null)
            ?.familyId;
        const echo = familyId ? getEcho() : null;
        if (!echo || !familyId || channelName === `family.${familyId}`) return;

        channelName = `family.${familyId}`;
        echo.private(channelName).listen('.data.changed', handler);
        stop = () => {
            echo.leave(channelName!);
            channelName = null;
        };
    };

    // Catch up on anything missed while the socket was down or the tab hidden.
    const catchUp = () => {
        if (!document.hidden && channelName) refresh();
    };

    onMounted(() => {
        connect();
        document.addEventListener('visibilitychange', catchUp);
    });

    watch(realtimeConnected, (up) => up && refresh());

    onBeforeUnmount(() => {
        document.removeEventListener('visibilitychange', catchUp);
        if (timer) clearTimeout(timer);
        stop?.();
        disconnectEcho();
    });
}
