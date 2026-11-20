import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { ref } from 'vue';

declare global {
    interface Window {
        Pusher: typeof Pusher;
    }
}

let echo: Echo<'reverb'> | null = null;

/** True while the websocket is connected (pages use it to skip fallback polling). */
export const realtimeConnected = ref(false);

/** Lazily creates the Reverb connection; null when realtime isn't configured. */
export function getEcho(): Echo<'reverb'> | null {
    const key = import.meta.env.VITE_REVERB_APP_KEY as string | undefined;
    if (!key) return null;
    if (echo) return echo;

    window.Pusher = Pusher;
    const scheme = (import.meta.env.VITE_REVERB_SCHEME as string) || 'https';
    const port = Number(import.meta.env.VITE_REVERB_PORT) || 443;

    echo = new Echo({
        broadcaster: 'reverb',
        key,
        wsHost:
            (import.meta.env.VITE_REVERB_HOST as string) || location.hostname,
        wsPort: port,
        wssPort: port,
        forceTLS: scheme === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint: '/broadcasting/auth',
        auth: {
            headers: {
                'X-XSRF-TOKEN': decodeURIComponent(
                    document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
                ),
                Accept: 'application/json',
            },
        },
    });

    const connection = echo.connector.pusher.connection;
    connection.bind('connected', () => (realtimeConnected.value = true));
    connection.bind('disconnected', () => (realtimeConnected.value = false));
    connection.bind('unavailable', () => (realtimeConnected.value = false));

    return echo;
}

export function disconnectEcho(): void {
    echo?.disconnect();
    echo = null;
    realtimeConnected.value = false;
}
