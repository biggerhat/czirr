<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Star } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { api } from '@/lib/api';

interface Idea {
    id: number;
    name: string;
    total_time: number;
    rating: number | null;
    last_planned: string | null;
}

const props = defineProps<{ open: boolean; defaultDate: string }>();
const emit = defineEmits<{ 'update:open': [boolean] }>();

const ideas = ref<Idea[]>([]);
const loading = ref(false);
const date = ref(props.defaultDate);
const addingId = ref<number | null>(null);
const added = ref<string[]>([]);

watch(
    () => props.open,
    async (open) => {
        if (!open) return;
        date.value = props.defaultDate;
        added.value = [];
        loading.value = true;
        const res = await fetch('/meal-plans/suggestions', {
            headers: { Accept: 'application/json' },
        });
        ideas.value = res.ok ? await res.json() : [];
        loading.value = false;
    },
);

async function add(idea: Idea) {
    addingId.value = idea.id;
    const res = await api('POST', '/meal-plan-entries', {
        date: date.value,
        meal_type: 'dinner',
        recipe_id: idea.id,
        name: idea.name,
    });
    addingId.value = null;
    if (res.ok) {
        added.value.push(idea.name);
        router.reload({ only: ['entries'] });
    }
}

function lastPlanned(d: string | null) {
    if (!d) return 'never planned';
    const days = Math.round(
        (Date.now() - new Date(d + 'T00:00:00').getTime()) / 864e5,
    );

    return `last planned ${days} days ago`;
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Dinner ideas</DialogTitle>
                <DialogDescription>
                    Recipes you haven't planned in the last two weeks, best
                    rated first.
                </DialogDescription>
            </DialogHeader>

            <div class="flex items-center gap-2 text-sm">
                <label for="idea-date">Add to</label>
                <Input
                    id="idea-date"
                    v-model="date"
                    type="date"
                    class="h-8 w-40"
                />
            </div>

            <p
                v-if="loading"
                class="py-6 text-center text-sm text-muted-foreground"
            >
                Thinking…
            </p>
            <p
                v-else-if="ideas.length === 0"
                class="py-6 text-center text-sm text-muted-foreground"
            >
                No ideas right now — add some recipes first.
            </p>

            <ul v-else class="max-h-80 divide-y overflow-y-auto">
                <li
                    v-for="idea in ideas"
                    :key="idea.id"
                    class="flex items-center justify-between gap-3 py-2"
                >
                    <div class="min-w-0">
                        <div class="truncate font-medium">{{ idea.name }}</div>
                        <div
                            class="flex items-center gap-2 text-xs text-muted-foreground"
                        >
                            <span
                                v-if="idea.rating"
                                class="flex items-center gap-0.5"
                            >
                                <Star
                                    class="h-3 w-3 fill-yellow-400 text-yellow-400"
                                />
                                {{ idea.rating }}
                            </span>
                            <span v-if="idea.total_time"
                                >{{ idea.total_time }} min</span
                            >
                            <span>{{ lastPlanned(idea.last_planned) }}</span>
                        </div>
                    </div>
                    <Button
                        size="sm"
                        variant="outline"
                        :disabled="
                            addingId === idea.id || added.includes(idea.name)
                        "
                        @click="add(idea)"
                    >
                        {{ added.includes(idea.name) ? 'Added' : 'Add' }}
                    </Button>
                </li>
            </ul>
        </DialogContent>
    </Dialog>
</template>
