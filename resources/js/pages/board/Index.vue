<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Pin, PinOff, Send, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import { api } from '@/lib/api';
import type { BreadcrumbItem } from '@/types';

interface Post {
    id: number;
    body: string;
    is_pinned: boolean;
    author: string;
    mine: boolean;
    created_at: string;
}

defineProps<{
    posts: Post[];
    can: { post: boolean; moderate: boolean };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Board' }];

const body = ref('');
const busy = ref(false);
const error = ref<string | null>(null);

const reload = () => router.reload({ only: ['posts'] });

async function submit() {
    if (!body.value.trim() || busy.value) return;
    busy.value = true;
    error.value = null;
    const res = await api('POST', '/board', { body: body.value });
    busy.value = false;
    if (res.ok) {
        body.value = '';
        reload();
    } else {
        error.value = res.message ?? 'Could not post.';
    }
}

async function pin(post: Post) {
    if ((await api('PATCH', `/board/${post.id}/pin`)).ok) reload();
}

async function remove(post: Post) {
    if (!window.confirm('Delete this post?')) return;
    if ((await api('DELETE', `/board/${post.id}`)).ok) reload();
}

const when = (iso: string) =>
    new Date(iso).toLocaleString(undefined, {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
</script>

<template>
    <Head title="Family Board" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-3xl flex-col gap-4 p-4">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">Family board</h1>
                <p class="text-sm text-muted-foreground">
                    Quick notes for everyone — who's picking up, what's for
                    tonight, reminders.
                </p>
            </div>

            <form
                v-if="can.post"
                class="flex flex-col gap-2"
                @submit.prevent="submit"
            >
                <Textarea
                    v-model="body"
                    maxlength="1000"
                    rows="2"
                    placeholder="Write something for the family…"
                    @keydown.meta.enter="submit"
                    @keydown.ctrl.enter="submit"
                />
                <p v-if="error" class="text-sm text-destructive">{{ error }}</p>
                <Button
                    type="submit"
                    class="self-end"
                    :disabled="busy || !body.trim()"
                >
                    <Send class="mr-2 h-4 w-4" /> Post
                </Button>
            </form>

            <p
                v-if="posts.length === 0"
                class="py-10 text-center text-muted-foreground"
            >
                Nothing here yet.
            </p>

            <Card
                v-for="post in posts"
                :key="post.id"
                :class="post.is_pinned ? 'border-amber-500/60' : ''"
            >
                <CardContent class="flex gap-3 p-4">
                    <div class="min-w-0 flex-1">
                        <p class="break-words whitespace-pre-wrap">
                            {{ post.body }}
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ post.author }} · {{ when(post.created_at) }}
                            <span v-if="post.is_pinned"> · 📌 pinned</span>
                        </p>
                    </div>
                    <div class="flex shrink-0 items-start gap-1">
                        <Button
                            v-if="can.moderate"
                            variant="ghost"
                            size="icon"
                            class="h-8 w-8"
                            :title="post.is_pinned ? 'Unpin' : 'Pin to top'"
                            @click="pin(post)"
                        >
                            <component
                                :is="post.is_pinned ? PinOff : Pin"
                                class="h-4 w-4"
                            />
                        </Button>
                        <Button aria-label="Delete"
                            v-if="post.mine || can.moderate"
                            variant="ghost"
                            size="icon"
                            class="h-8 w-8 text-muted-foreground hover:text-destructive"
                            title="Delete"
                            @click="remove(post)"
                        >
                            <Trash2 class="h-4 w-4" />
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
