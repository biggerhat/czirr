import { computed, onMounted, ref } from 'vue';

function urlBase64ToUint8Array(base64: string): Uint8Array<ArrayBuffer> {
    const padding = '='.repeat((4 - (base64.length % 4)) % 4);
    const raw = atob((base64 + padding).replace(/-/g, '+').replace(/_/g, '/'));
    const out = new Uint8Array(new ArrayBuffer(raw.length));
    for (let i = 0; i < raw.length; i++) out[i] = raw.charCodeAt(i);

    return out;
}

function send(method: 'POST' | 'DELETE', body: unknown): Promise<Response> {
    return fetch('/settings/push-subscription', {
        method,
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-XSRF-TOKEN': decodeURIComponent(
                document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
            ),
        },
        body: JSON.stringify(body),
    });
}

export function usePushNotifications(vapidPublicKey: string | null) {
    const supported = ref(false);
    const subscribed = ref(false);
    const busy = ref(false);
    const error = ref<string | null>(null);
    const denied = ref(false);

    const canEnable = computed(
        () => supported.value && !!vapidPublicKey && !denied.value,
    );

    async function currentSubscription() {
        const reg = await navigator.serviceWorker.ready;

        return reg.pushManager.getSubscription();
    }

    onMounted(async () => {
        supported.value =
            'serviceWorker' in navigator &&
            'PushManager' in window &&
            'Notification' in window;
        if (!supported.value) return;

        denied.value = Notification.permission === 'denied';
        subscribed.value = !!(await currentSubscription());
    });

    async function enable() {
        if (!vapidPublicKey) return;
        busy.value = true;
        error.value = null;

        try {
            const permission = await Notification.requestPermission();
            if (permission !== 'granted') {
                denied.value = permission === 'denied';
                error.value = 'Notification permission was not granted.';

                return;
            }

            const reg = await navigator.serviceWorker.ready;
            const sub =
                (await reg.pushManager.getSubscription()) ??
                (await reg.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(vapidPublicKey),
                }));

            const json = sub.toJSON();
            const res = await send('POST', {
                endpoint: json.endpoint,
                keys: json.keys,
                contentEncoding:
                    (PushManager.supportedContentEncodings ?? [])[0] ??
                    'aesgcm',
            });
            if (!res.ok) throw new Error('Server rejected the subscription.');

            subscribed.value = true;
        } catch (e) {
            error.value =
                e instanceof Error ? e.message : 'Could not enable push.';
        } finally {
            busy.value = false;
        }
    }

    async function disable() {
        busy.value = true;
        error.value = null;

        try {
            const sub = await currentSubscription();
            if (sub) {
                await send('DELETE', { endpoint: sub.endpoint });
                await sub.unsubscribe();
            }
            subscribed.value = false;
        } catch (e) {
            error.value =
                e instanceof Error ? e.message : 'Could not disable push.';
        } finally {
            busy.value = false;
        }
    }

    return {
        supported,
        canEnable,
        denied,
        subscribed,
        busy,
        error,
        enable,
        disable,
    };
}
