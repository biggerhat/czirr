import { usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

function urlBase64ToUint8Array(base64String: string): Uint8Array {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; i++) {
        outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
}

export function usePushSubscription() {
    const isLoading = ref(false);
    const subscription = ref<PushSubscription | null>(null);
    const permission = ref<NotificationPermission>(
        typeof Notification !== 'undefined' ? Notification.permission : 'default',
    );

    const isSupported = computed(
        () => typeof window !== 'undefined' && 'serviceWorker' in navigator && 'PushManager' in window,
    );

    const isSubscribed = computed(() => subscription.value !== null);

    async function loadExistingSubscription() {
        if (!isSupported.value) return;
        const registration = await navigator.serviceWorker.ready;
        subscription.value = await registration.pushManager.getSubscription();
    }

    // Load on init
    loadExistingSubscription();

    async function subscribe() {
        if (!isSupported.value) return;

        isLoading.value = true;
        try {
            const result = await Notification.requestPermission();
            permission.value = result;

            if (result !== 'granted') return;

            const page = usePage();
            const vapidKey = page.props.vapidPublicKey as string;
            if (!vapidKey) return;

            const registration = await navigator.serviceWorker.ready;
            const sub = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(vapidKey),
            });

            subscription.value = sub;

            const json = sub.toJSON();
            const csrfToken =
                document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

            await fetch('/push-subscriptions', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    Accept: 'application/json',
                },
                body: JSON.stringify({
                    endpoint: json.endpoint,
                    keys: {
                        p256dh: json.keys?.p256dh,
                        auth: json.keys?.auth,
                    },
                }),
            });
        } finally {
            isLoading.value = false;
        }
    }

    async function unsubscribe() {
        if (!subscription.value) return;

        isLoading.value = true;
        try {
            const endpoint = subscription.value.endpoint;
            await subscription.value.unsubscribe();
            subscription.value = null;

            const csrfToken =
                document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

            await fetch('/push-subscriptions', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    Accept: 'application/json',
                },
                body: JSON.stringify({ endpoint }),
            });
        } finally {
            isLoading.value = false;
        }
    }

    return {
        isSupported,
        isSubscribed,
        isLoading,
        permission,
        subscribe,
        unsubscribe,
    };
}
