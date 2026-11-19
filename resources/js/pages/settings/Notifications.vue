<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePushNotifications } from '@/composables/usePushNotifications';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { api } from '@/lib/api';
import { edit, update } from '@/routes/notifications';
import { type BreadcrumbItem } from '@/types';

const props = defineProps<{
    timezone: string;
    vapidPublicKey: string | null;
    calendarFeedUrl: string | null;
    preferences: {
        digest_email: boolean;
        digest_push: boolean;
        alerts_push: boolean;
        weekly_recap: boolean;
        digest_hour: number;
        bills_due_days: number;
    };
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Notification settings', href: edit().url },
];

const browserTimezone =
    Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';

const form = useForm({
    timezone: props.timezone === 'UTC' ? browserTimezone : props.timezone,
    digest_email: props.preferences.digest_email,
    digest_push: props.preferences.digest_push,
    alerts_push: props.preferences.alerts_push,
    weekly_recap: props.preferences.weekly_recap,
    digest_hour: props.preferences.digest_hour,
    bills_due_days: props.preferences.bills_due_days,
});

const feedUrl = ref<string | null>(props.calendarFeedUrl);
const copied = ref(false);

async function rotateFeed() {
    if (
        feedUrl.value &&
        !confirm(
            'Create a new link? Calendars using the old link will stop updating.',
        )
    )
        return;
    const res = await api<{ url: string }>('POST', '/settings/calendar-feed');
    if (res.ok) feedUrl.value = res.data?.url ?? null;
}

async function disableFeed() {
    const res = await api('DELETE', '/settings/calendar-feed');
    if (res.ok) feedUrl.value = null;
}

async function copyFeed() {
    if (!feedUrl.value) return;
    await navigator.clipboard.writeText(feedUrl.value);
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
}

const push = usePushNotifications(props.vapidPublicKey);

const submit = () => form.patch(update().url, { preserveScroll: true });
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Notification settings" />

        <SettingsLayout>
            <div class="space-y-6">
                <Heading
                    variant="small"
                    title="Notification settings"
                    description="Choose what lands in your inbox each morning"
                />

                <form class="space-y-6" @submit.prevent="submit">
                    <div class="flex items-center gap-3">
                        <Checkbox
                            id="digest_email"
                            v-model="form.digest_email"
                        />
                        <Label for="digest_email">
                            Email me a daily digest (events, chores, meals,
                            bills)
                        </Label>
                    </div>

                    <div class="flex items-center gap-3">
                        <Checkbox id="digest_push" v-model="form.digest_push" />
                        <Label for="digest_push">
                            Send the digest as a push notification
                        </Label>
                    </div>

                    <div class="flex items-center gap-3">
                        <Checkbox
                            id="weekly_recap"
                            v-model="form.weekly_recap"
                        />
                        <Label for="weekly_recap">
                            Send a weekly recap on Sunday evening
                        </Label>
                    </div>

                    <div class="flex items-center gap-3">
                        <Checkbox id="alerts_push" v-model="form.alerts_push" />
                        <Label for="alerts_push">
                            Push alerts for things that need you (e.g. reward
                            requests)
                        </Label>
                    </div>

                    <div class="space-y-2 rounded-md border p-4">
                        <p class="text-sm font-medium">
                            Push on this device:
                            {{ push.subscribed.value ? 'on' : 'off' }}
                        </p>
                        <p
                            v-if="!push.supported.value"
                            class="text-sm text-muted-foreground"
                        >
                            This browser doesn't support push. On iPhone, add
                            the app to your Home Screen first.
                        </p>
                        <p
                            v-else-if="push.denied.value"
                            class="text-sm text-muted-foreground"
                        >
                            Notifications are blocked. Allow them in your
                            browser's site settings.
                        </p>
                        <Button
                            v-else-if="!push.subscribed.value"
                            type="button"
                            variant="outline"
                            :disabled="!push.canEnable.value || push.busy.value"
                            @click="push.enable()"
                        >
                            Enable on this device
                        </Button>
                        <Button
                            v-else
                            type="button"
                            variant="outline"
                            :disabled="push.busy.value"
                            @click="push.disable()"
                        >
                            Disable on this device
                        </Button>
                        <p v-if="push.error.value" class="text-sm text-red-600">
                            {{ push.error.value }}
                        </p>
                    </div>

                    <div class="grid gap-2">
                        <Label for="timezone">Timezone</Label>
                        <Input id="timezone" v-model="form.timezone" />
                        <p
                            v-if="form.errors.timezone"
                            class="text-sm text-red-600"
                        >
                            {{ form.errors.timezone }}
                        </p>
                    </div>

                    <div class="grid gap-2">
                        <Label for="digest_hour">Send at (hour, 0–23)</Label>
                        <Input
                            id="digest_hour"
                            v-model.number="form.digest_hour"
                            type="number"
                            min="0"
                            max="23"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="bills_due_days">
                            Warn about bills due within (days)
                        </Label>
                        <Input
                            id="bills_due_days"
                            v-model.number="form.bills_due_days"
                            type="number"
                            min="0"
                            max="14"
                        />
                    </div>

                    <Button type="submit" :disabled="form.processing">
                        Save
                    </Button>
                </form>

                <div class="space-y-3 border-t pt-6">
                    <Heading
                        variant="small"
                        title="Calendar feed"
                        description="Subscribe in Google Calendar, Apple Calendar or Outlook to see family events and meals there. Anyone with the link can view them, so keep it private."
                    />
                    <div v-if="feedUrl" class="space-y-2">
                        <Input
                            :model-value="feedUrl"
                            readonly
                            @focus="
                                ($event.target as HTMLInputElement).select()
                            "
                        />
                        <div class="flex flex-wrap gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                @click="copyFeed"
                            >
                                {{ copied ? 'Copied!' : 'Copy link' }}
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                @click="rotateFeed"
                            >
                                Reset link
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                @click="disableFeed"
                            >
                                Turn off
                            </Button>
                        </div>
                    </div>
                    <Button
                        v-else
                        type="button"
                        variant="outline"
                        @click="rotateFeed"
                    >
                        Create calendar link
                    </Button>
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
