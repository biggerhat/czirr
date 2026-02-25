<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { usePushSubscription } from '@/composables/usePushSubscription';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { edit } from '@/routes/notifications';
import { type BreadcrumbItem } from '@/types';

type NotificationTypeItem = {
    value: string;
    label: string;
    description: string;
    enabled: boolean;
};

const props = defineProps<{
    notificationTypes: NotificationTypeItem[];
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Notification settings',
        href: edit().url,
    },
];

const { isSupported, isSubscribed, isLoading, permission, subscribe, unsubscribe } =
    usePushSubscription();

const preferences = ref(
    props.notificationTypes.map((t) => ({ type: t.value, enabled: t.enabled })),
);

const saving = ref(false);
const recentlySaved = ref(false);

function save() {
    saving.value = true;
    router.put(
        '/settings/notifications',
        { preferences: preferences.value },
        {
            preserveScroll: true,
            onSuccess() {
                recentlySaved.value = true;
                setTimeout(() => (recentlySaved.value = false), 2000);
            },
            onFinish() {
                saving.value = false;
            },
        },
    );
}

function togglePreference(index: number, value: boolean) {
    preferences.value[index].enabled = value;
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Notification settings" />

        <h1 class="sr-only">Notification Settings</h1>

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <Heading
                    variant="small"
                    title="Push notifications"
                    description="Receive push notifications on this device"
                />

                <div v-if="!isSupported" class="text-sm text-muted-foreground">
                    Push notifications are not supported in this browser.
                </div>

                <div v-else class="space-y-4">
                    <div
                        v-if="permission === 'denied'"
                        class="rounded-md border border-destructive/50 bg-destructive/10 p-3 text-sm text-destructive"
                    >
                        Notifications are blocked by your browser. Please enable
                        them in your browser settings and reload the page.
                    </div>

                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium">
                                {{ isSubscribed ? 'Notifications enabled' : 'Notifications disabled' }}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                {{
                                    isSubscribed
                                        ? 'You will receive push notifications on this device.'
                                        : 'Enable to receive push notifications.'
                                }}
                            </p>
                        </div>
                        <Button
                            :disabled="isLoading || permission === 'denied'"
                            variant="outline"
                            size="sm"
                            @click="isSubscribed ? unsubscribe() : subscribe()"
                        >
                            {{ isSubscribed ? 'Disable' : 'Enable' }}
                        </Button>
                    </div>
                </div>
            </div>

            <div class="flex flex-col space-y-6">
                <Heading
                    variant="small"
                    title="Notification types"
                    description="Choose which notifications you want to receive"
                />

                <div class="space-y-4">
                    <div
                        v-for="(notifType, index) in notificationTypes"
                        :key="notifType.value"
                        class="flex items-center justify-between gap-4"
                    >
                        <div class="space-y-0.5">
                            <Label :for="`notif-${notifType.value}`">{{
                                notifType.label
                            }}</Label>
                            <p class="text-sm text-muted-foreground">
                                {{ notifType.description }}
                            </p>
                        </div>
                        <Switch
                            :id="`notif-${notifType.value}`"
                            :model-value="preferences[index].enabled"
                            @update:model-value="togglePreference(index, $event)"
                        />
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <Button :disabled="saving" @click="save">Save</Button>

                    <Transition
                        enter-active-class="transition ease-in-out"
                        enter-from-class="opacity-0"
                        leave-active-class="transition ease-in-out"
                        leave-to-class="opacity-0"
                    >
                        <p
                            v-show="recentlySaved"
                            class="text-sm text-neutral-600"
                        >
                            Saved.
                        </p>
                    </Transition>
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
